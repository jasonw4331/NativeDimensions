<?php
declare(strict_types=1);
namespace jasonwynn10\DimensionAPI;

use czechpmdevs\multiworld\generator\ender\EnderGenerator;
use jasonwynn10\DimensionAPI\block\EndPortal;
use jasonwynn10\DimensionAPI\block\EndPortalFrame;
use jasonwynn10\DimensionAPI\block\Obsidian;
use jasonwynn10\DimensionAPI\block\Portal;
use jasonwynn10\DimensionAPI\provider\AnvilDimension;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIds;
use pocketmine\event\level\LevelInitEvent;
use pocketmine\event\level\LevelLoadEvent;
use pocketmine\level\format\io\LevelProvider;
use pocketmine\level\format\io\LevelProviderManager;
use pocketmine\level\generator\Generator;
use pocketmine\level\generator\GeneratorManager;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;

class Main extends PluginBase {
	/** @var Main */
	private static $instance;

	/** @var string|null $endGenerator */
	protected $endGenerator = null;

	/**
	 * @return Main
	 */
	public static function getInstance() : Main {
		return self::$instance;
	}

	public function onLoad() {
		self::$instance = $this;
		LevelProviderManager::addProvider(AnvilDimension::class);
		//LevelProviderManager::addProvider(LevelDBDimensionProvider::class); // TODO
	}

	public function onEnable() {
		new DimensionListener($this);
		$this->endGenerator = GeneratorManager::getGenerator("ender");
		$multiworld = $this->getServer()->getPluginManager()->getPlugin("MultiWorld");
		if($multiworld !== null) {
			$this->endGenerator = EnderGenerator::class;
			BlockFactory::registerBlock(new EndPortalFrame(), true);
			BlockFactory::registerBlock(new EndPortal(), true);
		}
		BlockFactory::registerBlock(new Obsidian(), true);
		BlockFactory::registerBlock(new Portal(), true);
	}

