<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions;

use jasonwynn10\NativeDimensions\block\EndPortal;
use jasonwynn10\NativeDimensions\block\Fire;
use jasonwynn10\NativeDimensions\block\Obsidian;
use jasonwynn10\NativeDimensions\block\Portal;
use jasonwynn10\NativeDimensions\event\DimensionListener;
use jasonwynn10\NativeDimensions\provider\DimensionLevelDBProvider;
use jasonwynn10\NativeDimensions\provider\EnderAnvilProvider;
use jasonwynn10\NativeDimensions\provider\NetherAnvilProvider;
use jasonwynn10\NativeDimensions\world\DimensionalWorldManager;
use jasonwynn10\NativeDimensions\world\provider\ReadOnlyWorldProviderManagerEntry;
use jasonwynn10\NativeDimensions\world\provider\RewritableWorldProviderManagerEntry;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIdentifier as BID;
use pocketmine\block\BlockLegacyIds as Ids;
use pocketmine\block\BlockToolType;
use pocketmine\item\ToolTier;
use pocketmine\plugin\PluginBase;
use pocketmine\world\format\io\WorldData;
use pocketmine\world\generator\GeneratorManager;

class Main extends PluginBase {
	/** @var Main */
	private static $instance;
	/** @var int[] $teleporting */
	protected static $teleporting = [];

	public static function getInstance() : Main {
		return self::$instance;
	}

	public function onLoad() : void {
		self::$instance = $this;
		// register custom world providers
		$providerManager = $this->getServer()->getWorldManager()->getProviderManager();
		$providerManager->addProvider(new ReadOnlyWorldProviderManagerEntry(\Closure::fromCallable([NetherAnvilProvider::class, 'isValid']), fn(string $path, ?WorldData $data) => new NetherAnvilProvider($path, $data)), "nether");
		$providerManager->addProvider(new ReadOnlyWorldProviderManagerEntry(\Closure::fromCallable([EnderAnvilProvider::class, 'isValid']), fn(string $path, ?WorldData $data) => new EnderAnvilProvider($path, $data)), "ender");
		$providerManager->addProvider(new RewritableWorldProviderManagerEntry(\Closure::fromCallable([DimensionLevelDBProvider::class, 'isValid']), fn(string $path, int $dimension, ?\LevelDB $db) => new DimensionLevelDBProvider($path, $dimension, $db), \Closure::fromCallable([DimensionLevelDBProvider::class, 'generate'])), "leveldb", true);

		$server = $this->getServer();
		$oldManager = $server->getWorldManager();
		foreach($oldManager->getWorlds() as $world)
			$oldManager->unloadWorld($world, true);

		// replace default world manager with one that supports dimensions
		$ref = new \ReflectionClass($server);
		$prop = $ref->getProperty('worldManager');
		$prop->setAccessible(true);
		$prop->setValue(new DimensionalWorldManager($server, $server->getDataPath(), $oldManager->getProviderManager()));
	}

	public function onEnable() : void {
		new DimensionListener($this);
		$factory = BlockFactory::getInstance();
		if(GeneratorManager::getInstance()->getGenerator("ender") !== null) {
			$factory->register(new EndPortal(new BID(Ids::END_PORTAL, 0), "End Portal", BlockBreakInfo::indestructible()));
		}
		$factory->register(new Fire(new BID(Ids::FIRE, 0), "Fire Block", BlockBreakInfo::instant()), true);
		$factory->register(new Obsidian(new BID(Ids::OBSIDIAN, 0), "Obsidian", new BlockBreakInfo(35.0 /* 50 in PC */, BlockToolType::PICKAXE, ToolTier::DIAMOND()->getHarvestLevel(), 6000.0)), true);
		$factory->register(new Portal(new BID(Ids::PORTAL, 0), "Nether Portal", BlockBreakInfo::indestructible(0.0)), true);
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
}