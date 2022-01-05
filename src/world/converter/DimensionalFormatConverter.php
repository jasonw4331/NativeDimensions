<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\world\converter;

use jasonwynn10\NativeDimensions\world\provider\DimensionLevelDBProvider;
use jasonwynn10\NativeDimensions\world\provider\RewritableWorldProviderManagerEntry;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\utils\Filesystem;
use pocketmine\world\format\io\WorldData;
use pocketmine\world\format\io\WorldProvider;
use pocketmine\world\format\io\WritableWorldProvider;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\generator\normal\Normal;
use pocketmine\world\WorldCreationOptions;
use Webmozart\PathUtil\Path;

class DimensionalFormatConverter {

	/** @var WorldProvider[] $oldProviders */
	private array $oldProviders;
	private RewritableWorldProviderManagerEntry $newProvider;

	private string $backupPath;

	private \Logger $logger;

	private int $chunksPerProgressUpdate;

	/**
	 * @param WorldProvider[]                     $oldProviders
	 * @param RewritableWorldProviderManagerEntry $newProvider
	 * @param string                              $backupPath
	 * @param \Logger                             $logger
	 * @param int                                 $chunksPerProgressUpdate
	 *
	 * @throws \Exception
	 */
	public function __construct(array $oldProviders, RewritableWorldProviderManagerEntry $newProvider, string $backupPath, \Logger $logger, int $chunksPerProgressUpdate = 256){
		$this->oldProviders = $oldProviders;
		$this->newProvider = $newProvider;
		$this->logger = new \PrefixedLogger($logger, "World Converter: " . $oldProviders[DimensionIds::OVERWORLD]->getWorldData()->getName());
		$this->chunksPerProgressUpdate = $chunksPerProgressUpdate;

		if(!file_exists($backupPath)){
			@mkdir($backupPath, 0777, true);
		}
		$nextSuffix = "";
		do{
			$this->backupPath = Path::join($backupPath, basename($this->oldProviders[DimensionIds::OVERWORLD]->getPath()) . $nextSuffix);
			$nextSuffix = "_" . crc32(random_bytes(4));
		}while(file_exists($this->backupPath));
	}

	/**
	 * @return WritableWorldProvider[]
	 */
	public function execute() : array{
		[$overworld, $nether, $end] = $this->generateNew();

		$this->populateLevelData($overworld->getWorldData());
		$this->convertTerrain($overworld);
		$this->convertTerrain($nether);
		$this->convertTerrain($end);

		$path = $this->oldProviders[DimensionIds::OVERWORLD]->getPath();
		$this->oldProviders[DimensionIds::OVERWORLD]->close();
		$overworld->close();
		$nether->close();
		$end->close();

		$this->logger->info("Backing up pre-conversion world to " . $this->backupPath);
		if(!@rename($path, $this->backupPath)){
			$this->logger->warning("Moving old world files for backup failed, attempting copy instead. This might take a long time.");
			Filesystem::recursiveCopy($path, $this->backupPath);
			Filesystem::recursiveUnlink($path);
		}
		if(!@rename($overworld->getPath(), $path)){
			//we don't expect this to happen because worlds/ should most likely be all on the same FS, but just in case...
			$this->logger->debug("Relocation of new world files to location failed, attempting copy and delete instead");
			Filesystem::recursiveCopy($overworld->getPath(), $path);
			Filesystem::recursiveUnlink($overworld->getPath());
		}

		$this->logger->info("Conversion completed");
		return [
			$overworld = $this->newProvider->fromPath($path, DimensionIds::OVERWORLD),
			$this->newProvider->fromPath($path, DimensionIds::NETHER, $overworld->getDatabase()),
			$this->newProvider->fromPath($path, DimensionIds::THE_END, $overworld->getDatabase()),
		];
	}

	/**
	 * @return DimensionLevelDBProvider[]
	 */
	private function generateNew() : array{
		$this->logger->info("Generating new world");
		$data = $this->oldProviders[DimensionIds::OVERWORLD]->getWorldData();

		$convertedOutput = rtrim($this->oldProviders[DimensionIds::OVERWORLD]->getPath(), "/" . DIRECTORY_SEPARATOR) . "_converted" . DIRECTORY_SEPARATOR;
		if(file_exists($convertedOutput)){
			$this->logger->info("Found previous conversion attempt, deleting...");
			Filesystem::recursiveUnlink($convertedOutput);
		}
		$this->newProvider->generate($convertedOutput, $data->getName(), WorldCreationOptions::create()
			//TODO: defaulting to NORMAL here really isn't very good behaviour, but it's consistent with what pocketmine already
			//does; WorldManager checks for unknown generators before this is reached anyways.
			->setGeneratorClass(GeneratorManager::getInstance()->getGenerator($data->getGenerator())?->getGeneratorClass() ?? Normal::class)
			->setGeneratorOptions($data->getGeneratorOptions())
			->setSeed($data->getSeed())
			->setSpawnPosition($data->getSpawn())
			->setDifficulty($data->getDifficulty())
		);

		return [
			$overworld = $this->newProvider->fromPath($convertedOutput, DimensionIds::OVERWORLD),
			$this->newProvider->fromPath($convertedOutput, DimensionIds::NETHER, $overworld->getDatabase()),
			$this->newProvider->fromPath($convertedOutput, DimensionIds::THE_END, $overworld->getDatabase()),
		];
	}

	private function populateLevelData(WorldData $data) : void{
		$this->logger->info("Converting world manifest");
		$oldData = $this->oldProviders[DimensionIds::OVERWORLD]->getWorldData();
		$data->setDifficulty($oldData->getDifficulty());
		$data->setLightningLevel($oldData->getLightningLevel());
		$data->setLightningTime($oldData->getLightningTime());
		$data->setRainLevel($oldData->getRainLevel());
		$data->setRainTime($oldData->getRainTime());
		$data->setSpawn($oldData->getSpawn());
		$data->setTime($oldData->getTime());

		$data->save(DimensionIds::OVERWORLD);
		$this->logger->info("Finished converting manifest");
		//TODO: add more properties as-needed
	}

	private function convertTerrain(DimensionLevelDBProvider $new) : void{
		$this->logger->info("Calculating chunk count");
		$provider = $this->oldProviders[$new->getDimensionId()];
		$count = $provider->calculateChunkCount();
		$this->logger->info("Discovered $count chunks");

		$counter = 0;

		$start = microtime(true);
		$thisRound = $start;
		foreach($provider->getAllChunks(true, $this->logger) as $coords => $chunk){
			[$chunkX, $chunkZ] = $coords;
			$chunk->getChunk()->setTerrainDirty();
			$new->saveChunk($chunkX, $chunkZ, $chunk);
			$counter++;
			if(($counter % $this->chunksPerProgressUpdate) === 0){
				$time = microtime(true);
				$diff = $time - $thisRound;
				$thisRound = $time;
				$this->logger->info("Converted $counter / $count chunks (" . floor($this->chunksPerProgressUpdate / $diff) . " chunks/sec)");
			}
		}
		$total = microtime(true) - $start;
		$this->logger->info("Converted $counter / $counter chunks in " . round($total, 3) . " seconds (" . floor($counter / $total) . " chunks/sec)");
	}

	/**
	 * @return string
	 */
	public function getBackupPath() : string{
		return $this->backupPath;
	}
}