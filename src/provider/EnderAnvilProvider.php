<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\provider;

use jasonwynn10\NativeDimensions\world\data\FakeWorldData;
use pocketmine\world\format\io\exception\CorruptedChunkException;
use pocketmine\world\format\io\region\Anvil;
use pocketmine\world\format\io\WorldData;
use pocketmine\world\WorldException;
use Webmozart\PathUtil\Path;

class EnderAnvilProvider extends Anvil {

	public function __construct(string $path, ?WorldData $data = null){
		if(!file_exists($path)){
			throw new WorldException("World does not exist");
		}

		if(!$data instanceof WorldData) {
			throw new WorldException("Cannot have dimension without overworld data");
		}

		$this->path = $path;
		$this->worldData = $data;
	}

	protected function loadLevelData() : WorldData{
		return $this->worldData ?? new FakeWorldData();
	}

	protected function pathToRegion(int $regionX, int $regionZ) : string{
		return Path::join($this->path, "dim1", "region", "r.$regionX.$regionZ." . static::getRegionFileExtension());
	}

	public static function isValid(string $path) : bool{
		if(file_exists(Path::join($path, "level.dat")) and is_dir($regionPath = Path::join($path, "dim1", "region"))){
			foreach(scandir($regionPath, SCANDIR_SORT_NONE) as $file){
				$extPos = strrpos($file, ".");
				if($extPos !== false && substr($file, $extPos + 1) === static::getRegionFileExtension()){
					//we don't care if other region types exist, we only care if this format is possible
					return true;
				}
			}
		}

		return false;
	}

	private function createRegionIterator() : \RegexIterator{
		return new \RegexIterator(
			new \FilesystemIterator(
				Path::join($this->path, 'dim1', 'region'),
				\FilesystemIterator::CURRENT_AS_PATHNAME | \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS
			),
			'/\/r\.(-?\d+)\.(-?\d+)\.' . static::getRegionFileExtension() . '$/',
			\RegexIterator::GET_MATCH
		);
	}

	public function getAllChunks(bool $skipCorrupted = false, ?\Logger $logger = null) : \Generator{
		$iterator = $this->createRegionIterator();

		foreach($iterator as $region){
			$regionX = ((int) $region[1]);
			$regionZ = ((int) $region[2]);
			$rX = $regionX << 5;
			$rZ = $regionZ << 5;

			for($chunkX = $rX; $chunkX < $rX + 32; ++$chunkX){
				for($chunkZ = $rZ; $chunkZ < $rZ + 32; ++$chunkZ){
					try{
						$chunk = $this->loadChunk($chunkX, $chunkZ);
						if($chunk !== null){
							yield [$chunkX, $chunkZ] => $chunk;
						}
					}catch(CorruptedChunkException $e){
						if(!$skipCorrupted){
							throw $e;
						}
						if($logger !== null){
							$logger->error("Skipped corrupted chunk $chunkX $chunkZ (" . $e->getMessage() . ")");
						}
					}
				}
			}

			$this->unloadRegion($regionX, $regionZ);
		}
	}

	public function calculateChunkCount() : int{
		$count = 0;
		foreach($this->createRegionIterator() as $region){
			$regionX = ((int) $region[1]);
			$regionZ = ((int) $region[2]);
			$count += $this->loadRegion($regionX, $regionZ)->calculateChunkCount();
			$this->unloadRegion($regionX, $regionZ);
		}
		return $count;
	}
}