	/**
	 * @param string $generator
	 *
	 * @return Main
	 */
	public function setEndGenerator(string $generator) : self {
		if(!($generator !== null and class_exists($generator, true) and is_subclass_of($generator, Generator::class))){
			$this->endGenerator = GeneratorManager::getGenerator("ender");
		}
		$this->endGenerator = EnderGenerator::class;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getEndGenerator() : ?string {
		return $this->endGenerator;
	}

	/**
	 * Generates a new level if it does not exist
	 *
	 * @param string $name
	 * @param int|null $seed
	 * @param string|null $generator Class name that extends pocketmine\level\generator\Generator
	 * @param array $options
	 * @param int $dimension defaults to nether id
	 *
	 * @return bool
	 * @throws \ReflectionException
	 */
	public function generateLevelDimension(string $name, int $seed = null, $generator = null, array $options = [], int $dimension = -1) : bool {
		if(trim($name) === "" or $this->getServer()->isLevelGenerated($name." dim".$dimension)) {
			return false;
		}

		$seed = $seed ?? random_int(INT32_MIN, INT32_MAX);

		if(!isset($options["preset"])){
			$options["preset"] = $this->getServer()->getConfigString("generator-settings", "");
		}

		if(!($generator !== null and class_exists($generator, true) and is_subclass_of($generator, Generator::class))){
			$generator = GeneratorManager::getGenerator("hell");
		}

		$providerClass = LevelProviderManager::getProviderByName("anvil_dimension");
		if($providerClass === null){
			throw new \InvalidStateException("Default world provider has not been registered");
		}

		$path = $this->getServer()->getDataPath() . "worlds/" . $name . "/";
		/** @var LevelProvider $providerClass */
		$providerClass::generate($path, $name, $seed, $generator, $options);

		/** @see LevelProvider::__construct() */
		$level = new Level($this->getServer(), $name." dim".$dimension, new $providerClass($path));
		$ref = new \ReflectionClass($this->getServer());
		$prop = $ref->getProperty("levels");
		$prop->setAccessible(true);
		$levels = $prop->getValue($this->getServer());
		$levels[$level->getId()] = $level;
		$prop->setValue($this->getServer(), $levels);
		$prop->setAccessible(false);

		(new LevelInitEvent($level))->call();

		(new LevelLoadEvent($level))->call();

		$this->getLogger()->notice($this->getServer()->getLanguage()->translateString("pocketmine.level.backgroundGeneration", [$name." dim".$dimension]));

		$spawnLocation = $level->getSpawnLocation();
		$centerX = $spawnLocation->getFloorX() >> 4;
		$centerZ = $spawnLocation->getFloorZ() >> 4;

		$order = [];

		for($X = -3; $X <= 3; ++$X){
			for($Z = -3; $Z <= 3; ++$Z){
				$distance = $X ** 2 + $Z ** 2;
				$chunkX = $X + $centerX;
				$chunkZ = $Z + $centerZ;
				$index = Level::chunkHash($chunkX, $chunkZ);
				$order[$index] = $distance;
			}
		}

		asort($order);

		foreach($order as $index => $distance){
			Level::getXZ($index, $chunkX, $chunkZ);
			$level->populateChunk($chunkX, $chunkZ, true);
		}

		return true;
	}

	/**
	 * @param Level $level
	 * @param int $dimension
	 *
	 * @return bool
	 */
	public static function dimensionExists(Level $level, int $dimension) : bool {
		return AnvilDimension::isValid($level->getProvider()->getPath(), $dimension); // TODO: levelDB provider
	}

	/**
	 * @param Level $level the dimension level instance
	 *
	 * @return Level|null the overworld level instance
	 */
	public static function getDimensionBaseLevel(Level $level) : ?Level {
		if(($strpos = strpos($level->getFolderName(), "dim")) !== false) {
			$overworldName = preg_replace("([a-zA-Z0-9\s]*)(dim-?\d)", '${1}', $level->getFolderName());
			return Server::getInstance()->getLevelByName($overworldName);
		}
		return null;
	}

	/**
	 * @param Position $position
	 *
	 * @return bool false on failure
	 */
	public function makePortal(Position $position) : bool {
		if(!$position->isValid())
			return false;
		$level = $position->getLevel();
		$xDirection = (bool)mt_rand(0,1);
		if($xDirection) {
			// portals
			$level->setBlock($position, BlockFactory::get(BlockIds::PORTAL), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_UP), BlockFactory::get(BlockIds::PORTAL), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_UP, 2), BlockFactory::get(BlockIds::PORTAL), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::PORTAL), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_NORTH)->getSide(Vector3::SIDE_UP), BlockFactory::get(BlockIds::PORTAL), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_NORTH)->getSide(Vector3::SIDE_UP, 2), BlockFactory::get(BlockIds::PORTAL), false, false);
			// obsidian
			$level->setBlock($position->getSide(Vector3::SIDE_SOUTH), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_SOUTH)->getSide(Vector3::SIDE_DOWN), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_SOUTH)->getSide(Vector3::SIDE_UP), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_SOUTH)->getSide(Vector3::SIDE_UP, 2), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_SOUTH)->getSide(Vector3::SIDE_UP, 3), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_DOWN), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_DOWN)->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_UP, 3), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_UP, 3)->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_NORTH, 2), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_NORTH, 2)->getSide(Vector3::SIDE_DOWN), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_NORTH, 2)->getSide(Vector3::SIDE_UP), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_NORTH, 2)->getSide(Vector3::SIDE_UP, 2), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_NORTH, 2)->getSide(Vector3::SIDE_UP, 3), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
			// air
			$level->setBlock($position->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::AIR), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_UP)->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::AIR), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_UP, 2)->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::AIR), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::AIR), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP)->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::AIR), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP, 2)->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::AIR), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_WEST), BlockFactory::get(BlockIds::AIR), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_UP)->getSide(Vector3::SIDE_WEST), BlockFactory::get(BlockIds::AIR), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_UP, 2)->getSide(Vector3::SIDE_WEST), BlockFactory::get(BlockIds::AIR), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_WEST), BlockFactory::get(BlockIds::AIR), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP)->getSide(Vector3::SIDE_WEST), BlockFactory::get(BlockIds::AIR), false, false);
			$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP, 2)->getSide(Vector3::SIDE_WEST), BlockFactory::get(BlockIds::AIR), false, false);
			return true;
		}
		// portals
		$level->setBlock($position, BlockFactory::get(BlockIds::PORTAL, 1), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_UP), BlockFactory::get(BlockIds::PORTAL, 1), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_UP, 2), BlockFactory::get(BlockIds::PORTAL, 1), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::PORTAL, 1), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP), BlockFactory::get(BlockIds::PORTAL, 1), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP, 2), BlockFactory::get(BlockIds::PORTAL, 1), false, false);
		// obsidian
		$level->setBlock($position->getSide(Vector3::SIDE_WEST), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_WEST)->getSide(Vector3::SIDE_DOWN), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_WEST)->getSide(Vector3::SIDE_UP), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_WEST)->getSide(Vector3::SIDE_UP, 2), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_WEST)->getSide(Vector3::SIDE_UP, 3), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_DOWN), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_DOWN)->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_UP, 3), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_UP, 3)->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST, 2), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST, 2)->getSide(Vector3::SIDE_DOWN), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST, 2)->getSide(Vector3::SIDE_UP), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST, 2)->getSide(Vector3::SIDE_UP, 2), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST, 2)->getSide(Vector3::SIDE_UP, 3), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
		// air
		$level->setBlock($position->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::AIR), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_UP)->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::AIR), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_UP, 2)->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::AIR), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::AIR), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP)->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::AIR), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP, 2)->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::AIR), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_SOUTH), BlockFactory::get(BlockIds::AIR), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_UP)->getSide(Vector3::SIDE_SOUTH), BlockFactory::get(BlockIds::AIR), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_UP, 2)->getSide(Vector3::SIDE_SOUTH), BlockFactory::get(BlockIds::AIR), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_SOUTH), BlockFactory::get(BlockIds::AIR), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP)->getSide(Vector3::SIDE_SOUTH), BlockFactory::get(BlockIds::AIR), false, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP, 2)->getSide(Vector3::SIDE_SOUTH), BlockFactory::get(BlockIds::AIR), false, false);
		return true;
		// TODO: levelDB portal map
	}
}