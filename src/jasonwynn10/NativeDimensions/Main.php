<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions;

use jasonwynn10\NativeDimensions\block\EndPortal;
use jasonwynn10\NativeDimensions\block\EndPortalFrame;
use jasonwynn10\NativeDimensions\block\Fire;
use jasonwynn10\NativeDimensions\block\Obsidian;
use jasonwynn10\NativeDimensions\block\Portal;
use jasonwynn10\NativeDimensions\event\DimensionListener;
use jasonwynn10\NativeDimensions\provider\DimensionLevelDBProvider;
use jasonwynn10\NativeDimensions\provider\EnderAnvilProvider;
use jasonwynn10\NativeDimensions\provider\NetherAnvilProvider;
use jasonwynn10\NativeDimensions\provider\SubAnvilProvider;
use pocketmine\block\BlockFactory;
use pocketmine\event\level\LevelInitEvent;
use pocketmine\event\level\LevelLoadEvent;
use pocketmine\level\format\io\leveldb\LevelDB;
use pocketmine\level\format\io\LevelProviderManager;
use pocketmine\level\format\io\region\Anvil;
use pocketmine\level\generator\Generator;
use pocketmine\level\generator\GeneratorManager;
use pocketmine\level\generator\hell\Nether;
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
		LevelProviderManager::addProvider(NetherAnvilProvider::class);
		LevelProviderManager::addProvider(EnderAnvilProvider::class);

		$ref = new \ReflectionClass(LevelProviderManager::class);
		$prop = $ref->getProperty('providers');
		$prop->setAccessible(true);
		$providers = $prop->getValue(LevelProviderManager::class);
		$providers[strtolower(LevelDB::getProviderName())] = DimensionLevelDBProvider::class;
		$prop->setValue($providers);

		LevelProviderManager::addProvider(DimensionLevelDBProvider::class);
	}

	public function onEnable() {
		new DimensionListener($this);
		if(GeneratorManager::getGenerator("ender") !== Normal::class) {
			BlockFactory::registerBlock(new EndPortalFrame(), true);
			BlockFactory::registerBlock(new EndPortal(), true);
		}
		BlockFactory::registerBlock(new Fire(), true);
		BlockFactory::registerBlock(new Obsidian(), true);
		BlockFactory::registerBlock(new Portal(), true);
	}

	public function generateLevelDimension(string $name, int $dimension, ?int $seed = null, ?string $generator = null, array $options = []) : bool {
		if(trim($name) === "" or !$this->getServer()->isLevelGenerated($name) or $this->getServer()->isLevelGenerated($name." dim".$dimension))
			return false;

		$seed = $seed ?? random_int(INT32_MIN, INT32_MAX);

		if(!isset($options["preset"]))
			$options["preset"] = $this->getServer()->getConfigString("generator-settings", "");

		if($generator === null or !class_exists($generator, true) or !is_subclass_of($generator, Generator::class)){
			if($dimension < 0)
				/** @var Nether $generator */
				$generator = GeneratorManager::getGenerator("hell");
			elseif($dimension > 0)
				/** @var Generator $generator */
				$generator = GeneratorManager::getGenerator("ender"); // might return normal if not registered
			else
				/** @var Normal $generator */
				$generator = GeneratorManager::getGenerator("default");
		}

		$levelProvider = $this->getServer()->getLevelByName($name)->getProvider();
		if($levelProvider instanceof LevelDB) {
			/** @var DimensionLevelDBProvider $providerClass */
			$providerClass = DimensionLevelDBProvider::class;
		}elseif($levelProvider instanceof Anvil) {
			if($dimension < 0)
				/** @var NetherAnvilProvider $providerClass */
				$providerClass = NetherAnvilProvider::class;
			elseif($dimension > 0)
				/** @var EnderAnvilProvider $providerClass */
				$providerClass = EnderAnvilProvider::class;
			else
				/** @var Anvil $providerClass */
				$providerClass = Anvil::class;
		}

		if($providerClass === null){
			throw new \InvalidStateException("Dimension world provider has not been registered");
		}

		$path = $this->getServer()->getDataPath() . "worlds/" . $name . "/";

		if($providerClass instanceof SubAnvilProvider)
			$providerClass::generate($path."dim" . $dimension . "/", $name, $seed, $generator, $options, $dimension);
		if($providerClass instanceof DimensionLevelDBProvider)
			$providerClass::generate($path, $name, $seed, $generator, $options, $dimension < 0 ? 1 : ($dimension > 0 ? 2 : 0));

		/** @see Anvil::__construct() */
		$level = new Level($this->getServer(), $name." dim".$dimension, new $providerClass($path, $dimension < 0 ? 1 : ($dimension > 0 ? 2 : 0)));
		$ref = new \ReflectionClass($this->getServer());
		$prop = $ref->getProperty("levels");
		$prop->setAccessible(true);
		/** @var Level[] $levels */
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
		$baseLevel = self::getDimensionBaseLevel($level) ?? $level;
		$provider = $baseLevel->getProvider();
		if($provider instanceof LevelDB) {
			return DimensionLevelDBProvider::isValid($baseLevel->getProvider()->getPath());
		}elseif($provider instanceof Anvil) {
			if($dimension < 0)
				return NetherAnvilProvider::isValid($baseLevel->getProvider()->getPath());
			if($dimension > 0)
				return EnderAnvilProvider::isValid($baseLevel->getProvider()->getPath());
			return Anvil::isValid($baseLevel->getProvider()->getPath());
		}
		return false;
	}

	public static function getDimensionBaseLevel(Level $level) : ?Level {
		if(strpos($level->getFolderName(), " dim") !== false) {
			$overWorldName = preg_replace('/([a-zA-Z0-9\s]*)(\sdim-?\d)/', '${1}', $level->getFolderName());
			return Server::getInstance()->getLevelByName($overWorldName);
		}
		return null;
	}
}