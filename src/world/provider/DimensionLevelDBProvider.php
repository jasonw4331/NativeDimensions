<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\world\provider;

use jasonw4331\NativeDimensions\world\data\DimensionalBedrockWorldData;
use LevelDBException;
use Logger;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\data\bedrock\block\BlockStateDeserializeException;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\utils\Binary;
use pocketmine\utils\BinaryDataException;
use pocketmine\utils\BinaryStream;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\BaseWorldProvider;
use pocketmine\world\format\io\ChunkData;
use pocketmine\world\format\io\ChunkUtils;
use pocketmine\world\format\io\exception\CorruptedChunkException;
use pocketmine\world\format\io\exception\CorruptedWorldException;
use pocketmine\world\format\io\exception\UnsupportedWorldFormatException;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\leveldb\ChunkDataKey;
use pocketmine\world\format\io\leveldb\ChunkVersion;
use pocketmine\world\format\io\leveldb\LevelDB;
use pocketmine\world\format\io\leveldb\SubChunkVersion;
use pocketmine\world\format\io\WorldData;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use pocketmine\world\WorldCreationOptions;
use Symfony\Component\Filesystem\Path;
use function array_map;
use function array_values;
use function chr;
use function count;
use function defined;
use function extension_loaded;
use function file_exists;
use function mkdir;
use function ord;
use function str_repeat;
use function trim;
use function unpack;
use const LEVELDB_ZLIB_RAW_COMPRESSION;

class DimensionLevelDBProvider extends LevelDB{

	private const CAVES_CLIFFS_EXPERIMENTAL_SUBCHUNK_KEY_OFFSET = 4;

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
	 * @throws LevelDBException
	 */
	private static function createDB(string $path) : \LevelDB{
		return new \LevelDB(Path::join($path, "db"), [
			"compression" => LEVELDB_ZLIB_RAW_COMPRESSION,
			"block_size" => 64 * 1024 //64KB, big enough for most chunks
		]);
	}

	public function __construct(string $path, Logger $logger, int $dimension = 0, ?\LevelDB $db = null){
		self::checkForLevelDBExtension();

		if($dimension < 0){
			throw new LevelDBException("Dimension cannot be saved with negative index");
		}elseif($dimension > 0 && $db === null){
			throw new LevelDBException("Dimension cannot be generated without overworld data");
		}

		$this->dimensionId = $dimension;
		BaseWorldProvider::__construct($path, $logger);

		try{
			$this->db = $db ?? self::createDB($path);
		}catch(LevelDBException $e){
			//we can't tell the difference between errors caused by bad permissions and actual corruption :(
			throw new CorruptedWorldException(trim($e->getMessage()), 0, $e);
		}
	}

	protected function loadLevelData() : WorldData{
		$data = new DimensionalBedrockWorldData(Path::join($this->getPath(), "level.dat"));
		if($this->dimensionId > 0){
			$data->setGenerator($this->dimensionId === DimensionIds::NETHER ? "nether" : "ender");
			$data->setName($data->getName() . ($this->dimensionId === DimensionIds::NETHER ? " nether" : " end"));
		}
		return $data;
	}

	public function getDimensionId() : int{
		return $this->dimensionId;
	}

	public function getWorldMinY() : int{
		return -64; // TODO: change per dimension
	}

	public function getWorldMaxY() : int{
		return 320; // TODO: change per dimension
	}

