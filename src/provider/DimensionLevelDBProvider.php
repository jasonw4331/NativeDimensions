<?php
declare(strict_types=1);

namespace jasonwynn10\NativeDimensions\provider;

use pocketmine\level\format\Chunk;
use pocketmine\level\format\io\ChunkUtils;
use pocketmine\level\format\io\exception\CorruptedChunkException;
use pocketmine\level\format\io\exception\UnsupportedChunkFormatException;
use pocketmine\level\format\io\leveldb\LevelDB;
use pocketmine\level\format\SubChunk;
use pocketmine\level\generator\Flat;
use pocketmine\level\generator\GeneratorManager;
use pocketmine\level\Level;
use pocketmine\level\LevelException;
use pocketmine\nbt\LittleEndianNBTStream;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\LongTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\Server;
use pocketmine\utils\Binary;
use pocketmine\utils\BinaryStream;

class DimensionLevelDBProvider extends LevelDB {
	/** @var int $dimensionId */
	protected $dimensionId = 0;

	public function __construct(string $path, int $dimension = 0) {
		if($dimension < 0) {
			throw new \LevelDBException("Dimension cannot be saved with negative index");
		}

		$this->path = $path;
		if(!file_exists($this->path)) {
			mkdir($this->path, 0777, true);
		}
		$this->loadLevelData();

		if($dimension === 0) {
			$this->fixLevelData();
			parent::__construct($path);
		}

		$this->dimensionId = $dimension;

		if(strpos($this->getName(), " dim") !== false) {
			$overWorldName = preg_replace('/([a-zA-Z0-9\s]*)(\sdim-?\d)/', '${1}', $this->getName());
			/** @var DimensionLevelDBProvider $provider */
			$provider = Server::getInstance()->getLevelByName($overWorldName)->getProvider();
			$this->db = $provider->getDb();
		}
	}

	protected function fixLevelData() : void {
		if(!$this->levelData->hasTag("NetherScale", IntTag::class)) {
			$this->levelData->setInt("NetherScale", 8);
		}

		parent::fixLevelData();
	}

	public function getName() : string {
		return parent::getName().($this->dimensionId === 1 ? ' dim-1' : ($this->dimensionId === 2 ? ' dim1' : ''));
	}

	public function getDb() : \LevelDB {
		return $this->db;
	}

	public static function generate(string $path, string $name, int $seed, string $generator, array $options = [], int $dimension = 0) {
		if($dimension !== 0) {
			return;
		}

		self::checkForLevelDBExtension();

		if(!file_exists($path."/db")) {
			mkdir($path."/db", 0777, true);
		}

		switch($generator) {
			case Flat::class:
				$generatorType = self::GENERATOR_FLAT;
			break;
			default:
				$generatorType = self::GENERATOR_INFINITE;
			//TODO: add support for limited worlds
		}

		$levelData = new CompoundTag("", [//Vanilla fields
			new IntTag("DayCycleStopTime", -1), new IntTag("Difficulty", Level::getDifficultyFromString((string)($options["difficulty"] ?? "normal"))), new ByteTag("ForceGameType", 0), new IntTag("GameType", 0), new IntTag("Generator", $generatorType), new LongTag("LastPlayed", time()), new StringTag("LevelName", $name), new IntTag("NetworkVersion", ProtocolInfo::CURRENT_PROTOCOL), //new IntTag("Platform", 2), //TODO: find out what the possible values are for
			new LongTag("RandomSeed", $seed), new IntTag("SpawnX", 0), new IntTag("SpawnY", 32767), new IntTag("SpawnZ", 0), new IntTag("StorageVersion", self::CURRENT_STORAGE_VERSION), new LongTag("Time", 0), new ByteTag("eduLevel", 0), new ByteTag("falldamage", 1), new ByteTag("firedamage", 1), new ByteTag("hasBeenLoadedInCreative", 1), //badly named, this actually determines whether achievements can be earned in this world...
			new ByteTag("immutableWorld", 0), new FloatTag("lightningLevel", 0.0), new IntTag("lightningTime", 0), new ByteTag("pvp", 1), new FloatTag("rainLevel", 0.0), new IntTag("rainTime", 0), new ByteTag("spawnMobs", 1), new ByteTag("texturePacksRequired", 0), //TODO

			//Additional PocketMine-MP fields
			new CompoundTag("GameRules", []), new ByteTag("hardcore", ($options["hardcore"] ?? false) === true ? 1 : 0), new StringTag("generatorName", GeneratorManager::getGeneratorName($generator)), new StringTag("generatorOptions", $options["preset"] ?? "")]);

		$nbt = new LittleEndianNBTStream();
		$buffer = $nbt->write($levelData);
		file_put_contents($path."level.dat", Binary::writeLInt(self::CURRENT_STORAGE_VERSION).Binary::writeLInt(strlen($buffer)).$buffer);

		$db = self::createDB($path);

		if($generatorType === self::GENERATOR_FLAT and isset($options["preset"])) {
			$layers = explode(";", $options["preset"])[1] ?? "";
			if($layers !== "") {
				$out = "[";
				foreach(Flat::parseLayers($layers) as $result) {
					$out .= $result[0].","; //only id, meta will unfortunately not survive :(
				}
				$out = rtrim($out, ",")."]"; //remove trailing comma
				$db->put(self::ENTRY_FLAT_WORLD_LAYERS, $out); //Add vanilla flatworld layers to allow terrain generation by MCPE to continue seamlessly
			}
		}

		$db->close();

	}

