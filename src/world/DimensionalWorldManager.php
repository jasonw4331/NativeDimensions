<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\world;

use jasonw4331\NativeDimensions\world\converter\DimensionalFormatConverter;
use jasonw4331\NativeDimensions\world\provider\DimensionalWorldProviderManager;
use jasonw4331\NativeDimensions\world\provider\DimensionLevelDBProvider;
use pocketmine\entity\Entity;
use pocketmine\event\world\WorldInitEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\player\ChunkSelector;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\exception\CorruptedWorldException;
use pocketmine\world\format\io\exception\UnsupportedWorldFormatException;
use pocketmine\world\format\io\WorldProviderManager;
use pocketmine\world\format\io\WritableWorldProvider;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\generator\InvalidGeneratorOptionsException;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;
use pocketmine\world\WorldException;
use pocketmine\world\WorldManager;
use Symfony\Component\Filesystem\Path;
use function array_keys;
use function array_shift;
use function assert;
use function count;
use function floor;
use function implode;
use function intdiv;
use function iterator_to_array;
use function microtime;
use function round;
use function sprintf;
use function strval;
use function trim;

class DimensionalWorldManager extends WorldManager{
	public const TICKS_PER_AUTOSAVE = 300 * Server::TARGET_TICKS_PER_SECOND;

	/** @var DimensionalWorld[] */
	private array $worlds = [];
	private ?World $defaultWorld = null;

	private bool $autoSave = true;
	private int $autoSaveTicks = self::TICKS_PER_AUTOSAVE;
	private int $autoSaveTicker = 0;

	public function __construct(
		private Server $server,
		private string $dataPath,
		private DimensionalWorldProviderManager $providerManager
	){}

	public function getProviderManager() : WorldProviderManager{
		throw new \BadMethodCallException("Use of pocketmine WorldProviderManager is disallowed through NativeDimensions");
	}

	public function getDimensionalProviderManager() : DimensionalWorldProviderManager{
		return $this->providerManager;
	}

	/**
	 * @return World[]
	 */
	public function getWorlds() : array{
		return $this->worlds;
	}

	public function getDefaultWorld() : ?World{
		return $this->defaultWorld;
	}

	/**
	 * Sets the default world to a different world
	 * This won't change the level-name property,
	 * it only affects the server on runtime
	 */
	public function setDefaultWorld(?World $world) : void{
		if($world instanceof DimensionalWorld) {
			$world = $world->getOverworld();
		}
		if($world === null || ($this->isWorldLoaded($world->getFolderName()) && $world !== $this->defaultWorld)){
			$this->defaultWorld = $world;
		}
	}

	public function isWorldLoaded(string $name) : bool{
		return $this->getWorldByName($name) instanceof World;
	}

	public function getWorld(int $worldId) : ?World{
		return $this->worlds[$worldId] ?? null;
	}

	/**
	 * NOTE: This matches worlds based on the FOLDER name, NOT the display name.
	 */
	public function getWorldByName(string $name) : ?World{
		foreach($this->worlds as $world){
			if($world->getFolderName() === $name){
				return $world;
			}
		}

		return null;
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	public function unloadWorld(World $world, bool $forceUnload = false) : bool{
		if($world === $this->getDefaultWorld() && !$forceUnload){
			throw new \InvalidArgumentException("The default world cannot be unloaded while running, please switch worlds.");
		}
		if($world->isDoingTick()){
			throw new \InvalidArgumentException("Cannot unload a world during world tick");
		}
		if($world instanceof DimensionalWorld && !$world->isLoaded()) {
			return true; // we already unloaded the dimension
		}

		$ev = new WorldUnloadEvent($world);
		if($world === $this->defaultWorld && !$forceUnload){
			$ev->cancel();
		}

		$ev->call();

		if(!$forceUnload && $ev->isCancelled()){
			return false;
		}

		$this->server->getLogger()->info($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_unloading($world->getDisplayName())));
		if(count($world->getPlayers()) !== 0){
			try{
				$safeSpawn = $this->defaultWorld !== null && $this->defaultWorld !== $world ? $this->defaultWorld->getSafeSpawn() : null;
			}catch(WorldException $e){
				$safeSpawn = null;
			}
			foreach($world->getPlayers() as $player){
				if($safeSpawn === null){
					$player->disconnect("Forced default world unload");
				}else{
					$player->teleport($safeSpawn);
				}
			}
		}

		if($world instanceof DimensionalWorld && $world->getDimensionId() === DimensionIds::OVERWORLD){
			$this->unloadWorld($world->getEnd(), true);
			$this->unloadWorld($world->getNether(), true);
		}

		if($world === $this->defaultWorld){
			$this->defaultWorld = null;
		}
		unset($this->worlds[$world->getId()]);

		$world->onUnload();
		return true;
	}

