<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\provider;

use jasonwynn10\NativeDimensions\world\data\DimensionalBedrockWorldData;
use pocketmine\block\Block;
use pocketmine\block\BlockLegacyIds;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\data\bedrock\LegacyBlockIdToStringIdMap;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\utils\Binary;
use pocketmine\utils\BinaryDataException;
use pocketmine\utils\BinaryStream;
use pocketmine\world\format\BiomeArray;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\ChunkData;
use pocketmine\world\format\io\ChunkUtils;
use pocketmine\world\format\io\exception\CorruptedChunkException;
use pocketmine\world\format\io\exception\CorruptedWorldException;
use pocketmine\world\format\io\exception\UnsupportedWorldFormatException;
use pocketmine\world\format\io\leveldb\LevelDB;
use pocketmine\world\format\io\SubChunkConverter;
use pocketmine\world\format\io\WorldData;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use pocketmine\world\WorldCreationOptions;
use pocketmine\world\WorldException;
use Webmozart\PathUtil\Path;

class DimensionLevelDBProvider extends LevelDB {

	protected int $dimensionId = 0;

	private static function checkForLevelDBExtension() : void{
		if(!extension_loaded('leveldb')){
			throw new UnsupportedWorldFormatException("The leveldb PHP extension is required to use this world format");
		}

		if(!defined('LEVELDB_ZLIB_RAW_COMPRESSION')){
			throw new UnsupportedWorldFormatException("Given version of php-leveldb doesn't support zlib raw compression");
		}
	}

	/**
	 * @throws \LevelDBException
	 */
	private static function createDB(string $path) : \LevelDB{
		return new \LevelDB(Path::join($path, "db"), [
			"compression" => LEVELDB_ZLIB_RAW_COMPRESSION,
			"block_size" => 64 * 1024 //64KB, big enough for most chunks
		]);
	}

	public function __construct(string $path, int $dimension = 0, ?\LevelDB $db = null){
		self::checkForLevelDBExtension();
		if(!file_exists($path)){
			throw new WorldException("World does not exist");
		}

		if($dimension < 0) {
			throw new \LevelDBException("Dimension cannot be saved with negative index");
		}

		if($dimension > 0 and $db === null) {
			throw new \LevelDBException("Dimension cannot be generated without overworld data");
		}

		$this->dimensionId = $dimension;

		$this->path = $path;
		$this->worldData = $this->loadLevelData();


		try{
			$this->db = $db ?? self::createDB($path);
		}catch(\LevelDBException $e){
			//we can't tell the difference between errors caused by bad permissions and actual corruption :(
			throw new CorruptedWorldException(trim($e->getMessage()), 0, $e);
		}
	}

	protected function loadLevelData() : WorldData{
		return new DimensionalBedrockWorldData(Path::join($this->getPath(), "level.dat"));
	}

	public function getWorldMinY() : int{
		return 0; // TODO: change per dimension
	}

	public function getWorldMaxY() : int{
		return 256; // TODO: change per dimension
	}

	public static function generate(string $path, string $name, WorldCreationOptions $options, int $dimension = 0) : void{
		if($dimension !== 0) {
			return;
		}

		self::checkForLevelDBExtension();

		$dbPath = Path::join($path, "db");
		if(!file_exists($dbPath)){
			mkdir($dbPath, 0777, true);
		}

		DimensionalBedrockWorldData::generate($path, $name, $options);
	}

