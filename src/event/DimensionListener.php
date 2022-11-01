<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\event;

use jasonwynn10\NativeDimensions\block\Obsidian;
use jasonwynn10\NativeDimensions\block\Portal;
use jasonwynn10\NativeDimensions\Main;
use jasonwynn10\NativeDimensions\world\data\NetherPortalData;
use jasonwynn10\NativeDimensions\world\data\NetherPortalMap;
use jasonwynn10\NativeDimensions\world\DimensionalWorld;
use jasonwynn10\NativeDimensions\world\provider\DimensionLevelDBProvider;
use pocketmine\block\Air;
use pocketmine\block\BlockFactory;
use pocketmine\block\Fire;
use pocketmine\block\NetherPortal;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBedEnterEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\event\world\WorldSaveEvent;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\network\mcpe\protocol\types\SpawnSettings;
use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\ClosureTask;
use pocketmine\timings\Timings;
use pocketmine\timings\TimingsHandler;
use pocketmine\world\format\SubChunk;
use pocketmine\world\Position;

class DimensionListener implements Listener {
	/** @var Main */
	protected $plugin;

	private static array $syncPortalsLoadTimer = [];
	private static array $syncPortalsSaveTimer = [];
	private static array $syncPortalLocateTimer = [];

	public function __construct(Main $plugin) {
		$plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
		$this->plugin = $plugin;
	}

	private static function initTimings(DimensionalWorld $world){
		if(isset(self::$syncPortalsLoadTimer[$world->getId()]))
			return;

		self::$syncPortalsLoadTimer[$world->getId()] = new TimingsHandler(Timings::INCLUDED_BY_OTHER_TIMINGS_PREFIX . "{$world->getFolderName()} - Portals Load", Timings::$worldLoad);
		self::$syncPortalsSaveTimer[$world->getId()] = new TimingsHandler(Timings::INCLUDED_BY_OTHER_TIMINGS_PREFIX . "{$world->getFolderName()} - Portals Save", Timings::$worldSave);
		self::$syncPortalLocateTimer[$world->getId()] = new TimingsHandler(Timings::INCLUDED_BY_OTHER_TIMINGS_PREFIX . "{$world->getFolderName()} - Portal Locate", Timings::$worldLoad);
	}

	public function onDataPacket(DataPacketSendEvent $event) : void {
		foreach($event->getPackets() as $pk) {
			if($pk instanceof StartGamePacket) {
				foreach($event->getTargets() as $session) {
					/** @var DimensionalWorld $world */
					$world = $session->getPlayer()->getWorld();
					$settings = $pk->levelSettings->spawnSettings;
					if($world->getOverworld() === $world)
						$dimension = DimensionIds::OVERWORLD;
					elseif($world->getNether() === $world)
						$dimension = DimensionIds::NETHER;
					elseif($world->getEnd() === $world)
						$dimension = DimensionIds::THE_END;
					else
						return; // players can still go to non-dimension worlds
					$pk->levelSettings->spawnSettings = new SpawnSettings($settings->getBiomeType(), $settings->getBiomeName(), $dimension);
				}
			}
		}
	}

	public function onWorldLoad(WorldLoadEvent $event) {
		/** @var DimensionalWorld $world */
		$world = $event->getWorld();
		$provider = $world->getProvider();
		if(!$provider instanceof DimensionLevelDBProvider)
			return;

		self::initTimings($world);

		self::$syncPortalsLoadTimer[$world->getId()]->startTiming();

		$levelDB = $provider->getDatabase();
		$buffer = $levelDB->get('portals');

		if($buffer === false) {
			self::$syncPortalsLoadTimer[$world->getId()]->stopTiming();
			return;
		}

		$treeRoot = (new LittleEndianNbtSerializer())->read($buffer);
		$portalRecords = $treeRoot->mustGetCompoundTag()->getCompoundTag('data')?->getListTag('PortalRecords');
		var_dump($portalRecords?->getAllValues());
		if($portalRecords === null) {
			self::$syncPortalsLoadTimer[$world->getId()]->stopTiming();
			return;
		}
		/** @var CompoundTag $record */
		foreach($portalRecords as $record) {
			NetherPortalMap::getInstance()->addPortal($world, NetherPortalData::fromCompoundTag($record));
		}

		self::$syncPortalsLoadTimer[$world->getId()]->stopTiming();
	}

