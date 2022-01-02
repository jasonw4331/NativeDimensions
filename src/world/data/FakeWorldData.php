<?php

namespace jasonwynn10\NativeDimensions\world\data;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\format\io\data\JavaWorldData;
use pocketmine\world\format\io\exception\CorruptedWorldException;
use pocketmine\world\format\io\exception\UnsupportedWorldFormatException;
use pocketmine\world\WorldCreationOptions;

class FakeWorldData extends JavaWorldData{

	/** @var string */
	protected $dataPath;

	/** @var CompoundTag */
	protected $compoundTag;

	/**
	 * @throws CorruptedWorldException
	 * @throws UnsupportedWorldFormatException
	 */
	public function __construct(){
		try{
			$this->compoundTag = $this->load();
		}catch(CorruptedWorldException $e){
			throw new CorruptedWorldException("Corrupted world data: " . $e->getMessage(), 0, $e);
		}
		$this->fix();
	}

	/**
	 * @throws CorruptedWorldException
	 * @throws UnsupportedWorldFormatException
	 */
	protected function load() : CompoundTag{
		return CompoundTag::create();
	}

	public static function generate(string $path, string $name, WorldCreationOptions $options, int $version = 19133) : void{
//		//TODO, add extra details
//
//		$worldData = CompoundTag::create()
//			->setByte("hardcore", 0)
//			->setByte("Difficulty", $options->getDifficulty())
//			->setByte("initialized", 1)
//			->setInt("GameType", 0)
//			->setInt("generatorVersion", 1) //2 in MCPE
//			->setInt("SpawnX", $options->getSpawnPosition()->getFloorX())
//			->setInt("SpawnY", $options->getSpawnPosition()->getFloorY())
//			->setInt("SpawnZ", $options->getSpawnPosition()->getFloorZ())
//			->setInt("version", $version)
//			->setInt("DayTime", 0)
//			->setLong("LastPlayed", (int) (microtime(true) * 1000))
//			->setLong("RandomSeed", $options->getSeed())
//			->setLong("SizeOnDisk", 0)
//			->setLong("Time", 0)
//			->setString("generatorName", GeneratorManager::getInstance()->getGeneratorName($options->getGeneratorClass()))
//			->setString("generatorOptions", $options->getGeneratorOptions())
//			->setString("LevelName", $name)
//			->setTag("GameRules", new CompoundTag());
//
//		$nbt = new BigEndianNbtSerializer();
//		$buffer = zlib_encode($nbt->write(new TreeRoot(CompoundTag::create()->setTag("Data", $worldData))), ZLIB_ENCODING_GZIP);
//		file_put_contents(Path::join($path, "level.dat"), $buffer);
	}

	public function save() : void{
//		$nbt = new BigEndianNbtSerializer();
//		$buffer = zlib_encode($nbt->write(new TreeRoot(CompoundTag::create()->setTag("Data", $this->compoundTag))), ZLIB_ENCODING_GZIP);
//		file_put_contents($this->dataPath, $buffer);
	}
}