	private static function checkForLevelDBExtension() : void {
		if(!extension_loaded('leveldb')) {
			throw new LevelException("The leveldb PHP extension is required to use this world format");
		}

		if(!defined('LEVELDB_ZLIB_RAW_COMPRESSION')) {
			throw new LevelException("Given version of php-leveldb doesn't support zlib raw compression");
		}
	}

	private static function createDB(string $path) : \LevelDB {
		return new \LevelDB($path."/db", ["compression" => LEVELDB_ZLIB_RAW_COMPRESSION]);
	}

	/**
	 * @throws UnsupportedChunkFormatException
	 */
	protected function readChunk(int $chunkX, int $chunkZ) : ?Chunk {
		$index = $this->dimensionChunkIndex($chunkX, $chunkZ);

		$closeDB = false;
		try {
			$this->db->getProperty('leveldb.stats');
		}catch(\Exception $e) {
			$this->db = self::createDB($this->path);
			$closeDB = true;
		}

		$chunkVersionRaw = $this->db->get($index.self::TAG_VERSION);
		if($chunkVersionRaw === false) {
			return null;
		}

		/** @var SubChunk[] $subChunks */
		$subChunks = [];

		/** @var int[] $heightMap */
		$heightMap = [];
		/** @var string $biomeIds */
		$biomeIds = "";

		/** @var bool $lightPopulated */
		$lightPopulated = true;

		$chunkVersion = ord($chunkVersionRaw);
		$hasBeenUpgraded = $chunkVersion < self::CURRENT_LEVEL_CHUNK_VERSION;

		$binaryStream = new BinaryStream();

		switch($chunkVersion) {
			case 7: //MCPE 1.2 (???)
			case 4: //MCPE 1.1
				//TODO: check beds
			case 3: //MCPE 1.0
				for($y = 0; $y < Chunk::MAX_SUBCHUNKS; ++$y) {
					if(($data = $this->db->get($index.self::TAG_SUBCHUNK_PREFIX.chr($y))) === false) {
						continue;
					}

					$binaryStream->setBuffer($data, 0);
					$subChunkVersion = $binaryStream->getByte();
					if($subChunkVersion < self::CURRENT_LEVEL_SUBCHUNK_VERSION) {
						$hasBeenUpgraded = true;
					}

					switch($subChunkVersion) {
						case 0:
							$blocks = $binaryStream->get(4096);
							$blockData = $binaryStream->get(2048);
							if($chunkVersion < 4) {
								$blockSkyLight = $binaryStream->get(2048);
								$blockLight = $binaryStream->get(2048);
								$hasBeenUpgraded = true; //drop saved light
							}else {
								//Mojang didn't bother changing the subchunk version when they stopped saving sky light -_-
								$blockSkyLight = "";
								$blockLight = "";
								$lightPopulated = false;
							}

							$subChunks[$y] = new SubChunk($blocks, $blockData, $blockSkyLight, $blockLight);
						break;
						default:
							//TODO: set chunks read-only so the version on disk doesn't get overwritten
							throw new UnsupportedChunkFormatException("don't know how to decode LevelDB subchunk format version $subChunkVersion");
					}
				}

				if(($maps2d = $this->db->get($index.self::TAG_DATA_2D)) !== false) {
					$binaryStream->setBuffer($maps2d, 0);

					$heightMap = array_values(unpack("v*", $binaryStream->get(512)));
					$biomeIds = $binaryStream->get(256);
				}
			break;
			case 2: // < MCPE 1.0
				$legacyTerrain = $this->db->get($index.self::TAG_LEGACY_TERRAIN);
				if($legacyTerrain === false) {
					throw new CorruptedChunkException("Expected to find a LEGACY_TERRAIN key for this chunk version, but none found");
				}
				$binaryStream->setBuffer($legacyTerrain);
				$fullIds = $binaryStream->get(32768);
				$fullData = $binaryStream->get(16384);
				$fullSkyLight = $binaryStream->get(16384);
				$fullBlockLight = $binaryStream->get(16384);

				for($yy = 0; $yy < 8; ++$yy) {
					$subOffset = ($yy << 4);
					$ids = "";
					for($i = 0; $i < 256; ++$i) {
						$ids .= substr($fullIds, $subOffset, 16);
						$subOffset += 128;
					}
					$data = "";
					$subOffset = ($yy << 3);
					for($i = 0; $i < 256; ++$i) {
						$data .= substr($fullData, $subOffset, 8);
						$subOffset += 64;
					}
					$skyLight = "";
					$subOffset = ($yy << 3);
					for($i = 0; $i < 256; ++$i) {
						$skyLight .= substr($fullSkyLight, $subOffset, 8);
						$subOffset += 64;
					}
					$blockLight = "";
					$subOffset = ($yy << 3);
					for($i = 0; $i < 256; ++$i) {
						$blockLight .= substr($fullBlockLight, $subOffset, 8);
						$subOffset += 64;
					}
					$subChunks[$yy] = new SubChunk($ids, $data, $skyLight, $blockLight);
				}

				$heightMap = array_values(unpack("C*", $binaryStream->get(256)));
				$biomeIds = ChunkUtils::convertBiomeColors(array_values(unpack("N*", $binaryStream->get(1024))));
			break;
			default:
				//TODO: set chunks read-only so the version on disk doesn't get overwritten
				throw new UnsupportedChunkFormatException("don't know how to decode chunk format version $chunkVersion");
		}

		$nbt = new LittleEndianNBTStream();

		/** @var CompoundTag[] $entities */
		$entities = [];
		if(($entityData = $this->db->get($index.self::TAG_ENTITY)) !== false and $entityData !== "") {
			$entityTags = $nbt->read($entityData, true);
			foreach((is_array($entityTags) ? $entityTags : [$entityTags]) as $entityTag) {
				if(!($entityTag instanceof CompoundTag)) {
					throw new CorruptedChunkException("Entity root tag should be TAG_Compound");
				}
				if($entityTag->hasTag("id", IntTag::class)) {
					$entityTag->setInt("id", $entityTag->getInt("id") & 0xff); //remove type flags - TODO: use these instead of removing them)
				}
				$entities[] = $entityTag;
			}
		}

		/** @var CompoundTag[] $tiles */
		$tiles = [];
		if(($tileData = $this->db->get($index.self::TAG_BLOCK_ENTITY)) !== false and $tileData !== "") {
			$tileTags = $nbt->read($tileData, true);
			foreach((is_array($tileTags) ? $tileTags : [$tileTags]) as $tileTag) {
				if(!($tileTag instanceof CompoundTag)) {
					throw new CorruptedChunkException("Tile root tag should be TAG_Compound");
				}
				$tiles[] = $tileTag;
			}
		}

		//TODO: extra data should be converted into blockstorage layers (first they need to be implemented!)
		/*
		$extraData = [];
		if(($extraRawData = $this->db->get($index . self::TAG_BLOCK_EXTRA_DATA)) !== false and $extraRawData !== ""){
			$binaryStream->setBuffer($extraRawData, 0);
			$count = $binaryStream->getLInt();
			for($i = 0; $i < $count; ++$i){
				$key = $binaryStream->getLInt();
				$value = $binaryStream->getLShort();
				$extraData[$key] = $value;
			}
		}*/

		if($closeDB) {
			$this->close();
		}

		$chunk = new Chunk($chunkX, $chunkZ, $subChunks, $entities, $tiles, $biomeIds, $heightMap);

		//TODO: tile ticks, biome states (?)

		$chunk->setGenerated(true);
		$chunk->setPopulated(true);
		$chunk->setLightPopulated($lightPopulated);
		$chunk->setChanged($hasBeenUpgraded); //trigger rewriting chunk to disk if it was converted from an older format

		return $chunk;
	}

