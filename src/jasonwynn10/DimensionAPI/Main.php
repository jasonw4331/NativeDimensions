<?php
declare(strict_types=1);
namespace jasonwynn10\DimensionAPI;

use jasonwynn10\DimensionAPI\block\EndPortal;
use jasonwynn10\DimensionAPI\block\EndPortalFrame;
use jasonwynn10\DimensionAPI\block\Obsidian;
use jasonwynn10\DimensionAPI\block\Portal;
use jasonwynn10\DimensionAPI\provider\AnvilDimension;
use pocketmine\block\BlockFactory;
use pocketmine\event\level\LevelInitEvent;
use pocketmine\event\level\LevelLoadEvent;
use pocketmine\level\format\io\LevelProvider;
use pocketmine\level\format\io\LevelProviderManager;
use pocketmine\level\generator\Generator;
use pocketmine\level\generator\GeneratorManager;
use pocketmine\level\generator\normal\Normal;
use pocketmine\level\Level;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;

class Main extends PluginBase {
	/** @var Main */
	private static $instance;
	/** @var int[] $teleporting */
	protected static $teleporting = [];

	public static function getInstance() : Main {
		return self::$instance;
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
		if($key !== false)
			unset(self::$teleporting[$key]);
	}

	public function onLoad() {
		self::$instance = $this;
		LevelProviderManager::addProvider(AnvilDimension::class);
		//LevelProviderManager::addProvider(LevelDBDimensionProvider::class); // TODO
	}

	public function onEnable() {
		new DimensionListener($this);
		if(GeneratorManager::getGenerator("ender") !== Normal::class) {
			BlockFactory::registerBlock(new EndPortalFrame(), true);
			BlockFactory::registerBlock(new EndPortal(), true);
		}
		BlockFactory::registerBlock(new Obsidian(), true);
		BlockFactory::registerBlock(new Portal(), true);
	}

	public function generateLevelDimension(string $name, int $dimension, ?int $seed = null, ?string $generator = null, array $options = []) : bool {
		if(trim($name) === "" or $this->getServer()->isLevelGenerated($name." dim".$dimension)) {
			return false;
		}

		$seed = $seed ?? random_int(INT32_MIN, INT32_MAX);

		if(!isset($options["preset"])){
			$options["preset"] = $this->getServer()->getConfigString("generator-settings", "");
		}

		if($generator === null or !class_exists($generator, true) or !is_subclass_of($generator, Generator::class)){
			if($dimension < 0)
				$generator = GeneratorManager::getGenerator("hell"); // default to hell because this is a dimension
			elseif($dimension > 0)
				$generator = GeneratorManager::getGenerator("ender");
		}

		$providerClass = LevelProviderManager::getProviderByName("anvil_dimension");
		if($providerClass === null){
			throw new \InvalidStateException("Dimension world provider has not been registered");
		}

		$path = $this->getServer()->getDataPath() . "worlds/" . $name . "/";
		/** @var LevelProvider $providerClass */
		$providerClass::generate($path, $name, $seed, $generator, $options);

		/** @see LevelProvider::__construct() */
		$level = new Level($this->getServer(), $name." dim".$dimension, new $providerClass($path, $dimension));
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

	public static function dimensionExists(Level $level, int $dimension) : bool {
		return AnvilDimension::isValid($level->getProvider()->getPath(), $dimension); // TODO: levelDB provider
	}

	public static function getDimensionBaseLevel(Level $level) : ?Level {
		if(strpos($level->getFolderName(), " dim") !== false) {
			$overworldName = preg_replace('/([a-zA-Z0-9\s]*)(\sdim-?\d)/', '${1}', $level->getFolderName());
			return Server::getInstance()->getLevelByName($overworldName);
		}
		return null;
	}
}