	/**
	 * @throws CorruptedChunkException
	 */
	public function loadChunk(int $chunkX, int $chunkZ) : ?ChunkData{
		$index = self::dimensionalChunkIndex($chunkX, $chunkZ, $this->dimensionId);

		$chunkVersionRaw = $this->db->get($index . self::TAG_VERSION);
		if($chunkVersionRaw === false){
			return null;
		}

		/** @var SubChunk[] $subChunks */
		$subChunks = [];

		/** @var BiomeArray|null $biomeArray */
		$biomeArray = null;

		$chunkVersion = ord($chunkVersionRaw);
		$hasBeenUpgraded = $chunkVersion < self::CURRENT_LEVEL_CHUNK_VERSION;

		switch($chunkVersion){
			case 15: //MCPE 1.12.0.4 beta (???)
			case 14: //MCPE 1.11.1.2 (???)
			case 13: //MCPE 1.11.0.4 beta (???)
			case 12: //MCPE 1.11.0.3 beta (???)
			case 11: //MCPE 1.11.0.1 beta (???)
			case 10: //MCPE 1.9 (???)
			case 9: //MCPE 1.8 (???)
			case 7: //MCPE 1.2 (???)
			case 6: //MCPE 1.2.0.2 beta (???)
			case 4: //MCPE 1.1
				//TODO: check beds
			case 3: //MCPE 1.0
				$convertedLegacyExtraData = $this->deserializeLegacyExtraData($index, $chunkVersion);

				for($y = Chunk::MIN_SUBCHUNK_INDEX; $y <= Chunk::MAX_SUBCHUNK_INDEX; ++$y){
					if(($data = $this->db->get($index . self::TAG_SUBCHUNK_PREFIX . chr($y))) === false){
						continue;
					}

					$binaryStream = new BinaryStream($data);
					if($binaryStream->feof()){
						throw new CorruptedChunkException("Unexpected empty data for subchunk $y");
					}
					$subChunkVersion = $binaryStream->getByte();
					if($subChunkVersion < self::CURRENT_LEVEL_SUBCHUNK_VERSION){
						$hasBeenUpgraded = true;
					}

					switch($subChunkVersion){
						case 0:
						case 2: //these are all identical to version 0, but vanilla respects these so we should also
						case 3:
						case 4:
						case 5:
						case 6:
						case 7:
							try{
								$blocks = $binaryStream->get(4096);
								$blockData = $binaryStream->get(2048);

								if($chunkVersion < 4){
									$binaryStream->get(4096); //legacy light info, discard it
									$hasBeenUpgraded = true;
								}
							}catch(BinaryDataException $e){
								throw new CorruptedChunkException($e->getMessage(), 0, $e);
							}

							$storages = [SubChunkConverter::convertSubChunkXZY($blocks, $blockData)];
							if(isset($convertedLegacyExtraData[$y])){
								$storages[] = $convertedLegacyExtraData[$y];
							}

							$subChunks[$y] = new SubChunk(BlockLegacyIds::AIR << Block::INTERNAL_METADATA_BITS, $storages);
							break;
						case 1: //paletted v1, has a single blockstorage
							$storages = [$this->deserializePaletted($binaryStream)];
							if(isset($convertedLegacyExtraData[$y])){
								$storages[] = $convertedLegacyExtraData[$y];
							}
							$subChunks[$y] = new SubChunk(BlockLegacyIds::AIR << Block::INTERNAL_METADATA_BITS, $storages);
							break;
						case 8:
							//legacy extradata layers intentionally ignored because they aren't supposed to exist in v8
							$storageCount = $binaryStream->getByte();
							if($storageCount > 0){
								$storages = [];

								for($k = 0; $k < $storageCount; ++$k){
									$storages[] = $this->deserializePaletted($binaryStream);
								}
								$subChunks[$y] = new SubChunk(BlockLegacyIds::AIR << Block::INTERNAL_METADATA_BITS, $storages);
							}
							break;
						default:
							//TODO: set chunks read-only so the version on disk doesn't get overwritten
							throw new CorruptedChunkException("don't know how to decode LevelDB subchunk format version $subChunkVersion");
					}
				}

				if(($maps2d = $this->db->get($index . self::TAG_DATA_2D)) !== false){
					$binaryStream = new BinaryStream($maps2d);

					try{
						$binaryStream->get(512); //heightmap, discard it
						$biomeArray = new BiomeArray($binaryStream->get(256)); //never throws
					}catch(BinaryDataException $e){
						throw new CorruptedChunkException($e->getMessage(), 0, $e);
					}
				}
				break;
			case 2: // < MCPE 1.0
			case 1:
			case 0: //MCPE 0.9.0.1 beta (first version)
				$convertedLegacyExtraData = $this->deserializeLegacyExtraData($index, $chunkVersion);

				$legacyTerrain = $this->db->get($index . self::TAG_LEGACY_TERRAIN);
				if($legacyTerrain === false){
					throw new CorruptedChunkException("Missing expected LEGACY_TERRAIN tag for format version $chunkVersion");
				}
				$binaryStream = new BinaryStream($legacyTerrain);
				try{
					$fullIds = $binaryStream->get(32768);
					$fullData = $binaryStream->get(16384);
					$binaryStream->get(32768); //legacy light info, discard it
				}catch(BinaryDataException $e){
					throw new CorruptedChunkException($e->getMessage(), 0, $e);
				}

				for($yy = 0; $yy < 8; ++$yy){
					$storages = [SubChunkConverter::convertSubChunkFromLegacyColumn($fullIds, $fullData, $yy)];
					if(isset($convertedLegacyExtraData[$yy])){
						$storages[] = $convertedLegacyExtraData[$yy];
					}
					$subChunks[$yy] = new SubChunk(BlockLegacyIds::AIR << Block::INTERNAL_METADATA_BITS, $storages);
				}

				try{
					$binaryStream->get(256); //heightmap, discard it
					/** @var int[] $unpackedBiomeArray */
					$unpackedBiomeArray = unpack("N*", $binaryStream->get(1024)); //unpack() will never fail here
					$biomeArray = new BiomeArray(ChunkUtils::convertBiomeColors(array_values($unpackedBiomeArray))); //never throws
				}catch(BinaryDataException $e){
					throw new CorruptedChunkException($e->getMessage(), 0, $e);
				}
				break;
			default:
				//TODO: set chunks read-only so the version on disk doesn't get overwritten
				throw new CorruptedChunkException("don't know how to decode chunk format version $chunkVersion");
		}

		$nbt = new LittleEndianNbtSerializer();

		/** @var CompoundTag[] $entities */
		$entities = [];
		if(($entityData = $this->db->get($index . self::TAG_ENTITY)) !== false and $entityData !== ""){
			try{
				$entities = array_map(fn(TreeRoot $root) => $root->mustGetCompoundTag(), $nbt->readMultiple($entityData));
			}catch(NbtDataException $e){
				throw new CorruptedChunkException($e->getMessage(), 0, $e);
			}
		}

		/** @var CompoundTag[] $tiles */
		$tiles = [];
		if(($tileData = $this->db->get($index . self::TAG_BLOCK_ENTITY)) !== false and $tileData !== ""){
			try{
				$tiles = array_map(fn(TreeRoot $root) => $root->mustGetCompoundTag(), $nbt->readMultiple($tileData));
			}catch(NbtDataException $e){
				throw new CorruptedChunkException($e->getMessage(), 0, $e);
			}
		}

		$finalisationChr = $this->db->get($index . self::TAG_STATE_FINALISATION);
		if($finalisationChr !== false){
			$finalisation = ord($finalisationChr);
			$terrainPopulated = $finalisation === self::FINALISATION_DONE;
		}else{ //older versions didn't have this tag
			$terrainPopulated = true;
		}

		//TODO: tile ticks, biome states (?)

		$chunk = new Chunk(
			$subChunks,
			$biomeArray ?? BiomeArray::fill(BiomeIds::OCEAN), //TODO: maybe missing biomes should be an error?
			$terrainPopulated
		);

		if($hasBeenUpgraded){
			$chunk->setTerrainDirty(); //trigger rewriting chunk to disk if it was converted from an older format
		}

		return new ChunkData($chunk, $entities, $tiles);
	}