	public function dimensionChunkIndex(int $chunkX, int $chunkZ) : string {
		return self::chunkIndex($chunkX, $chunkZ).Binary::writeLInt($this->dimensionId);
	}

	protected function writeChunk(Chunk $chunk) : void {
		$index = $this->dimensionChunkIndex($chunk->getX(), $chunk->getZ());
		$closeDB = false;
		try {
			$this->db->getProperty('leveldb.stats');
		}catch(\Exception $e) {
			$this->db = self::createDB($this->path);
			$closeDB = true;
		}

		$this->db->put($index.self::TAG_VERSION, chr(self::CURRENT_LEVEL_CHUNK_VERSION));

		$subChunks = $chunk->getSubChunks();
		foreach($subChunks as $y => $subChunk) {
			$key = $index.self::TAG_SUBCHUNK_PREFIX.chr($y);
			if($subChunk->isEmpty(false)) { //MCPE doesn't save light anymore as of 1.1
				$this->db->delete($key);
			}else {
				$this->db->put($key, chr(self::CURRENT_LEVEL_SUBCHUNK_VERSION).$subChunk->getBlockIdArray().$subChunk->getBlockDataArray());
			}
		}

		$this->db->put($index.self::TAG_DATA_2D, pack("v*", ...$chunk->getHeightMapArray()).$chunk->getBiomeIdArray());

		//TODO: use this properly
		$this->db->put($index.self::TAG_STATE_FINALISATION, chr(self::FINALISATION_DONE));

		/** @var CompoundTag[] $tiles */
		$tiles = [];
		foreach($chunk->getTiles() as $tile) {
			$tiles[] = $tile->saveNBT();
		}
		$this->writeTags($tiles, $index.self::TAG_BLOCK_ENTITY);

		/** @var CompoundTag[] $entities */
		$entities = [];
		foreach($chunk->getSavableEntities() as $entity) {
			$entity->saveNBT();
			$entities[] = $entity->namedtag;
		}
		$this->writeTags($entities, $index.self::TAG_ENTITY);

		$this->db->delete($index.self::TAG_DATA_2D_LEGACY);
		$this->db->delete($index.self::TAG_LEGACY_TERRAIN);

		if($closeDB) {
			$this->close();
		}
	}

	/**
	 * @param CompoundTag[] $targets
	 */
	private function writeTags(array $targets, string $index) : void {
		if(count($targets) > 0) {
			$nbt = new LittleEndianNBTStream();
			$this->db->put($index, $nbt->write($targets));
		}else {
			$this->db->delete($index);
		}
	}

	public function getDimensionId() : int {
		return $this->dimensionId;
	}

}