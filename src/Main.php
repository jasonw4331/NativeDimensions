<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions;

use jasonwynn10\NativeDimensions\block\EndPortal;
use jasonwynn10\NativeDimensions\block\Fire;
use jasonwynn10\NativeDimensions\block\Obsidian;
use jasonwynn10\NativeDimensions\block\Portal;
use jasonwynn10\NativeDimensions\event\DimensionListener;
use jasonwynn10\NativeDimensions\network\DimensionSpecificCompressor;
use jasonwynn10\NativeDimensions\world\data\NetherPortalData;
use jasonwynn10\NativeDimensions\world\data\NetherPortalMap;
use jasonwynn10\NativeDimensions\world\DimensionalWorld;
use jasonwynn10\NativeDimensions\world\DimensionalWorldManager;
use jasonwynn10\NativeDimensions\world\generator\ender\EnderGenerator;
use jasonwynn10\NativeDimensions\world\generator\nether\NetherGenerator;
use pocketmine\block\BlockFactory;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\network\mcpe\cache\ChunkCache;
use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\plugin\PluginBase;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\Position;
use Webmozart\PathUtil\Path;

class Main extends PluginBase {

	/** @var Compressor[] */
	private array $known_compressors = [];

	private static self $instance;

	/** @var int[] $teleporting */
	protected static $teleporting = [];

	public static function getInstance() : Main {
		return self::$instance;
	}

	public function onLoad() : void {
		self::$instance = $this;

		GeneratorManager::getInstance()->addGenerator(NetherGenerator::class, 'nether', fn() => null, true);
		GeneratorManager::getInstance()->addGenerator(EnderGenerator::class, 'ender', fn() => null, true);

		$config = $this->getConfig();
		if(count($config->get('Portal Disabled Worlds', [])) === 0)
			$config->set('Portal Disabled Worlds', []);

		$this->getLogger()->debug("Unloading Worlds");
		$server = $this->getServer();
		$oldManager = $server->getWorldManager();
		$worlds = [];
		foreach($oldManager->getWorlds() as $world){
			$oldManager->unloadWorld($world, true);
			$worlds[] = $world->getFolderName();
		}
		$this->getLogger()->debug("Worlds Successfully Unloaded");

		// replace default world manager with one that supports dimensions
		$ref = new \ReflectionClass($server);
		$prop = $ref->getProperty('worldManager');
		$prop->setAccessible(true);
		$prop->setValue($server, new DimensionalWorldManager($server, Path::join($server->getDataPath(), "worlds")));

		if($this->getServer()->getWorldManager() instanceof DimensionalWorldManager)
			$this->getLogger()->debug("WorldManager Successfully swapped");

		foreach($worlds as $worldName)
			$server->getWorldManager()->loadWorld($worldName);
	}

	public function onEnable() : void {
		$this->getServer()->getPluginManager()->registerEvent(PlayerLoginEvent::class, function(PlayerLoginEvent $event) : void{
			$this->registerKnownCompressor($event->getPlayer()->getNetworkSession()->getCompressor());
		}, EventPriority::LOWEST, $this);
		$this->getServer()->getPluginManager()->registerEvent(WorldLoadEvent::class, function(WorldLoadEvent $event) : void{
			/** @var DimensionalWorld $world */
			$world = $event->getWorld();
			$this->registerHackToWorld($world);
		}, EventPriority::LOWEST, $this);

		// register already-registered values
		$this->registerKnownCompressor(ZlibCompressor::getInstance());
		/** @var DimensionalWorld $world */
		foreach($this->getServer()->getWorldManager()->getWorlds() as $world){
			$this->registerHackToWorld($world);
		}

		new DimensionListener($this);
		$factory = BlockFactory::getInstance();
		$parser = StringToItemParser::getInstance();
		foreach([
			new EndPortal(),
			new Fire(),
			new Obsidian(),
			new Portal()
		] as $block) {
			$factory->register($block, true);
			$parser->override($block->getName(), fn(string $input) => $block->asItem());
		}
	}

	private function registerKnownCompressor(Compressor $compressor) : void{
		if(isset($this->known_compressors[$id = spl_object_id($compressor)])){
			return;
		}

		$this->known_compressors[$id] = $compressor;
		/** @var DimensionalWorld $world */
		foreach($this->getServer()->getWorldManager()->getWorlds() as $world){
			$this->registerHackToWorld($world);
		}
	}

	private function registerHackToWorld(DimensionalWorld $world) : void{
		if($world->getOverworld() === $world)
			return;

		static $_chunk_cache_instances = null;
		if($_chunk_cache_instances === null){
			$_chunk_cache_instances = new \ReflectionProperty(ChunkCache::class, "instances");
			$_chunk_cache_instances->setAccessible(true);
		}

		static $_chunk_cache_compressor = null;
		if($_chunk_cache_compressor === null){
			$_chunk_cache_compressor = new \ReflectionProperty(ChunkCache::class, "compressor");
			$_chunk_cache_compressor->setAccessible(true);
		}

		foreach($this->known_compressors as $compressor){
			$chunk_cache = ChunkCache::getInstance($world, $compressor);
			$compressor = $_chunk_cache_compressor->getValue($chunk_cache);
			if($compressor instanceof DimensionSpecificCompressor){
				continue;
			}

			$_chunk_cache_compressor->setValue(ChunkCache::getInstance($world, $compressor), new DimensionSpecificCompressor($compressor));
		}
	}

