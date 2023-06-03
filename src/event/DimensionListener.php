<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\event;

use jasonw4331\NativeDimensions\Main;
use jasonw4331\NativeDimensions\world\DimensionalWorld;
use pocketmine\block\Air;
use pocketmine\block\Bed;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Fire;
use pocketmine\block\NetherPortal;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBedEnterEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\SetTimePacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\network\mcpe\protocol\types\SpawnSettings;
use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\Explosion;
use pocketmine\world\Position;
use function in_array;

class DimensionListener implements Listener{
	/** @var Main */
	protected $plugin;

	public function __construct(Main $plugin){
		$plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
		$this->plugin = $plugin;
	}

	public function onDataPacket(DataPacketSendEvent $event) : void{
		foreach($event->getPackets() as $pk){
			// fix dimension ids
			if($pk instanceof StartGamePacket){
				foreach($event->getTargets() as $session){
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
			// sync time across dimensions
			if($pk instanceof SetTimePacket) {
				$session = array_shift($event->getTargets());
				$world = $session->getPlayer()->getWorld();
				// check time to prevent feedback loop
				if($world->getTime() !== $pk->time) {
					$event->cancel();
				}
				if($world instanceof DimensionalWorld and $world->getTime() !== $pk->time) {
					$world->getOverworld()->setTime($pk->time);
					$world->getEnd()->setTime($pk->time);
					$world->getNether()->setTime($pk->time);
				}
			}
		}
	}

	public function onReceivePacket(DataPacketReceiveEvent $event) : void{
		$pk = $event->getPacket();
		if($pk instanceof PlayerActionPacket && $pk->action === PlayerAction::DIMENSION_CHANGE_ACK){
			$player = $event->getOrigin()->getPlayer();
			$player->getNetworkSession()->sendDataPacket(PlayStatusPacket::create(PlayStatusPacket::PLAYER_SPAWN));

			if(!in_array($player->getId(), Main::getTeleporting(), true))
				return;

			$this->plugin->getLogger()->debug("Valid Dimension ACK received");

			$this->plugin->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(function() use ($player) : void{
				if($player->getWorld()->getBlock($player->getPosition()) instanceof NetherPortal){
					$this->plugin->getLogger()->debug("Player has not left portal after teleport");
					return;
				}
				Main::removeTeleportingId($player->getId());
				throw new CancelTaskException();
			}), 20 * 10, 20 * 5);
		}
	}

	public function onRespawn(PlayerRespawnEvent $event) : void{
		$player = $event->getPlayer();
		if(!$player->isAlive()){
			/** @var DimensionalWorld $world */
			$world = $event->getRespawnPosition()->getWorld();
			$respawn = $event->getRespawnPosition();
			$respawn->world = $world->getOverworld();
			$event->setRespawnPosition($respawn);
			Main::removeTeleportingId($player->getId());
		}
	}

	public function onSleep(PlayerBedEnterEvent $event) : void{
		$bed = $event->getBed();
		if(!$bed instanceof Bed)
			return;
		$pos = $bed->isHeadPart() ? $bed->getPosition() : $bed->getOtherHalf()->getPosition();
		/** @var DimensionalWorld $world */
		$world = $pos->getWorld();
		if($world->getOverworld() !== $world){
			$event->cancel();
			$explosion = new Explosion($pos, 5, $event->getBed());
			$explosion->explodeA();
			$explosion->explodeB();
		}
	}

	public function onBlockUpdate(BlockUpdateEvent $event) : void{
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
			$direction = match (true) {
				$this->testDirectionForObsidian(Facing::NORTH, $block->getPosition(), $widthA) &&
				$this->testDirectionForObsidian(Facing::SOUTH, $block->getPosition(), $widthB) => Facing::NORTH,
				$this->testDirectionForObsidian(Facing::EAST, $block->getPosition(), $widthA) &&
				$this->testDirectionForObsidian(Facing::WEST, $block->getPosition(), $widthB) => Facing::EAST,
				default => null
			};
			$totalWidth = $widthA + $widthB - 1;
			if($totalWidth < 2){
				return; // portal cannot be made
			}

			if(!$this->testDirectionForObsidian(Facing::UP, $block->getPosition(), $heightA) ||
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
						$start->getWorld()->setBlock($start->add(0, -$j, $k), VanillaBlocks::NETHER_PORTAL()->setAxis(Axis::Z), false);
					}else{
						$start->getWorld()->setBlock($start->add(-$k, -$j, 0), VanillaBlocks::NETHER_PORTAL()->setAxis(Axis::X), false);
					}
				}
			}
			return;
		}
	}

	public function onBlockBreak(BlockBreakEvent $event) : void {
		$block = $event->getBlock();
		if($block->getTypeId() !== BlockTypeIds::OBSIDIAN && !$block instanceof NetherPortal)
			return;

		foreach($block->getAllSides() as $portalBlock){
			if($portalBlock->getTypeId() !== BlockTypeIds::NETHER_PORTAL)
				continue;
			$block->getPosition()->getWorld()->useBreakOn($portalBlock->getPosition(), createParticles: true);
		}
	}

	private function testDirectionForObsidian(int $direction, Position $start, ?int &$distance = 0) : bool{
		$distance ??= 0;
		for($i = 1; $i <= 23; ++$i){
			$testPos = $start->getSide($direction, $i);
			if($testPos->getWorld()->getBlock($testPos, true, false)->getTypeId() === BlockTypeIds::OBSIDIAN){
				$distance = $i;
				return true;
			}elseif(!$testPos->getWorld()->getBlock($testPos, true, false) instanceof Air){
				return false;
			}
		}
		return false;
	}
}