	public function onWorldSave(WorldSaveEvent $event) {
		/** @var DimensionalWorld $world */
		$world = $event->getWorld();
		$provider = $world->getProvider();
		if(!$provider instanceof DimensionLevelDBProvider or $world->getOverworld() !== $world)
			return;

		self::$syncPortalsSaveTimer[$world->getId()]->startTiming();

		$portalData = NetherPortalMap::getInstance()->getPortals($world->getOverworld());
		$portalData = array_merge($portalData, NetherPortalMap::getInstance()->getPortals($world->getNether()));
		$portalData = array_merge($portalData, NetherPortalMap::getInstance()->getPortals($world->getEnd()));
		$levelDB = $provider->getDatabase();
		$levelDB->put('portals',
			(new LittleEndianNbtSerializer())->write(new TreeRoot(
				CompoundTag::create()->setTag('data',
					CompoundTag::create()->setTag('PortalRecords', new ListTag(
						array_map(function(NetherPortalData $data) {
							return $data->getCompoundTag();
						}, $portalData),
						NBT::TAG_Compound
					))
				)
			))
		);
		self::$syncPortalsSaveTimer[$world->getId()]->stopTiming();
	}

	public function onChunkLoad(ChunkLoadEvent $event) {
		/** @var DimensionalWorld $world */
		$world = $event->getWorld();

		self::initTimings($world);

		self::$syncPortalLocateTimer[$world->getId()]->startTiming();

		$chunk = $event->getChunk();

		$highestBlock = 0;
		for($x = 0; $x < SubChunk::EDGE_LENGTH; ++$x) {
			for($z = 0; $z < SubChunk::EDGE_LENGTH; ++$z) {
				$highestBlock = max($chunk->getHighestBlockAt($x, $z) ?? 0, $highestBlock); // save the highest block in the chunk
			}
		}

		foreach($chunk->getSubChunks() as $yOffset => $subChunk) {
			for($x = 0; $x < SubChunk::EDGE_LENGTH; ++$x) {
				for($z = 0; $z < SubChunk::EDGE_LENGTH; ++$z) {
					for($y = 0; $y < SubChunk::EDGE_LENGTH; ++$y) {
						if($y | ($yOffset << SubChunk::COORD_BIT_SIZE) > $highestBlock)
							break; // no need to check further because there are no blocks above

						$block = $subChunk->getFullBlock($x, $y, $z);
						$block = BlockFactory::getInstance()->fromFullBlock($block);
						if($block instanceof Portal) {
							$block->position(
								$world,
								($event->getChunkX() << SubChunk::COORD_BIT_SIZE) + $x,
								($yOffset << SubChunk::COORD_BIT_SIZE) + $y,
								($event->getChunkZ() << SubChunk::COORD_BIT_SIZE) + $z
							);

							$side = $block->getSide(Facing::DOWN, $i = 1);
							while($side instanceof Portal) {
								$side = $block->getSide(Facing::DOWN, $i++);
							}
							/** @var Portal $block */
							$block = $side->getSide(Facing::UP);

							$axis = $block->getAxis();
							$direction = $axis << 1; // don't bother with positive bit since we only want to look in the negative direction

							$side = $block->getSide($direction, $i = 1);
							while($side instanceof Portal) {
								$side = $block->getSide($direction, $i++);
							}

							$direction = Facing::opposite($direction);
							$positionToBeMapped = $side->getSide($direction)->getPosition()->floor(); // TODO: debug

							$side = $block->getSide($direction, $j = 1);
							while($side instanceof Portal) {
								$side = $block->getSide($direction, $j++);
							}

							NetherPortalMap::getInstance()->addPortal($world, new NetherPortalData($i + $j, $axis, $world->getDimensionId(), (int) $positionToBeMapped->x, (int) $positionToBeMapped->y, (int) $positionToBeMapped->z));

							$y = ($y + 3) & SubChunk::COORD_MASK;
						}
					}
				}
			}
		}
		self::$syncPortalLocateTimer[$world->getId()]->stopTiming();
	}