	public static function makeNetherPortal(Position $position, int $axis) : bool {
		if(!$position->isValid())
			throw new \InvalidArgumentException("Position does not have a valid world");

		if($axis !== Axis::X && $axis !== Axis::Z)
			throw new \InvalidArgumentException("Axis must be X or Z only");

		$portalBlock = (new Portal())->setAxis($axis);

		/** @var DimensionalWorld $world */
		$world = $position->world;
		if($axis === Axis::Z){
			self::getInstance()->getLogger()->debug('Generating Z Axis Nether Portal');
			// portal blocks
			$world->setBlock($position, $portalBlock, true);
			$world->setBlock($position->getSide(Facing::UP), $portalBlock, true);
			$world->setBlock($position->getSide(Facing::UP, 2), $portalBlock, true);
			$world->setBlock($position->getSide(Facing::NORTH), $portalBlock, true);
			$world->setBlock($position->getSide(Facing::NORTH)->getSide(Facing::UP), $portalBlock, true);
			$world->setBlock($position->getSide(Facing::NORTH)->getSide(Facing::UP, 2), $portalBlock, true);
			// obsidian
			$world->setBlock($position->getSide(Facing::SOUTH), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::DOWN), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::UP), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::UP, 2), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::UP, 3), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::DOWN), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::DOWN)->getSide(Facing::NORTH), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::UP, 3), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::UP, 3)->getSide(Facing::NORTH), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::NORTH, 2), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::DOWN), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::UP), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::UP, 2), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::UP, 3), VanillaBlocks::OBSIDIAN(), true);
			// air
			$world->setBlock($position->getSide(Facing::EAST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::UP)->getSide(Facing::EAST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::UP, 2)->getSide(Facing::EAST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::EAST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP)->getSide(Facing::EAST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP, 2)->getSide(Facing::EAST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::WEST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::UP)->getSide(Facing::WEST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::UP, 2)->getSide(Facing::WEST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::WEST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP)->getSide(Facing::WEST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP, 2)->getSide(Facing::WEST), VanillaBlocks::AIR(), true);
		}else{
			self::getInstance()->getLogger()->debug('Generating X Axis Nether Portal');
			// portal blocks
			$world->setBlock($position, $portalBlock, true);
			$world->setBlock($position->getSide(Facing::UP), $portalBlock, true);
			$world->setBlock($position->getSide(Facing::UP, 2), $portalBlock, true);
			$world->setBlock($position->getSide(Facing::EAST), $portalBlock, true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP), $portalBlock, true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP, 2), $portalBlock, true);
			// obsidian
			$world->setBlock($position->getSide(Facing::WEST), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::DOWN), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::UP), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::UP, 2), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::UP, 3), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::DOWN), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::DOWN)->getSide(Facing::EAST), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::UP, 3), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::UP, 3)->getSide(Facing::EAST), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::EAST, 2), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::DOWN), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::UP), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::UP, 2), VanillaBlocks::OBSIDIAN(), true);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::UP, 3), VanillaBlocks::OBSIDIAN(), true);
			// air
			$world->setBlock($position->getSide(Facing::NORTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::UP)->getSide(Facing::NORTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::UP, 2)->getSide(Facing::NORTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::NORTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP)->getSide(Facing::NORTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP, 2)->getSide(Facing::NORTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::SOUTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::UP)->getSide(Facing::SOUTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::UP, 2)->getSide(Facing::SOUTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::SOUTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP)->getSide(Facing::SOUTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP, 2)->getSide(Facing::SOUTH), VanillaBlocks::AIR(), true);
		}
		NetherPortalMap::getInstance()->addPortal($world, new NetherPortalData(2, $axis, $world->getDimensionId(), (int) floor($position->x), (int) floor($position->y), (int) floor($position->z)));
		return true;
	}

	public static function makeEndSpawn(DimensionalWorld $world) : bool {
		$world = $world->getEnd();
		$position = $world->getSpawnLocation();

		for($x = $position->x - 2; $x < $position->x + 3; ++$x) {
			for($z = $position->z - 2; $z < $position->z + 3; ++$z) {
				$world->setBlockAt($x, $position->y - 1, $z, VanillaBlocks::OBSIDIAN(), false);
				for($y = 0; $y < 3; ++$y)
					$world->setBlockAt($x, $position->y + $y, $z, VanillaBlocks::AIR(), false);
			}
		}
		return true;
	}

	public static function makeEndExit(DimensionalWorld $world) : void {
		$world = $world->getEnd();
		$position = new Position(0, 64, 0, $world);

		$endPortal = new EndPortal();
		$bedrock = VanillaBlocks::BEDROCK();
		$endStone = VanillaBlocks::END_STONE();
		$dragonEgg = VanillaBlocks::DRAGON_EGG();
		$torch = VanillaBlocks::TORCH();

		$world->setBlock($position->getSide(Facing::UP, 4), $dragonEgg, false);
		for($h = 3; $h > 0; --$h) {
			$world->setBlock($position->getSide(Facing::UP, $h), $bedrock, false);
		}
		$world->setBlock($position, $bedrock, false);
		$world->setBlock($position->getSide(Facing::DOWN), $bedrock, false);
		$world->setBlock($position->getSide(Facing::DOWN, 2), $endStone, false);

		foreach(Facing::HORIZONTAL as $side) {
			$world->setBlock($position->getSide(Facing::UP, 2)->getSide($side), $torch->setFacing($side), false);

			$world->setBlock($position->getSide($side), $endPortal, false);
			$world->setBlock($position->getSide($side)->getSide(Facing::rotateY($side, true)), $endPortal, false);

			$world->setBlock($position->getSide(Facing::DOWN)->getSide($side), $bedrock, false);
			$world->setBlock($position->getSide(Facing::DOWN)->getSide($side)->getSide(Facing::rotateY($side, true)), $bedrock, false);

			$world->setBlock($position->getSide($side, 2)->getSide(Facing::rotateY($side, false), 2), $bedrock, false);
			$world->setBlock($position->getSide($side, 2)->getSide(Facing::rotateY($side, false)), $endPortal, false);
			$world->setBlock($position->getSide($side, 2), $endPortal, false);
			$world->setBlock($position->getSide($side, 2)->getSide(Facing::rotateY($side, true)), $endPortal, false);
			$world->setBlock($position->getSide($side, 2)->getSide(Facing::rotateY($side, true), 2), $bedrock, false);

			$world->setBlock($position->getSide(Facing::DOWN)->getSide($side, 2)->getSide(Facing::rotateY($side, false)), $bedrock, false);
			$world->setBlock($position->getSide(Facing::DOWN)->getSide($side, 2), $bedrock, false);
			$world->setBlock($position->getSide(Facing::DOWN)->getSide($side, 2)->getSide(Facing::rotateY($side, true)), $bedrock, false);

			$world->setBlock($position->getSide($side, 3)->getSide(Facing::rotateY($side, false)), $bedrock, false);
			$world->setBlock($position->getSide($side, 3), $bedrock, false);
			$world->setBlock($position->getSide($side, 3)->getSide(Facing::rotateY($side, true)), $bedrock, false);

			$world->setBlock($position->getSide(Facing::DOWN, 2)->getSide($side)->getSide(Facing::rotateY($side, false)), $endStone, false);
			$world->setBlock($position->getSide(Facing::DOWN, 2)->getSide($side), $endStone, false);
			$world->setBlock($position->getSide(Facing::DOWN, 2)->getSide($side)->getSide(Facing::rotateY($side, true)), $endStone, false);

			$world->setBlock($position->getSide(Facing::DOWN, 2)->getSide($side, 2)->getSide(Facing::rotateY($side, false), 2), $endStone, false);
			$world->setBlock($position->getSide(Facing::DOWN, 2)->getSide($side, 2)->getSide(Facing::rotateY($side, false)), $endStone, false);
			$world->setBlock($position->getSide(Facing::DOWN, 2)->getSide($side, 2), $endStone, false);
			$world->setBlock($position->getSide(Facing::DOWN, 2)->getSide($side, 2)->getSide(Facing::rotateY($side, true)), $endStone, false);
			$world->setBlock($position->getSide(Facing::DOWN, 2)->getSide($side, 2)->getSide(Facing::rotateY($side, true), 2), $endStone, false);

			$world->setBlock($position->getSide(Facing::DOWN, 2)->getSide($side, 3)->getSide(Facing::rotateY($side, false)), $endStone, false);
			$world->setBlock($position->getSide(Facing::DOWN, 2)->getSide($side, 3), $endStone, false);
			$world->setBlock($position->getSide(Facing::DOWN, 2)->getSide($side, 3)->getSide(Facing::rotateY($side, true)), $endStone, false);
		}
	}

	/**
	 * @return int[]
	 */
	public static function getTeleporting() : array {
		return self::$teleporting;
	}

	public static function addTeleportingId(int $id) : void {
		if(!in_array($id, self::$teleporting))
			self::$teleporting[] = $id;
	}

	public static function removeTeleportingId(int $id) : void{
		$key = array_search($id, self::$teleporting);
		if($key !== false){
			unset(self::$teleporting[$key]);
			self::$instance->getLogger()->debug("Player can use a portal again");
		}
	}

	public static function isPortalDisabled(DimensionalWorld $world) : bool{
		foreach(((array) self::$instance->getConfig()->get('Portal Disabled Worlds', [])) as $worldName){
			if(str_contains($world->getFolderName(), $worldName))
				return true;
		}
		return false;
	}
}