	public function saveChunk(int $chunkX, int $chunkZ, ChunkData $chunkData) : void{
		$idMap = LegacyBlockIdToStringIdMap::getInstance();
		$index = self::dimensionalChunkIndex($chunkX, $chunkZ, $this->dimensionId);

		$write = new \LevelDBWriteBatch();
		$write->put($index . self::TAG_VERSION, chr(self::CURRENT_LEVEL_CHUNK_VERSION));

		$chunk = $chunkData->getChunk();
		if($chunk->getTerrainDirtyFlag(Chunk::DIRTY_FLAG_BLOCKS)){
			$subChunks = $chunk->getSubChunks();
			foreach($subChunks as $y => $subChunk){
				$key = $index . self::TAG_SUBCHUNK_PREFIX . chr($y);
				if($subChunk->isEmptyAuthoritative()){
					$write->delete($key);
				}else{
					$subStream = new BinaryStream();
					$subStream->putByte(self::CURRENT_LEVEL_SUBCHUNK_VERSION);

					$layers = $subChunk->getBlockLayers();
					$subStream->putByte(count($layers));
					foreach($layers as $blocks){
						if($blocks->getBitsPerBlock() !== 0){
							$subStream->putByte($blocks->getBitsPerBlock() << 1);
							$subStream->put($blocks->getWordArray());
						}else{
							//TODO: we use these in-memory, but they aren't supported on disk by the game yet
							//polyfill them with a zero'd 1-bpb instead
							$subStream->putByte(1 << 1);
							$subStream->put(str_repeat("\x00", PalettedBlockArray::getExpectedWordArraySize(1)));
						}

						$palette = $blocks->getPalette();
						$subStream->putLInt(count($palette));
						$tags = [];
						foreach($palette as $p){
							$tags[] = new TreeRoot(CompoundTag::create()
								->setString("name", $idMap->legacyToString($p >> Block::INTERNAL_METADATA_BITS) ?? "minecraft:info_update")
								->setInt("oldid", $p >> Block::INTERNAL_METADATA_BITS) //PM only (debugging), vanilla doesn't have this
								->setShort("val", $p & Block::INTERNAL_METADATA_MASK));
						}

						$subStream->put((new LittleEndianNbtSerializer())->writeMultiple($tags));
					}

					$write->put($key, $subStream->getBuffer());
				}
			}
		}

		if($chunk->getTerrainDirtyFlag(Chunk::DIRTY_FLAG_BIOMES)){
			$write->put($index . self::TAG_DATA_2D, str_repeat("\x00", 512) . $chunk->getBiomeIdArray());
		}

		//TODO: use this properly
		$write->put($index . self::TAG_STATE_FINALISATION, chr($chunk->isPopulated() ? self::FINALISATION_DONE : self::FINALISATION_NEEDS_POPULATION));

		$this->writeTags($chunkData->getTileNBT(), $index . self::TAG_BLOCK_ENTITY, $write);
		$this->writeTags($chunkData->getEntityNBT(), $index . self::TAG_ENTITY, $write);

		$write->delete($index . self::TAG_DATA_2D_LEGACY);
		$write->delete($index . self::TAG_LEGACY_TERRAIN);

		$this->db->write($write);
	}

	/**
	 * @param CompoundTag[]      $targets
	 */
	private function writeTags(array $targets, string $index, \LevelDBWriteBatch $write) : void{
		if(count($targets) > 0){
			$nbt = new LittleEndianNbtSerializer();
			$write->put($index, $nbt->writeMultiple(array_map(fn(CompoundTag $tag) => new TreeRoot($tag), $targets)));
		}else{
			$write->delete($index);
		}
	}

	public static function dimensionalChunkIndex(int $chunkX, int $chunkZ, int $dimension) : string{
		return Binary::writeLInt($chunkX) . Binary::writeLInt($chunkZ).($dimension > 0 ? Binary::writeLInt($dimension) : '');
	}

	public function getDimensionId() : int {
		return $this->dimensionId;
	}
}