	public static function generate(string $path, string $name, WorldCreationOptions $options, int $dimension = 0) : void{
		if($dimension !== 0){
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
	protected function deserializeBlockPalette(BinaryStream $stream, Logger $logger) : PalettedBlockArray{
		$bitsPerBlock = $stream->getByte() >> 1;

		try{
			$words = $stream->get(PalettedBlockArray::getExpectedWordArraySize($bitsPerBlock));
		}catch(\InvalidArgumentException $e){
			throw new CorruptedChunkException("Failed to deserialize paletted storage: " . $e->getMessage(), 0, $e);
		}
		$nbt = new LittleEndianNbtSerializer();
		$palette = [];

		$paletteSize = $bitsPerBlock === 0 ? 1 : $stream->getLInt();

		for($i = 0; $i < $paletteSize; ++$i){
			try{
				$offset = $stream->getOffset();
				$blockStateNbt = $nbt->read($stream->getBuffer(), $offset)->mustGetCompoundTag();
				$stream->setOffset($offset);
			}catch(NbtDataException $e){
				//NBT borked, unrecoverable
				throw new CorruptedChunkException("Invalid blockstate NBT at offset $i in paletted storage: " . $e->getMessage(), 0, $e);
			}

			//TODO: remember data for unknown states so we can implement them later
			try{
				$blockStateData = $this->blockDataUpgrader->upgradeBlockStateNbt($blockStateNbt);
			}catch(BlockStateDeserializeException $e){
				//while not ideal, this is not a fatal error
				$logger->error("Failed to upgrade blockstate: " . $e->getMessage() . " offset $i in palette, blockstate NBT: " . $blockStateNbt->toString());
				$palette[] = $this->blockStateDeserializer->deserialize(GlobalBlockStateHandlers::getUnknownBlockStateData());
				continue;
			}
			try{
				$palette[] = $this->blockStateDeserializer->deserialize($blockStateData);
			}catch(BlockStateDeserializeException $e){
				$logger->error("Failed to deserialize blockstate: " . $e->getMessage() . " offset $i in palette, blockstate NBT: " . $blockStateNbt->toString());
				$palette[] = $this->blockStateDeserializer->deserialize(GlobalBlockStateHandlers::getUnknownBlockStateData());
			}
		}

		//TODO: exceptions
		return PalettedBlockArray::fromData($bitsPerBlock, $words, $palette);
	}

	private function serializeBlockPalette(BinaryStream $stream, PalettedBlockArray $blocks) : void{
		$stream->putByte($blocks->getBitsPerBlock() << 1);
		$stream->put($blocks->getWordArray());

		$palette = $blocks->getPalette();
		if($blocks->getBitsPerBlock() !== 0){
			$stream->putLInt(count($palette));
		}
		$tags = [];
		foreach($palette as $p){
			$tags[] = new TreeRoot($this->blockStateSerializer->serialize($p)->toNbt());
		}

		$stream->put((new LittleEndianNbtSerializer())->writeMultiple($tags));
	}

	/**
	 * @throws CorruptedChunkException
	 */
	private static function getExpected3dBiomesCount(int $chunkVersion) : int{
		return match (true) {
			$chunkVersion >= ChunkVersion::v1_18_30 => 24,
			$chunkVersion >= ChunkVersion::v1_18_0_25_beta => 25,
			$chunkVersion >= ChunkVersion::v1_18_0_24_beta => 32,
			$chunkVersion >= ChunkVersion::v1_18_0_22_beta => 65,
			$chunkVersion >= ChunkVersion::v1_17_40_20_beta_experimental_caves_cliffs => 32,
			default => throw new CorruptedChunkException("Chunk version $chunkVersion should not have 3D biomes")
		};
	}

	/**
	 * @throws CorruptedChunkException
	 */
	private static function deserializeBiomePalette(BinaryStream $stream, int $bitsPerBlock) : PalettedBlockArray{
		try{
			$words = $stream->get(PalettedBlockArray::getExpectedWordArraySize($bitsPerBlock));
		}catch(\InvalidArgumentException $e){
			throw new CorruptedChunkException("Failed to deserialize paletted biomes: " . $e->getMessage(), 0, $e);
		}
		$palette = [];
		$paletteSize = $bitsPerBlock === 0 ? 1 : $stream->getLInt();

		for($i = 0; $i < $paletteSize; ++$i){
			$palette[] = $stream->getLInt();
		}

		//TODO: exceptions
		return PalettedBlockArray::fromData($bitsPerBlock, $words, $palette);
	}

	private static function serializeBiomePalette(BinaryStream $stream, PalettedBlockArray $biomes) : void{
		$stream->putByte($biomes->getBitsPerBlock() << 1);
		$stream->put($biomes->getWordArray());

		$palette = $biomes->getPalette();
		if($biomes->getBitsPerBlock() !== 0){
			$stream->putLInt(count($palette));
		}
		foreach($palette as $p){
			$stream->putLInt($p);
		}
	}

	/**
	 * @return PalettedBlockArray[]
	 * @phpstan-return array<int, PalettedBlockArray>
	 * @throws CorruptedChunkException
	 */
	private static function deserialize3dBiomes(BinaryStream $stream, int $chunkVersion, Logger $logger) : array{
		$previous = null;
		$result = [];
		$nextIndex = Chunk::MIN_SUBCHUNK_INDEX;

		$expectedCount = self::getExpected3dBiomesCount($chunkVersion);
		for($i = 0; $i < $expectedCount; ++$i){
			try{
				$bitsPerBlock = $stream->getByte() >> 1;
				if($bitsPerBlock === 127){
					if($previous === null){
						throw new CorruptedChunkException("Serialized biome palette $i has no previous palette to copy from");
					}
					$decoded = clone $previous;
				}else{
					$decoded = self::deserializeBiomePalette($stream, $bitsPerBlock);
				}
				$previous = $decoded;
				if($nextIndex <= Chunk::MAX_SUBCHUNK_INDEX){ //older versions wrote additional superfluous biome palettes
					$result[$nextIndex++] = $decoded;
				}
			}catch(BinaryDataException $e){
				throw new CorruptedChunkException("Failed to deserialize biome palette $i: " . $e->getMessage(), 0, $e);
			}
		}
		if(!$stream->feof()){
			//maybe bad output produced by a third-party conversion tool like Chunker
			$logger->error("Unexpected trailing data after 3D biomes data");
		}

		return $result;
	}

	private static function serialize3dBiomes(BinaryStream $stream, Chunk $chunk) : void{
		//TODO: the server-side min/max may not coincide with the world storage min/max - we may need additional logic to handle this
		for($y = Chunk::MIN_SUBCHUNK_INDEX; $y <= Chunk::MAX_SUBCHUNK_INDEX; $y++){
			//TODO: is it worth trying to use the previous palette if it's the same as the current one? vanilla supports
			//this, but it's not clear if it's worth the effort to implement.
			self::serializeBiomePalette($stream, $chunk->getSubChunk($y)->getBiomeArray());
		}
	}

	private function readVersion(int $chunkX, int $chunkZ) : ?int{
		$index = self::dimensionalChunkIndex($chunkX, $chunkZ, $this->dimensionId);
		$chunkVersionRaw = $this->db->get($index . ChunkDataKey::NEW_VERSION);
		if($chunkVersionRaw === false){
			$chunkVersionRaw = $this->db->get($index . ChunkDataKey::OLD_VERSION);
			if($chunkVersionRaw === false){
				return null;
			}
		}

		return ord($chunkVersionRaw);
	}

	/**
	 * Deserializes terrain data stored in the 0.9 full-chunk format into subchunks.
	 *
	 * @return SubChunk[]
	 * @phpstan-return array<int, SubChunk>
	 * @throws CorruptedWorldException
	 */
	private function deserializeLegacyTerrainData(string $index, int $chunkVersion, Logger $logger) : array{
		$convertedLegacyExtraData = $this->deserializeLegacyExtraData($index, $chunkVersion, $logger);

		$legacyTerrain = $this->db->get($index . ChunkDataKey::LEGACY_TERRAIN);
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

		try{
			$binaryStream->get(256); //heightmap, discard it
			/** @var int[] $unpackedBiomeArray */
			$unpackedBiomeArray = unpack("N*", $binaryStream->get(1024)); //unpack() will never fail here
			$biomes3d = ChunkUtils::extrapolate3DBiomes(ChunkUtils::convertBiomeColors(array_values($unpackedBiomeArray))); //never throws
		}catch(BinaryDataException $e){
			throw new CorruptedChunkException($e->getMessage(), 0, $e);
		}
		if(!$binaryStream->feof()){
			$logger->error("Unexpected trailing data in legacy terrain data");
		}

		$subChunks = [];
		for($yy = 0; $yy < 8; ++$yy){
			$storages = [$this->palettizeLegacySubChunkFromColumn($fullIds, $fullData, $yy)];
			if(isset($convertedLegacyExtraData[$yy])){
				$storages[] = $convertedLegacyExtraData[$yy];
			}
			$subChunks[$yy] = new SubChunk(BlockTypeIds::AIR << Block::INTERNAL_STATE_DATA_BITS, $storages, clone $biomes3d);
		}

		//make sure extrapolated biomes get filled in correctly
		for($yy = Chunk::MIN_SUBCHUNK_INDEX; $yy <= Chunk::MAX_SUBCHUNK_INDEX; ++$yy){
			if(!isset($subChunks[$yy])){
				$subChunks[$yy] = new SubChunk(BlockTypeIds::AIR << Block::INTERNAL_STATE_DATA_BITS, [], clone $biomes3d);
			}
		}

		return $subChunks;
	}

	/**
	 * Deserializes a subchunk stored in the legacy non-paletted format used from 1.0 until 1.2.13.
	 */
	private function deserializeNonPalettedSubChunkData(BinaryStream $binaryStream, int $chunkVersion, ?PalettedBlockArray $convertedLegacyExtraData, PalettedBlockArray $biomePalette, Logger $logger) : SubChunk{
		try{
			$blocks = $binaryStream->get(4096);
			$blockData = $binaryStream->get(2048);
		}catch(BinaryDataException $e){
			throw new CorruptedChunkException($e->getMessage(), 0, $e);
		}

		if($chunkVersion < ChunkVersion::v1_1_0){
			try{
				$binaryStream->get(4096); //legacy light info, discard it
				if(!$binaryStream->feof()){
					$logger->error("Unexpected trailing data in legacy subchunk data");
				}
			}catch(BinaryDataException $e){
				$logger->error("Failed to read legacy subchunk light info: " . $e->getMessage());
			}
		}

		$storages = [$this->palettizeLegacySubChunkXZY($blocks, $blockData)];
		if($convertedLegacyExtraData !== null){
			$storages[] = $convertedLegacyExtraData;
		}

		return new SubChunk(BlockTypeIds::AIR << Block::INTERNAL_STATE_DATA_BITS, $storages, $biomePalette);
	}

	/**
	 * Deserializes subchunk data stored under a subchunk LevelDB key.
	 *
	 * @throws CorruptedChunkException
	 * @see ChunkDataKey::SUBCHUNK
	 */
	private function deserializeSubChunkData(BinaryStream $binaryStream, int $chunkVersion, int $subChunkVersion, ?PalettedBlockArray $convertedLegacyExtraData, PalettedBlockArray $biomePalette, Logger $logger) : SubChunk{
		switch($subChunkVersion){
			case SubChunkVersion::CLASSIC:
			case SubChunkVersion::CLASSIC_BUG_2: //these are all identical to version 0, but vanilla respects these so we should also
			case SubChunkVersion::CLASSIC_BUG_3:
			case SubChunkVersion::CLASSIC_BUG_4:
			case SubChunkVersion::CLASSIC_BUG_5:
			case SubChunkVersion::CLASSIC_BUG_6:
			case SubChunkVersion::CLASSIC_BUG_7:
				return $this->deserializeNonPalettedSubChunkData($binaryStream, $chunkVersion, $convertedLegacyExtraData, $biomePalette, $logger);
			case SubChunkVersion::PALETTED_SINGLE:
				$storages = [$this->deserializeBlockPalette($binaryStream, $logger)];
				if($convertedLegacyExtraData !== null){
					$storages[] = $convertedLegacyExtraData;
				}
				return new SubChunk(BlockTypeIds::AIR << Block::INTERNAL_STATE_DATA_BITS, $storages, $biomePalette);
			case SubChunkVersion::PALETTED_MULTI:
			case SubChunkVersion::PALETTED_MULTI_WITH_OFFSET:
				//legacy extradata layers intentionally ignored because they aren't supposed to exist in v8

				$storageCount = $binaryStream->getByte();
				if($subChunkVersion >= SubChunkVersion::PALETTED_MULTI_WITH_OFFSET){
					//height ignored; this seems pointless since this is already in the key anyway
					$binaryStream->getByte();
				}

				$storages = [];
				for($k = 0; $k < $storageCount; ++$k){
					$storages[] = $this->deserializeBlockPalette($binaryStream, $logger);
				}
				return new SubChunk(BlockTypeIds::AIR << Block::INTERNAL_STATE_DATA_BITS, $storages, $biomePalette);
			default:
				//this should never happen - an unsupported chunk appearing in a supported world is a sign of corruption
				throw new CorruptedChunkException("don't know how to decode LevelDB subchunk format version $subChunkVersion");
		}
	}

	private static function hasOffsetCavesAndCliffsSubChunks(int $chunkVersion) : bool{
		return $chunkVersion >= ChunkVersion::v1_16_220_50_unused && $chunkVersion <= ChunkVersion::v1_16_230_50_unused;
	}

	/**
	 * Deserializes any subchunks stored under subchunk LevelDB keys, upgrading them to the current format if necessary.
	 *
	 * @param PalettedBlockArray[] $convertedLegacyExtraData
	 * @param PalettedBlockArray[] $biomeArrays
	 *
	 * @phpstan-param array<int, PalettedBlockArray> $convertedLegacyExtraData
	 * @phpstan-param array<int, PalettedBlockArray> $biomeArrays
	 * @phpstan-param-out bool                       $hasBeenUpgraded
	 *
	 * @return SubChunk[]
	 * @phpstan-return array<int, SubChunk>
	 */
	private function deserializeAllSubChunkData(string $index, int $chunkVersion, bool &$hasBeenUpgraded, array $convertedLegacyExtraData, array $biomeArrays, Logger $logger) : array{
		$subChunks = [];

		$subChunkKeyOffset = self::hasOffsetCavesAndCliffsSubChunks($chunkVersion) ? self::CAVES_CLIFFS_EXPERIMENTAL_SUBCHUNK_KEY_OFFSET : 0;
		for($y = Chunk::MIN_SUBCHUNK_INDEX; $y <= Chunk::MAX_SUBCHUNK_INDEX; ++$y){
			if(($data = $this->db->get($index . ChunkDataKey::SUBCHUNK . chr($y + $subChunkKeyOffset))) === false){
				$subChunks[$y] = new SubChunk(BlockTypeIds::AIR << Block::INTERNAL_STATE_DATA_BITS, [], $biomeArrays[$y]);
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

			$subChunks[$y] = $this->deserializeSubChunkData(
				$binaryStream,
				$chunkVersion,
				$subChunkVersion,
				$convertedLegacyExtraData[$y] ?? null,
				$biomeArrays[$y],
				new \PrefixedLogger($logger, "Subchunk y=$y v$subChunkVersion")
			);
		}

		return $subChunks;
	}

	/**
	 * Deserializes any available biome data into an array of paletted biomes. Old 2D biomes are extrapolated to 3D.
	 *
	 * @return PalettedBlockArray[]
	 * @phpstan-return array<int, PalettedBlockArray>
	 */
	private function deserializeBiomeData(string $index, int $chunkVersion, Logger $logger) : array{
		$biomeArrays = [];
		if(($maps2d = $this->db->get($index . ChunkDataKey::HEIGHTMAP_AND_2D_BIOMES)) !== false){
			$binaryStream = new BinaryStream($maps2d);

			try{
				$binaryStream->get(512); //heightmap, discard it
				$biomes3d = ChunkUtils::extrapolate3DBiomes($binaryStream->get(256)); //never throws
				if(!$binaryStream->feof()){
					$logger->error("Unexpected trailing data after 2D biome data");
				}
			}catch(BinaryDataException $e){
				throw new CorruptedChunkException($e->getMessage(), 0, $e);
			}
			for($i = Chunk::MIN_SUBCHUNK_INDEX; $i <= Chunk::MAX_SUBCHUNK_INDEX; ++$i){
				$biomeArrays[$i] = clone $biomes3d;
			}
		}elseif(($maps3d = $this->db->get($index . ChunkDataKey::HEIGHTMAP_AND_3D_BIOMES)) !== false){
			$binaryStream = new BinaryStream($maps3d);

			try{
				$binaryStream->get(512);
				$biomeArrays = self::deserialize3dBiomes($binaryStream, $chunkVersion, $logger);
			}catch(BinaryDataException $e){
				throw new CorruptedChunkException($e->getMessage(), 0, $e);
			}
		}else{
			$logger->error("Missing biome data, using default ocean biome");
			for($i = Chunk::MIN_SUBCHUNK_INDEX; $i <= Chunk::MAX_SUBCHUNK_INDEX; ++$i){
				$biomeArrays[$i] = new PalettedBlockArray(BiomeIds::OCEAN); //polyfill
			}
		}

		return $biomeArrays;
	}

	/**
	 * @throws CorruptedChunkException
	 */
	public function loadChunk(int $chunkX, int $chunkZ) : ?ChunkData{
		$index = self::dimensionalChunkIndex($chunkX, $chunkZ, $this->dimensionId);

		$chunkVersion = $this->readVersion($chunkX, $chunkZ);
		if($chunkVersion === null){
			//TODO: this might be a slightly-corrupted chunk with a missing version field
			return null;
		}

		$logger = new \PrefixedLogger($this->logger, "Loading chunk x=$chunkX z=$chunkZ v$chunkVersion");

		$hasBeenUpgraded = $chunkVersion < self::CURRENT_LEVEL_CHUNK_VERSION;

		switch($chunkVersion){
			case ChunkVersion::v1_18_30:
			case ChunkVersion::v1_18_0_25_beta:
			case ChunkVersion::v1_18_0_24_unused:
			case ChunkVersion::v1_18_0_24_beta:
			case ChunkVersion::v1_18_0_22_unused:
			case ChunkVersion::v1_18_0_22_beta:
			case ChunkVersion::v1_18_0_20_unused:
			case ChunkVersion::v1_18_0_20_beta:
			case ChunkVersion::v1_17_40_unused:
			case ChunkVersion::v1_17_40_20_beta_experimental_caves_cliffs:
			case ChunkVersion::v1_17_30_25_unused:
			case ChunkVersion::v1_17_30_25_beta_experimental_caves_cliffs:
			case ChunkVersion::v1_17_30_23_unused:
			case ChunkVersion::v1_17_30_23_beta_experimental_caves_cliffs:
			case ChunkVersion::v1_16_230_50_unused:
			case ChunkVersion::v1_16_230_50_beta_experimental_caves_cliffs:
			case ChunkVersion::v1_16_220_50_unused:
			case ChunkVersion::v1_16_220_50_beta_experimental_caves_cliffs:
			case ChunkVersion::v1_16_210:
			case ChunkVersion::v1_16_100_57_beta:
			case ChunkVersion::v1_16_100_52_beta:
			case ChunkVersion::v1_16_0:
			case ChunkVersion::v1_16_0_51_beta:
				//TODO: check walls
			case ChunkVersion::v1_12_0_unused2:
			case ChunkVersion::v1_12_0_unused1:
			case ChunkVersion::v1_12_0_4_beta:
			case ChunkVersion::v1_11_1:
			case ChunkVersion::v1_11_0_4_beta:
			case ChunkVersion::v1_11_0_3_beta:
			case ChunkVersion::v1_11_0_1_beta:
			case ChunkVersion::v1_9_0:
			case ChunkVersion::v1_8_0:
			case ChunkVersion::v1_2_13:
			case ChunkVersion::v1_2_0:
			case ChunkVersion::v1_2_0_2_beta:
			case ChunkVersion::v1_1_0_converted_from_console:
			case ChunkVersion::v1_1_0:
				//TODO: check beds
			case ChunkVersion::v1_0_0:
				$convertedLegacyExtraData = $this->deserializeLegacyExtraData($index, $chunkVersion, $logger);
				$biomeArrays = $this->deserializeBiomeData($index, $chunkVersion, $logger);
				$subChunks = $this->deserializeAllSubChunkData($index, $chunkVersion, $hasBeenUpgraded, $convertedLegacyExtraData, $biomeArrays, $logger);
				break;
			case ChunkVersion::v0_9_5:
			case ChunkVersion::v0_9_2:
			case ChunkVersion::v0_9_0:
				$subChunks = $this->deserializeLegacyTerrainData($index, $chunkVersion, $logger);
				break;
			default:
				throw new CorruptedChunkException("don't know how to decode chunk format version $chunkVersion");
		}

		$nbt = new LittleEndianNbtSerializer();

		/** @var CompoundTag[] $entities */
		$entities = [];
		if(($entityData = $this->db->get($index . ChunkDataKey::ENTITIES)) !== false && $entityData !== ""){
			try{
				$entities = array_map(fn(TreeRoot $root) => $root->mustGetCompoundTag(), $nbt->readMultiple($entityData));
			}catch(NbtDataException $e){
				throw new CorruptedChunkException($e->getMessage(), 0, $e);
			}
		}

		/** @var CompoundTag[] $tiles */
		$tiles = [];
		if(($tileData = $this->db->get($index . ChunkDataKey::BLOCK_ENTITIES)) !== false && $tileData !== ""){
			try{
				$tiles = array_map(fn(TreeRoot $root) => $root->mustGetCompoundTag(), $nbt->readMultiple($tileData));
			}catch(NbtDataException $e){
				throw new CorruptedChunkException($e->getMessage(), 0, $e);
			}
		}

		$finalisationChr = $this->db->get($index . ChunkDataKey::FINALIZATION);
		if($finalisationChr !== false){
			$finalisation = ord($finalisationChr);
			$terrainPopulated = $finalisation === self::FINALISATION_DONE;
		}else{ //older versions didn't have this tag
			$terrainPopulated = true;
		}

		//TODO: tile ticks, biome states (?)

		$chunk = new Chunk(
			$subChunks,
			$terrainPopulated
		);

		if($hasBeenUpgraded){
			$logger->debug("Flagging chunk as dirty due to upgraded data");
			$chunk->setTerrainDirty(); //trigger rewriting chunk to disk if it was converted from an older format
		}

		return new ChunkData($chunk, $entities, $tiles);
	}

	public function saveChunk(int $chunkX, int $chunkZ, ChunkData $chunkData) : void{
		$index = self::dimensionalChunkIndex($chunkX, $chunkZ, $this->dimensionId);

		$write = new \LevelDBWriteBatch();

		$write->put($index . ChunkDataKey::NEW_VERSION, chr(self::CURRENT_LEVEL_CHUNK_VERSION));

		$chunk = $chunkData->getChunk();

		if($chunk->getTerrainDirtyFlag(Chunk::DIRTY_FLAG_BLOCKS)){
			$subChunks = $chunk->getSubChunks();

			foreach($subChunks as $y => $subChunk){
				$key = $index . ChunkDataKey::SUBCHUNK . chr($y);
				if($subChunk->isEmptyAuthoritative()){
					$write->delete($key);
				}else{
					$subStream = new BinaryStream();
					$subStream->putByte(self::CURRENT_LEVEL_SUBCHUNK_VERSION);

					$layers = $subChunk->getBlockLayers();
					$subStream->putByte(count($layers));
					foreach($layers as $blocks){
						$this->serializeBlockPalette($subStream, $blocks);
					}

					$write->put($key, $subStream->getBuffer());
				}
			}
		}

		if($chunk->getTerrainDirtyFlag(Chunk::DIRTY_FLAG_BIOMES)){
			$write->delete($index . ChunkDataKey::HEIGHTMAP_AND_2D_BIOMES);
			$stream = new BinaryStream();
			$stream->put(str_repeat("\x00", 512)); //fake heightmap
			self::serialize3dBiomes($stream, $chunk);
			$write->put($index . ChunkDataKey::HEIGHTMAP_AND_3D_BIOMES, $stream->getBuffer());
		}

		//TODO: use this properly
		$write->put($index . ChunkDataKey::FINALIZATION, chr($chunk->isPopulated() ? self::FINALISATION_DONE : self::FINALISATION_NEEDS_POPULATION));

		$this->writeTags($chunkData->getTileNBT(), $index . ChunkDataKey::BLOCK_ENTITIES, $write);
		$this->writeTags($chunkData->getEntityNBT(), $index . ChunkDataKey::ENTITIES, $write);

		$write->delete($index . ChunkDataKey::HEIGHTMAP_AND_2D_BIOME_COLORS);
		$write->delete($index . ChunkDataKey::LEGACY_TERRAIN);

		$this->db->write($write);
	}

	/**
	 * @param CompoundTag[] $targets
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
		return Binary::writeLInt($chunkX) . Binary::writeLInt($chunkZ) . ($dimension > 0 ? Binary::writeLInt($dimension) : '');
	}

}
