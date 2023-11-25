<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions;

use jasonw4331\NativeDimensions\block\ExtraVanillaBlocks;
use jasonw4331\NativeDimensions\event\DimensionListener;
use jasonw4331\NativeDimensions\network\DimensionSpecificCompressor;
use jasonw4331\NativeDimensions\world\DimensionalWorld;
use jasonw4331\NativeDimensions\world\DimensionalWorldManager;
use jasonw4331\NativeDimensions\world\generator\ender\EnderGenerator;
use jasonw4331\NativeDimensions\world\generator\nether\NetherGenerator;
use jasonw4331\NativeDimensions\world\provider\DimensionalWorldProviderManager;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\block\BlockTypeNames as Ids;
use pocketmine\data\bedrock\block\convert\BlockStateWriter;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\network\mcpe\cache\ChunkCache;
use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\AsyncTask;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\light\LightUpdate;
use pocketmine\world\Position;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Filesystem\Path;
use function array_search;
use function count;
use function in_array;
use function mb_strtolower;
use function mt_rand;
use function spl_object_id;
use function str_contains;

class Main extends PluginBase{

	/** @var array<string, DimensionIds::*> */
	private array $applicable_worlds = [];

	/** @var Compressor[] */
	private array $known_compressors = [];

	private static self $instance;

	/** @var int[] $teleporting */
	protected static array $teleporting = [];

	public static function getInstance() : Main{
		return self::$instance;
	}

	public function onLoad() : void{
		self::$instance = $this;

		GeneratorManager::getInstance()->addGenerator(NetherGenerator::class, 'nether', fn() => null, true);
		GeneratorManager::getInstance()->addGenerator(EnderGenerator::class, 'ender', fn() => null, true);

		$config = $this->getConfig();
		if(count((array) $config->get('Portal Disabled Worlds', [])) === 0)
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
		(new ReflectionClass($server))->getProperty('worldManager')->setValue($server, new DimensionalWorldManager($server, Path::join($server->getDataPath(), "worlds"), new DimensionalWorldProviderManager()));

		if($this->getServer()->getWorldManager() instanceof DimensionalWorldManager)
			$this->getLogger()->debug("WorldManager Successfully swapped");

		foreach($worlds as $worldName)
			$server->getWorldManager()->loadWorld($worldName);
	}

	public function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvent(PlayerLoginEvent::class, function(PlayerLoginEvent $event) : void{
			$this->registerKnownCompressor($event->getPlayer()->getNetworkSession()->getCompressor());
		}, EventPriority::LOWEST, $this);
		$this->getServer()->getPluginManager()->registerEvent(WorldLoadEvent::class, function(WorldLoadEvent $event) : void{
			/** @var DimensionalWorld $world */
			$world = $event->getWorld();
			$this->registerHackToWorldIfApplicable($world);
		}, EventPriority::LOWEST, $this);

		// register already-registered values
		$this->registerKnownCompressor(ZlibCompressor::getInstance());
		/** @var DimensionalWorld $world */
		foreach($this->getServer()->getWorldManager()->getWorlds() as $world){
			if($world->getDimensionId() !== DimensionIds::OVERWORLD)
				$this->applyToWorld($world->getFolderName(), $world->getDimensionId());
		}

		new DimensionListener($this);

		self::registerBlocks();