	public function onReceivePacket(DataPacketReceiveEvent $event) : void {
		$pk = $event->getPacket();
		if($pk instanceof PlayerActionPacket and $pk->action === PlayerAction::DIMENSION_CHANGE_ACK) {
			$player = $event->getOrigin()->getPlayer();
			$player->getNetworkSession()->sendDataPacket(PlayStatusPacket::create(PlayStatusPacket::PLAYER_SPAWN));

			if(!in_array($player->getId(), Main::getTeleporting()))
				return;

			$this->plugin->getLogger()->debug("Valid Dimension ACK received");

			$this->plugin->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(function() use($player) : void {
				if($player->getWorld()->getBlock($player->getPosition()) instanceof NetherPortal) {
					$this->plugin->getLogger()->debug("Player has not left portal after teleport");
					return;
				}
				Main::removeTeleportingId($player->getId());
				throw new CancelTaskException();
			}), 20 * 10, 20 * 5);
		}
	}

	public function onRespawn(PlayerRespawnEvent $event) : void {
		$player = $event->getPlayer();
		if(!$player->isAlive()) {
			/** @var DimensionalWorld $world */
			$world = $event->getRespawnPosition()->getWorld();
			$respawn = $event->getRespawnPosition();
			$respawn->world = $world->getOverworld();
			$event->setRespawnPosition($respawn);
			Main::removeTeleportingId($player->getId());
		}
	}

	public function onSleep(PlayerBedEnterEvent $event) : void {
		$pos = $event->getBed()->getPosition();
		/** @var DimensionalWorld $world */
		$world = $pos->getWorld();
		if($world->getOverworld() !== $world) {
			$event->cancel();
			// TODO: blow up bed
		}
	}

	public function onBlockUpdate(BlockUpdateEvent $event) : void {
		$block = $event->getBlock();
		if(!$block instanceof Fire)
			return;
		/** @var DimensionalWorld $world */
		$world = $block->getPosition()->getWorld();
		if($world->getEnd() === $world){
			return;
		}
		foreach($block->getAllSides() as $obsidian){
			if(!$obsidian->isSameType(VanillaBlocks::OBSIDIAN())){
				continue;
			}
			$direction = match(true) {
				$this->testDirectionForObsidian(Facing::NORTH, $block->getPosition(), $widthA) and
				$this->testDirectionForObsidian(Facing::SOUTH, $block->getPosition(), $widthB) => Facing::NORTH,
				$this->testDirectionForObsidian(Facing::EAST, $block->getPosition(), $widthA) and
				$this->testDirectionForObsidian(Facing::WEST, $block->getPosition(), $widthB) => Facing::EAST,
				default => null
			};
			$totalWidth = $widthA + $widthB - 1;
			if($totalWidth < 2){
				return; // portal cannot be made
			}

			if(!$this->testDirectionForObsidian(Facing::UP, $block->getPosition(), $heightA) or
				!$this->testDirectionForObsidian(Facing::DOWN, $block->getPosition(), $heightB)){
				return; // portal cannot be made
			}
			$totalHeight = $heightA + $heightB - 1;
			if($totalHeight < 3){
				return; // portal cannot be made
			}

			$this->testDirectionForObsidian($direction, $block->getPosition(), $horizblocks);
			$start = $block->getPosition()->getSide($direction, $horizblocks - 1);
			$this->testDirectionForObsidian(Facing::UP, $block->getPosition(), $vertblocks);
			$start = Position::fromObject($start->add(0, $vertblocks - 1, 0), $start->getWorld());

			for($j = 0; $j < $totalHeight; ++$j){
				for($k = 0; $k < $totalWidth; ++$k){
					if($direction == Facing::NORTH){
						$start->getWorld()->setBlock($start->add(0, -$j, $k), (new Portal())->setAxis(Axis::Z), false);
					}else{
						$start->getWorld()->setBlock($start->add(-$k, -$j, 0), (new Portal())->setAxis(Axis::X), false);
					}
				}
			}
			return;
		}
	}

	private function testDirectionForObsidian(int $direction, Position $start, ?int &$distance = 0) : bool {
		$distance ??= 0;
		for($i = 1; $i <= 23; ++$i){
			$testPos = $start->getSide($direction, $i);
			if($testPos->getWorld()->getBlock($testPos, true, false) instanceof Obsidian){
				$distance = $i;
				return true;
			}elseif(!$testPos->getWorld()->getBlock($testPos, true, false) instanceof Air){
				return false;
			}
		}
		return false;
	}
}