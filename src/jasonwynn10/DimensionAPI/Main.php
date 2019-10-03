<?php
declare(strict_types=1);
namespace jasonwynn10\DimensionAPI;

use czechpmdevs\multiworld\generator\ender\EnderGenerator;
use jasonwynn10\DimensionAPI\block\EndPortal;
use jasonwynn10\DimensionAPI\block\EndPortalFrame;
use jasonwynn10\DimensionAPI\block\Obsidian;
use jasonwynn10\DimensionAPI\block\Portal;
use jasonwynn10\DimensionAPI\provider\AnvilDimensionProvider;
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
	private $endGenerator = null;

	public function onLoad() {
		LevelProviderManager::addProvider(AnvilDimensionProvider::class);
		//LevelProviderManager::addProvider(LevelDBDimensionProvider::class); // TODO
	}

	public function onEnable() {
		$this->endGenerator = GeneratorManager::getGenerator("ender");
		$multiworld = $this->getServer()->getPluginManager()->getPlugin("MultiWorld");
		if($multiworld !== null)
			$this->endGenerator = EnderGenerator::class;

		BlockFactory::registerBlock(new Obsidian());
		BlockFactory::registerBlock(new Portal());
		BlockFactory::registerBlock(new EndPortalFrame());
		BlockFactory::registerBlock(new EndPortal());
	}

	/**
	 * @param string $generator
	 */
	public function setEndGenerator(string $generator) : void {
		if(!($generator !== null and class_exists($generator, true) and is_subclass_of($generator, Generator::class))){
			$this->endGenerator = GeneratorManager::getGenerator("ender");
		}
		$this->endGenerator = EnderGenerator::class;
	}

	/**
	 * @param LevelLoadEvent $event
	 *
	 * @throws \ReflectionException
	 */
	public function onLevelLoad(LevelLoadEvent $event) : void {
		$provider = $event->getLevel()->getProvider();
		if($provider instanceof Anvil and !$provider instanceof AnvilDimensionProvider) {
			if($this->dimensionExists($event->getLevel(), -1))
				$this->generateLevelDimension($event->getLevel()->getFolderName(), $event->getLevel()->getSeed(), Nether::class, [], -1);
			if($this->endGenerator !== null) {
				if($this->dimensionExists($event->getLevel(), 1))
					$this->generateLevelDimension($event->getLevel()->getFolderName(), $event->getLevel()->getSeed(), $this->endGenerator, [], -1); // TODO
			}
		}
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
		if(trim($name) === "" or $this->getServer()->isLevelGenerated($name."dim".$dimension)) {
			return false;
		}

		$seed = $seed ?? random_int(INT32_MIN, INT32_MAX);

		if(!isset($options["preset"])){
			$options["preset"] = $this->getServer()->getConfigString("generator-settings", "");
		}

		if(!($generator !== null and class_exists($generator, true) and is_subclass_of($generator, Generator::class))){
			$generator = GeneratorManager::getGenerator("hell");
		}

		$providerClass = LevelProviderManager::getProviderByName("anvildimensions");
		if($providerClass === null){
			throw new \InvalidStateException("Default world provider has not been registered");
		}

		$path = $this->getServer()->getDataPath() . "worlds/" . $name . "/";
		/** @var LevelProvider $providerClass */
		$providerClass::generate($path, $name, $seed, $generator, $options);

		/** @see LevelProvider::__construct() */
		$level = new Level($this->getServer(), $name."dim".$dimension, new $providerClass($path));
		$ref = new \ReflectionClass($this->getServer());
		$prop = $ref->getProperty("levels");
		$prop->setAccessible(true);
		$levels = $prop->getValue($this->getServer());
		$levels[$level->getId()] = $level;
		$prop->setValue($levels);
		$prop->setAccessible(false);

		(new LevelInitEvent($level))->call();

		(new LevelLoadEvent($level))->call();

		$this->getLogger()->notice($this->getServer()->getLanguage()->translateString("pocketmine.level.backgroundGeneration", [$name."dim".$dimension]));

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

	public function dimensionExists(Level $level, int $dimension) : bool {
		return AnvilDimensionProvider::isValid($level->getProvider()->getPath(), $dimension); // TODO: levelDB provider
	}
}