		$this->getServer()->getAsyncPool()->addWorkerStartHook(function(int $worker) : void{
			$this->getServer()->getAsyncPool()->submitTaskToWorker(new class extends AsyncTask{
				public function onRun() : void{
					Main::registerBlocks();
				}
			}, $worker);
		});
	}

	public static function registerBlocks() : void{
		$namespace = mb_strtolower(self::getInstance()->getName());

		// Custom End Portal Registration
		RuntimeBlockStateRegistry::getInstance()->register(ExtraVanillaBlocks::END_PORTAL());
		GlobalBlockStateHandlers::getSerializer()->mapSimple(ExtraVanillaBlocks::END_PORTAL(), Ids::END_PORTAL);
		GlobalBlockStateHandlers::getDeserializer()->mapSimple(Ids::END_PORTAL, fn() => ExtraVanillaBlocks::END_PORTAL());

		$parser = StringToItemParser::getInstance();
		$parser->override('end_portal', fn() => ExtraVanillaBlocks::END_PORTAL()->asItem());
		$parser->registerBlock("$namespace:end_portal", fn() => ExtraVanillaBlocks::END_PORTAL());

		// Hack to override nether portal registration
		$block = ExtraVanillaBlocks::END_PORTAL();
		$refClass = new ReflectionClass(RuntimeBlockStateRegistry::getInstance());
		$refProp = $refClass->getProperty('typeIndex');
		$oldValue = $refProp->getValue();
		$oldValue[$block->getTypeId()] = clone $block;
		$refProp->setValue(RuntimeBlockStateRegistry::getInstance(), $oldValue);

		foreach($block->generateStatePermutations() as $v){
			$index = $v->getStateId();
			$fullId = $block->getStateId();
			if($index !== $fullId){
				throw new AssumptionFailedError("Cannot fill static arrays for an invalid blockstate");
			}else{
				$refProp = $refClass->getProperty('fullList');
				$oldValue = $refProp->getValue();
				$oldValue[$index] = $v;
				$refProp->setValue(RuntimeBlockStateRegistry::getInstance(), $oldValue);
				$refProp = $refClass->getProperty('blastResistance');
				$oldValue = $refProp->getValue();
				$oldValue[$index] = $v->getBreakInfo()->getBlastResistance();
				$refProp->setValue(RuntimeBlockStateRegistry::getInstance(), $oldValue);
				$refProp = $refClass->getProperty('light');
				$oldValue = $refProp->getValue();
				$oldValue[$index] = $v->getLightLevel();
				$refProp->setValue(RuntimeBlockStateRegistry::getInstance(), $oldValue);
				$refProp = $refClass->getProperty('lightFilter');
				$oldValue = $refProp->getValue();
				$oldValue[$index] = min(15, $v->getLightFilter() + LightUpdate::BASE_LIGHT_FILTER);
				$refProp->setValue(RuntimeBlockStateRegistry::getInstance(), $oldValue);
				if($v->blocksDirectSkyLight()){
					$refProp = $refClass->getProperty('blocksDirectSkyLight');
					$oldValue = $refProp->getValue();
					$oldValue[$index] = true;
					$refProp->setValue(RuntimeBlockStateRegistry::getInstance(), $oldValue);
				}
			}
		}

		$refClass = new ReflectionClass(GlobalBlockStateHandlers::getSerializer());
		$refProp = $refClass->getProperty('serializers');
		$oldValue = $refProp->getValue();
		$oldValue[$block->getTypeId()] = BlockStateWriter::create(Ids::PORTAL);
		$refProp->setValue(GlobalBlockStateHandlers::getSerializer(), $oldValue);

		$refClass = new ReflectionClass(GlobalBlockStateHandlers::getDeserializer());
		$refProp = $refClass->getProperty('deserializeFuncs');
		$oldValue = $refProp->getValue();
		$oldValue[Ids::PORTAL] = fn() => ExtraVanillaBlocks::END_PORTAL();
		$refProp->setValue(GlobalBlockStateHandlers::getDeserializer(), $oldValue);

		$refClass = new ReflectionClass(VanillaBlocks::class);
		$oldValue = $refClass->getStaticPropertyValue('members', []);
		$oldValue[mb_strtoupper($block->getName())] = clone $block;
		$refClass->setStaticPropertyValue('members', $oldValue);

		$parser = StringToItemParser::getInstance();
		$parser->override('nether_portal', fn() => ExtraVanillaBlocks::NETHER_PORTAL()->asItem());
		$parser->registerBlock("$namespace:nether_portal", fn() => ExtraVanillaBlocks::NETHER_PORTAL());
	}

	private function registerKnownCompressor(Compressor $compressor) : void{
		if(isset($this->known_compressors[$id = spl_object_id($compressor)])){
			return;
		}

		$this->known_compressors[$id] = $compressor;
		/** @phpstan-var DimensionalWorld $world */
		foreach($this->getServer()->getWorldManager()->getWorlds() as $world){
			$this->registerHackToWorldIfApplicable($world);
		}
	}

	private function registerHackToWorldIfApplicable(DimensionalWorld $world) : bool{
		if(!isset($this->applicable_worlds[$world_name = $world->getFolderName()])){
			return false;
		}

		$dimension_id = $this->applicable_worlds[$world_name];
		$this->registerHackToWorld($world, $dimension_id);
		return true;
	}

	/**
	 * @param DimensionalWorld $world
	 * @param DimensionIds::* $dimension_id
	 */
	private function registerHackToWorld(DimensionalWorld $world, int $dimension_id) : void{
		/** @see ChunkCache::$compressor */
		static $_chunk_cache_compressor = null;
		$_chunk_cache_compressor ??= new ReflectionProperty(ChunkCache::class, "compressor");

		foreach($this->known_compressors as $compressor){
			$chunk_cache = ChunkCache::getInstance($world, $compressor);
			$compressor = $_chunk_cache_compressor->getValue($chunk_cache);
			if(!($compressor instanceof DimensionSpecificCompressor)){
				$_chunk_cache_compressor->setValue($chunk_cache, DimensionSpecificCompressor::fromDimensionId($compressor, $dimension_id));
			}
		}
	}

	/**
	 * @param string $world_folder_name
	 * @param DimensionIds::* $dimension_id
	 */
	public function applyToWorld(string $world_folder_name, int $dimension_id) : void{
		$this->applicable_worlds[$world_folder_name] = $dimension_id;
		/** @var DimensionalWorld $world */
		$world = $this->getServer()->getWorldManager()->getWorldByName($world_folder_name);
		if($world !== null){
			$this->registerHackToWorldIfApplicable($world);
		}
	}

	public function unapplyFromWorld(string $world_folder_name) : void{
		unset($this->applicable_worlds[$world_folder_name]);
	}

	public static function makeNetherPortal(Position $position) : bool{
		if(!$position->isValid())
			return false;
		$world = $position->getWorld();
		$portalBlock = VanillaBlocks::NETHER_PORTAL();
		$frameBlock = VanillaBlocks::OBSIDIAN();
		$air = VanillaBlocks::AIR();
		if(mt_rand(0, 1) === 0){
			self::getInstance()->getLogger()->debug('Generating Z Axis Nether Portal');
			$portalBlock->setAxis(Axis::Z);
			// portal blocks
			$world->setBlock($position, $portalBlock, false);
			$world->setBlock($position->getSide(Facing::UP), $portalBlock, false);
			$world->setBlock($position->getSide(Facing::UP, 2), $portalBlock, false);
			$world->setBlock($position->getSide(Facing::NORTH), $portalBlock, false);
			$world->setBlock($position->getSide(Facing::NORTH)->getSide(Facing::UP), $portalBlock, false);
			$world->setBlock($position->getSide(Facing::NORTH)->getSide(Facing::UP, 2), $portalBlock, false);
			// obsidian
			$world->setBlock($position->getSide(Facing::SOUTH), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::DOWN), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::UP), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::UP, 2), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::UP, 3), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::DOWN), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::DOWN)->getSide(Facing::NORTH), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::UP, 3), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::UP, 3)->getSide(Facing::NORTH), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::NORTH, 2), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::DOWN), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::UP), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::UP, 2), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::UP, 3), $frameBlock, false);
			// air
			$world->setBlock($position->getSide(Facing::EAST), $air, false);
			$world->setBlock($position->getSide(Facing::UP)->getSide(Facing::EAST), $air, false);
			$world->setBlock($position->getSide(Facing::UP, 2)->getSide(Facing::EAST), $air, false);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::EAST), $air, false);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP)->getSide(Facing::EAST), $air, false);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP, 2)->getSide(Facing::EAST), $air, false);
			$world->setBlock($position->getSide(Facing::WEST), $air, false);
			$world->setBlock($position->getSide(Facing::UP)->getSide(Facing::WEST), $air, false);
			$world->setBlock($position->getSide(Facing::UP, 2)->getSide(Facing::WEST), $air, false);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::WEST), $air, false);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP)->getSide(Facing::WEST), $air, false);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP, 2)->getSide(Facing::WEST), $air, false);
		}else{
			self::getInstance()->getLogger()->debug('Generating X Axis Nether Portal');
			$portalBlock->setAxis(Axis::X);
			// portal blocks
			$world->setBlock($position, $portalBlock, false);
			$world->setBlock($position->getSide(Facing::UP), $portalBlock, false);
			$world->setBlock($position->getSide(Facing::UP, 2), $portalBlock, false);
			$world->setBlock($position->getSide(Facing::EAST), $portalBlock, false);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP), $portalBlock, false);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP, 2), $portalBlock, false);
			// obsidian
			$world->setBlock($position->getSide(Facing::WEST), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::DOWN), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::UP), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::UP, 2), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::UP, 3), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::DOWN), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::DOWN)->getSide(Facing::EAST), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::UP, 3), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::UP, 3)->getSide(Facing::EAST), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::EAST, 2), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::DOWN), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::UP), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::UP, 2), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::UP, 3), $frameBlock, false);
			// air
			$world->setBlock($position->getSide(Facing::NORTH), $air, false);
			$world->setBlock($position->getSide(Facing::UP)->getSide(Facing::NORTH), $air, false);
			$world->setBlock($position->getSide(Facing::UP, 2)->getSide(Facing::NORTH), $air, false);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::NORTH), $air, false);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP)->getSide(Facing::NORTH), $air, false);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP, 2)->getSide(Facing::NORTH), $air, false);
			$world->setBlock($position->getSide(Facing::SOUTH), $air, false);
			$world->setBlock($position->getSide(Facing::UP)->getSide(Facing::SOUTH), $air, false);
			$world->setBlock($position->getSide(Facing::UP, 2)->getSide(Facing::SOUTH), $air, false);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::SOUTH), $air, false);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP)->getSide(Facing::SOUTH), $air, false);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP, 2)->getSide(Facing::SOUTH), $air, false);
		}
		// TODO: levelDB portal map
		return true;
	}

	public static function makeEndSpawn(DimensionalWorld $world) : bool{
		$world = $world->getEnd();
		$position = $world->getSpawnLocation();

		for($x = $position->x - 2; $x < $position->x + 3; ++$x){
			for($z = $position->z - 2; $z < $position->z + 3; ++$z){
				$world->setBlockAt($x, $position->y - 1, $z, VanillaBlocks::OBSIDIAN(), false);
				for($y = 0; $y < 3; ++$y)
					$world->setBlockAt($x, $position->y + $y, $z, VanillaBlocks::AIR(), false);
			}
		}
		return true;
	}

	public static function makeEndExit(DimensionalWorld $world) : void{
		$world = $world->getEnd();
		$position = new Position(0, 64, 0, $world);

		$endPortal = ExtraVanillaBlocks::END_PORTAL();
		$bedrock = VanillaBlocks::BEDROCK();
		$endStone = VanillaBlocks::END_STONE();
		$dragonEgg = VanillaBlocks::DRAGON_EGG();
		$torch = VanillaBlocks::TORCH();

		$world->setBlock($position->getSide(Facing::UP, 4), $dragonEgg, false);
		for($h = 3; $h > 0; --$h){
			$world->setBlock($position->getSide(Facing::UP, $h), $bedrock, false);
		}
		$world->setBlock($position, $bedrock, false);
		$world->setBlock($position->getSide(Facing::DOWN), $bedrock, false);
		$world->setBlock($position->getSide(Facing::DOWN, 2), $endStone, false);

		foreach(Facing::HORIZONTAL as $side){
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
	public static function getTeleporting() : array{
		return self::$teleporting;
	}

	public static function addTeleportingId(int $id) : void{
		if(!in_array($id, self::$teleporting, true))
			self::$teleporting[] = $id;
	}

	public static function removeTeleportingId(int $id) : void{
		$key = array_search($id, self::$teleporting, true);
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
