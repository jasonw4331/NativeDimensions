<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions;

use jasonwynn10\NativeDimensions\block\EndPortal;
use jasonwynn10\NativeDimensions\block\Fire;
use jasonwynn10\NativeDimensions\block\Obsidian;
use jasonwynn10\NativeDimensions\block\Portal;
use jasonwynn10\NativeDimensions\event\DimensionListener;
use jasonwynn10\NativeDimensions\network\DimensionSpecificCompressor;
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

		$this->getLogger()->debug("Unloading Worlds");
		$server = $this->getServer();
		$oldManager = $server->getWorldManager();
		foreach($oldManager->getWorlds() as $world)
			$oldManager->unloadWorld($world, true);
		$this->getLogger()->debug("Worlds Successfully Unloaded");

		// replace default world manager with one that supports dimensions
		$ref = new \ReflectionClass($server);
		$prop = $ref->getProperty('worldManager');
		$prop->setAccessible(true);
		$prop->setValue($server, new DimensionalWorldManager($server, Path::join($server->getDataPath(), "worlds")));

		if($this->getServer()->getWorldManager() instanceof DimensionalWorldManager)
			$this->getLogger()->debug("WorldManager Successfully swapped");
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
		$folder_name = $world->getFolderName();
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

	public static function makeNetherPortal(Position $position) : bool {
		if(!$position->isValid())
			return false;
		$world = $position->getWorld();
		if(mt_rand(0,1) === 0) {
			// portal blocks
			$world->setBlock($position, (new Portal())->setAxis(Axis::Z), true);
			$world->setBlock($position->getSide(Facing::UP), (new Portal())->setAxis(Axis::Z), true);
			$world->setBlock($position->getSide(Facing::UP, 2), (new Portal())->setAxis(Axis::Z), true);
			$world->setBlock($position->getSide(Facing::NORTH), (new Portal())->setAxis(Axis::Z), true);
			$world->setBlock($position->getSide(Facing::NORTH)->getSide(Facing::UP), (new Portal())->setAxis(Axis::Z), true);
			$world->setBlock($position->getSide(Facing::NORTH)->getSide(Facing::UP, 2), (new Portal())->setAxis(Axis::Z), true);
			// obsidian
			$world->setBlock($position->getSide(Facing::SOUTH), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::DOWN), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::UP), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::UP, 2), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::UP, 3), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::DOWN), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::DOWN)->getSide(Facing::NORTH), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::UP, 3), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::UP, 3)->getSide(Facing::NORTH), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::NORTH, 2), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::DOWN), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::UP), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::UP, 2), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::UP, 3), new Obsidian(), true);
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
			// portal blocks
			$world->setBlock($position, (new Portal())->setAxis(Axis::X), true);
			$world->setBlock($position->getSide(Facing::UP), (new Portal())->setAxis(Axis::X), true);
			$world->setBlock($position->getSide(Facing::UP, 2), (new Portal())->setAxis(Axis::X), true);
			$world->setBlock($position->getSide(Facing::EAST), (new Portal())->setAxis(Axis::X), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP), (new Portal())->setAxis(Axis::X), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP, 2), (new Portal())->setAxis(Axis::X), true);
			// obsidian
			$world->setBlock($position->getSide(Facing::WEST), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::DOWN), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::UP), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::UP, 2), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::UP, 3), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::DOWN), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::DOWN)->getSide(Facing::EAST), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::UP, 3), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::UP, 3)->getSide(Facing::EAST), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::EAST, 2), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::DOWN), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::UP), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::UP, 2), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::UP, 3), new Obsidian(), true);
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
		// TODO: levelDB portal map
		return true;
	}

	public static function makeEndSpawn(Position $center) : bool {
		if(!$center->isValid())
			return false;
		$world = $center->getWorld();
		$position = $center->floor();

		for($x = $position->x - 2; $x < $position->x + 3; ++$x) {
			for($z = $position->x - 2; $z < $position->x + 3; ++$z) {
				$world->setBlockAt($x, $position->y - 1, $z, VanillaBlocks::OBSIDIAN(), true);
				for($y = 0; $y < 3; ++$y)
					$world->setBlockAt($x, $position->y + $y, $z, VanillaBlocks::AIR(), true);
			}
		}
		return true;
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

	public static function removeTeleportingId(int $id) : void {
		$key = array_search($id, self::$teleporting);
		if($key !== false) {
			unset(self::$teleporting[$key]);
			self::$instance->getLogger()->debug("Player can use a portal again");
		}
	}
}