	/**
	 * Loads a world from the data directory
	 *
	 * @param bool $autoUpgrade Converts worlds to the default format if the world's format is not writable / deprecated
	 *
	 * @throws WorldException
	 */
	public function loadWorld(string $name, bool $autoUpgrade = false) : bool{
		if(trim($name) === ""){
			throw new \InvalidArgumentException("Invalid empty world name");
		}
		if($this->isWorldLoaded($name)){
			return true;
		}elseif(!$this->isWorldGenerated($name)){
			return false;
		}

		$path = $this->getWorldPath($name);

		$providers = $this->providerManager->getMatchingProviders($path);
		if(count($providers) !== 1){
			$this->server->getLogger()->error($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_loadError(
				$name,
				count($providers) === 0 ?
					KnownTranslationFactory::pocketmine_level_unknownFormat() :
					KnownTranslationFactory::pocketmine_level_ambiguousFormat(implode(", ", array_keys($providers)))
			)));
			return false;
		}
		$providerClass = array_shift($providers);

		try{
			$overworld = $providerClass->fromPath($path, new \PrefixedLogger($this->server->getLogger(), "World Provider: $name"), DimensionIds::OVERWORLD);
		}catch(CorruptedWorldException $e){
			$this->server->getLogger()->error($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_loadError(
				$name,
				KnownTranslationFactory::pocketmine_level_corrupted($e->getMessage())
			)));
			return false;
		}catch(UnsupportedWorldFormatException $e){
			$this->server->getLogger()->error($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_loadError(
				$name,
				KnownTranslationFactory::pocketmine_level_unsupportedFormat($e->getMessage())
			)));
			return false;
		}

		$generatorEntry = GeneratorManager::getInstance()->getGenerator($overworld->getWorldData()->getGenerator());
		if($generatorEntry === null){
			$this->server->getLogger()->error($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_loadError(
				$name,
				KnownTranslationFactory::pocketmine_level_unknownGenerator($overworld->getWorldData()->getGenerator())
			)));
			return false;
		}
		try{
			$generatorEntry->validateGeneratorOptions($overworld->getWorldData()->getGeneratorOptions());
		}catch(InvalidGeneratorOptionsException $e){
			$this->server->getLogger()->error($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_loadError(
				$name,
				KnownTranslationFactory::pocketmine_level_invalidGeneratorOptions(
					$overworld->getWorldData()->getGeneratorOptions(),
					$overworld->getWorldData()->getGenerator(),
					$e->getMessage()
				)
			)));
			return false;
		}
		if(!($overworld instanceof WritableWorldProvider)){
			if(!$autoUpgrade){
				throw new UnsupportedWorldFormatException("World \"$name\" is in an unsupported format and needs to be upgraded");
			}
			$this->server->getLogger()->notice($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_conversion_start($name)));

			$converter = new DimensionalFormatConverter([
				$overworld,
				$providerClass->fromPath($path, new \PrefixedLogger($this->server->getLogger(), "World Provider: $name"), DimensionIds::NETHER),
				$providerClass->fromPath($path, new \PrefixedLogger($this->server->getLogger(), "World Provider: $name"), DimensionIds::THE_END)
			], $this->providerManager->getDefault(), Path::join($this->server->getDataPath(), "backups", "worlds"), $this->server->getLogger());
			[$overworld, $nether, $end] = $converter->execute();

			$this->server->getLogger()->notice($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_conversion_finish($name, $converter->getBackupPath())));
		}elseif($overworld instanceof DimensionLevelDBProvider){
			$nether = $providerClass->fromPath($path, new \PrefixedLogger($this->server->getLogger(), "World Provider: $name"), DimensionIds::NETHER, $overworld->getDatabase());
			$end = $providerClass->fromPath($path, new \PrefixedLogger($this->server->getLogger(), "World Provider: $name"), DimensionIds::THE_END, $overworld->getDatabase());
		}else{
			throw new AssumptionFailedError('WorldProvider is not a WritableWorldProvider');
		}

		$world = new DimensionalWorld($this->server, $name, $overworld, $this->server->getAsyncPool(), DimensionIds::OVERWORLD);

		$this->worlds[$world->getId()] = $world;
		$world->setAutoSave($this->autoSave);

		(new WorldLoadEvent($world))->call();

		// Nether Dimension
		$world = new DimensionalWorld($this->server, $name . " nether", $nether, $this->server->getAsyncPool(), DimensionIds::NETHER);

		$this->worlds[$world->getId()] = $world;
		$world->setAutoSave($this->autoSave);

		(new WorldLoadEvent($world))->call();

		// Nether Dimension
		$world = new DimensionalWorld($this->server, $name . " end", $end, $this->server->getAsyncPool(), DimensionIds::THE_END);

		$this->worlds[$world->getId()] = $world;
		$world->setAutoSave($this->autoSave);

		(new WorldLoadEvent($world))->call();

		return true;
	}

	/**
	 * Generates a new world if it does not exist
	 *
	 * @throws \InvalidArgumentException
	 */
	public function generateWorld(string $name, WorldCreationOptions $options, bool $backgroundGeneration = true, bool $dimensionalWorld = true) : bool{
		if(trim($name) === "" || $this->isWorldGenerated($name)){
			return false;
		}

		$providerEntry = $this->providerManager->getDefault();

		$path = $this->getWorldPath($name);
		$providerEntry->generate($path, $name, $options);
		$provider = $providerEntry->fromPath($path, new \PrefixedLogger($this->server->getLogger(), "World Provider: $name"), DimensionIds::OVERWORLD);

		if(!$dimensionalWorld){
			$world = new World($this->server, $name, $provider, $this->server->getAsyncPool());
			$this->worlds[$world->getId()] = $world;

			$world->setAutoSave($this->autoSave);

			(new WorldInitEvent($world))->call();

			(new WorldLoadEvent($world))->call();

			if($backgroundGeneration){
				$this->server->getLogger()->notice($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_backgroundGeneration($name)));

				$spawnLocation = $world->getSpawnLocation();
				$centerX = $spawnLocation->getFloorX() >> Chunk::COORD_BIT_SIZE;
				$centerZ = $spawnLocation->getFloorZ() >> Chunk::COORD_BIT_SIZE;

				$selected = iterator_to_array((new ChunkSelector())->selectChunks(8, $centerX, $centerZ));
				$overworldDone = 0;
				$total = count($selected);
				foreach($selected as $index){
					World::getXZ($index, $chunkX, $chunkZ);
					$world->orderChunkPopulation($chunkX, $chunkZ, null)->onCompletion(
						static function() use ($world, &$overworldDone, $total) : void{
							$oldProgress = (int) floor(($overworldDone / $total) * 100);
							$newProgress = (int) floor((++$overworldDone / $total) * 100);
							if(intdiv($oldProgress, 10) !== intdiv($newProgress, 10) || $overworldDone === $total || $overworldDone === 1){
								$world->getLogger()->info($world->getServer()->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_spawnTerrainGenerationProgress(strval($overworldDone), strval($total), strval($newProgress))));
							}
						},
						static function() : void{
							//NOOP: we don't care if the world was unloaded
						});
				}
			}
		}else{
			$world = new DimensionalWorld($this->server, $name, $provider, $this->server->getAsyncPool(), DimensionIds::OVERWORLD);
			$this->worlds[$world->getId()] = $world;

			$world->setAutoSave($this->autoSave);

			(new WorldInitEvent($world))->call();

			(new WorldLoadEvent($world))->call();

			if($backgroundGeneration){
				$this->server->getLogger()->notice($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_backgroundGeneration($name)));

				$spawnLocation = $world->getSpawnLocation();
				$centerX = $spawnLocation->getFloorX() >> Chunk::COORD_BIT_SIZE;
				$centerZ = $spawnLocation->getFloorZ() >> Chunk::COORD_BIT_SIZE;

				$selected = iterator_to_array((new ChunkSelector())->selectChunks(8, $centerX, $centerZ));
				$overworldDone = 0;
				$total = count($selected);
				foreach($selected as $index){
					World::getXZ($index, $chunkX, $chunkZ);
					$world->orderChunkPopulation($chunkX, $chunkZ, null)->onCompletion(
						static function() use ($world, &$overworldDone, $total) : void{
							$oldProgress = (int) floor(($overworldDone / $total) * 100);
							$newProgress = (int) floor((++$overworldDone / $total) * 100);
							if(intdiv($oldProgress, 10) !== intdiv($newProgress, 10) || $overworldDone === $total || $overworldDone === 1){
								$world->getLogger()->info($world->getServer()->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_spawnTerrainGenerationProgress(strval($overworldDone), strval($total), strval($newProgress))));
							}
						},
						static function() : void{
							//NOOP: we don't care if the world was unloaded
						});
				}
			}

			$world = new DimensionalWorld($this->server, $name . " nether", $providerEntry->fromPath($path, new \PrefixedLogger($this->server->getLogger(), "World Provider: $name"), DimensionIds::NETHER, $provider->getDatabase()), $this->server->getAsyncPool(), DimensionIds::NETHER);
			$this->worlds[$world->getId()] = $world;

			$world->setAutoSave($this->autoSave);

			(new WorldInitEvent($world))->call();

			(new WorldLoadEvent($world))->call();

			if($backgroundGeneration){
				$this->server->getLogger()->notice($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_backgroundGeneration($name)));

				$spawnLocation = $world->getSpawnLocation();
				$centerX = $spawnLocation->getFloorX() >> Chunk::COORD_BIT_SIZE;
				$centerZ = $spawnLocation->getFloorZ() >> Chunk::COORD_BIT_SIZE;

				$selected = iterator_to_array((new ChunkSelector())->selectChunks(8, $centerX, $centerZ));
				$netherDone = 0;
				$total = count($selected);
				foreach($selected as $index){
					World::getXZ($index, $chunkX, $chunkZ);
					$world->orderChunkPopulation($chunkX, $chunkZ, null)->onCompletion(
						static function() use ($world, &$netherDone, $total) : void{
							$oldProgress = (int) floor(($netherDone / $total) * 100);
							$newProgress = (int) floor((++$netherDone / $total) * 100);
							if(intdiv($oldProgress, 10) !== intdiv($newProgress, 10) || $netherDone === $total || $netherDone === 1){
								$world->getLogger()->info($world->getServer()->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_spawnTerrainGenerationProgress(strval($netherDone), strval($total), strval($newProgress))));
							}
						},
						static function() : void{
							//NOOP: we don't care if the world was unloaded
						});
				}
			}

			$world = new DimensionalWorld($this->server, $name . " end", $providerEntry->fromPath($path, new \PrefixedLogger($this->server->getLogger(), "World Provider: $name"), DimensionIds::THE_END, $provider->getDatabase()), $this->server->getAsyncPool(), DimensionIds::THE_END);
			$this->worlds[$world->getId()] = $world;

			$world->setAutoSave($this->autoSave);

			(new WorldInitEvent($world))->call();

			(new WorldLoadEvent($world))->call();

			if($backgroundGeneration){
				$this->server->getLogger()->notice($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_backgroundGeneration($name)));

				$spawnLocation = $world->getSpawnLocation();
				$centerX = $spawnLocation->getFloorX() >> Chunk::COORD_BIT_SIZE;
				$centerZ = $spawnLocation->getFloorZ() >> Chunk::COORD_BIT_SIZE;

				$selected = iterator_to_array((new ChunkSelector())->selectChunks(8, $centerX, $centerZ));
				$endDone = 0;
				$total = count($selected);
				foreach($selected as $index){
					World::getXZ($index, $chunkX, $chunkZ);
					$world->orderChunkPopulation($chunkX, $chunkZ, null)->onCompletion(
						static function() use ($world, &$endDone, $total) : void{
							$oldProgress = (int) floor(($endDone / $total) * 100);
							$newProgress = (int) floor((++$endDone / $total) * 100);
							if(intdiv($oldProgress, 10) !== intdiv($newProgress, 10) || $endDone === $total || $endDone === 1){
								$world->getLogger()->info($world->getServer()->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_spawnTerrainGenerationProgress(strval($endDone), strval($total), strval($newProgress))));
							}
						},
						static function() : void{
							//NOOP: we don't care if the world was unloaded
						});
				}
			}
		}

		return true;
	}

	private function getWorldPath(string $name) : string{
		return Path::join($this->dataPath, $name) . "/"; //TODO: check if we still need the trailing dirsep (I'm a little scared to remove it)
	}

	public function isWorldGenerated(string $name) : bool{
		if(trim($name) === ""){
			return false;
		}
		$path = $this->getWorldPath($name);
		if(!($this->getWorldByName($name) instanceof World)){
			return count($this->providerManager->getMatchingProviders($path)) > 0;
		}

		return true;
	}

	/**
	 * Searches all worlds for the entity with the specified ID.
	 * Useful for tracking entities across multiple worlds without needing strong references.
	 */
	public function findEntity(int $entityId) : ?Entity{
		foreach($this->worlds as $world){
			assert($world->isLoaded());
			if(($entity = $world->getEntity($entityId)) instanceof Entity){
				return $entity;
			}
		}

		return null;
	}

	public function tick(int $currentTick) : void{
		foreach($this->worlds as $k => $world){
			if(!isset($this->worlds[$k])){
				// World unloaded during the tick of a world earlier in this loop, perhaps by plugin
				continue;
			}

			$worldTime = microtime(true);
			$world->doTick($currentTick);
			$tickMs = (microtime(true) - $worldTime) * 1000;
			$world->tickRateTime = $tickMs;
			if($tickMs >= Server::TARGET_SECONDS_PER_TICK * 1000){
				$world->getLogger()->debug(sprintf("Tick took too long: %gms (%g ticks)", $tickMs, round($tickMs / (Server::TARGET_SECONDS_PER_TICK * 1000), 2)));
			}
		}

		if($this->autoSave && ++$this->autoSaveTicker >= $this->autoSaveTicks){
			$this->autoSaveTicker = 0;
			$this->server->getLogger()->debug("[Auto Save] Saving worlds...");
			$start = microtime(true);
			$this->doAutoSave();
			$time = microtime(true) - $start;
			$this->server->getLogger()->debug("[Auto Save] Save completed in " . ($time >= 1 ? round($time, 3) . "s" : round($time * 1000) . "ms"));
		}
	}

	public function getAutoSave() : bool{
		return $this->autoSave;
	}

	public function setAutoSave(bool $value) : void{
		$this->autoSave = $value;
		foreach($this->worlds as $world){
			$world->setAutoSave($this->autoSave);
		}
	}

	/**
	 * Returns the period in ticks after which loaded worlds will be automatically saved to disk.
	 */
	public function getAutoSaveInterval() : int{
		return $this->autoSaveTicks;
	}

	public function setAutoSaveInterval(int $autoSaveTicks) : void{
		if($autoSaveTicks <= 0){
			throw new \InvalidArgumentException("Autosave ticks must be positive");
		}
		$this->autoSaveTicks = $autoSaveTicks;
	}

	private function doAutoSave() : void{
		foreach($this->worlds as $world){
			foreach($world->getPlayers() as $player){
				if($player->spawned){
					$player->save();
				}
			}
			$world->save(false);
		}
	}
}
