<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\network;

use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializerContext;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\utils\BinaryStream;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\PalettedBlockArray;
use function array_key_first;
use function array_key_last;
use function count;
use function substr;

final class DimensionSpecificCompressor implements Compressor{

	/**
	 * @param Compressor $compressor
	 * @param DimensionIds::* $dimension_id
	 * @return Compressor
	 */
	public static function fromDimensionId(Compressor $compressor, int $dimension_id) : Compressor{
		return match($dimension_id){
			DimensionIds::NETHER => new self($compressor, 0, 7),
			DimensionIds::THE_END => new self($compressor, 0, 15),
			default => $compressor
		};
	}

	public function __construct(
		readonly private Compressor $inner,
		readonly private int $min_sub_chunk_index,
		readonly private int $max_sub_chunk_index
	){}

	public function getCompressionThreshold() : ?int{
		return $this->inner->getCompressionThreshold();
	}

	public function decompress(string $payload) : string{
		return $this->inner->decompress($payload);
	}

	public function compress(string $payload) : string{
		$context = new PacketSerializerContext(TypeConverter::getInstance()->getItemTypeDictionary());
		$pool = PacketPool::getInstance();
		foreach(PacketBatch::decodeRaw(new BinaryStream($payload)) as $buffer){
			$packet = $pool->getPacket($buffer);
			if($packet instanceof LevelChunkPacket){
				$packet->decode(PacketSerializer::decoder($buffer, 0, $context));
				$packet = $this->modifyPacket($packet, $context);

				$stream = new BinaryStream();
				PacketBatch::encodePackets($stream, $context, [$packet]);
				$payload = $stream->getBuffer();
			}
		}
		return $this->inner->compress($payload);
	}

	private function modifyPacket(LevelChunkPacket $packet, PacketSerializerContext $context) : LevelChunkPacket{
		$original_sub_chunk_count = $packet->getSubChunkCount();
		if($original_sub_chunk_count === 0){
			return $packet;
		}

		$stream = PacketSerializer::decoder($packet->getExtraPayload(), 0, $context);

		$sub_chunks = [];
		for($i = Chunk::MIN_SUBCHUNK_INDEX, $max = $original_sub_chunk_count; $max-- > 0; $i++){
			$begin = $stream->getOffset();
			$this->readSubChunk($stream);
			$end = $stream->getOffset();
			$sub_chunks[$i] = [$begin, $end - 1];
		}

		$biomes = [];
		for($i = Chunk::MIN_SUBCHUNK_INDEX; $i <= Chunk::MAX_SUBCHUNK_INDEX; $i++){
			$begin = $stream->getOffset();
			$this->readBiome($stream);
			$end = $stream->getOffset();
			$biomes[$i] = [$begin, $end - 1];
		}

		// set payload to contain only a slice of the chunks
		$resulting_chunks = [];
		for($i = $this->min_sub_chunk_index; $i <= $this->max_sub_chunk_index && isset($sub_chunks[$i]); $i++){
			$resulting_chunks[] = $sub_chunks[$i];
		}
		$begin = $resulting_chunks[array_key_first($resulting_chunks)][0];
		$end = $resulting_chunks[array_key_last($resulting_chunks)][1];
		$payload = substr($packet->getExtraPayload(), $begin, 1 + ($end - $begin));

		// set payload to contain only a slice of the biomes
		$resulting_biomes = [];
		for($i = $this->min_sub_chunk_index; $i <= $this->max_sub_chunk_index; $i++){
			$resulting_biomes[] = $biomes[$i];
		}
		$begin = $resulting_biomes[array_key_first($resulting_biomes)][0];
		$end = $resulting_biomes[array_key_last($resulting_biomes)][1];
		$payload .= substr($packet->getExtraPayload(), $begin, 1 + ($end - $begin));

		// add remaining payload (containing tiles)
		$end = $biomes[array_key_last($biomes)][1];
		$payload .= substr($packet->getExtraPayload(), $end + 1);

		return LevelChunkPacket::create(
			$packet->getChunkPosition(),
			count($resulting_chunks),
			$packet->isClientSubChunkRequestEnabled(),
			$packet->getUsedBlobHashes(),
			$payload
		);
	}

	private function readSubChunk(PacketSerializer $stream) : void{
		$stream->getByte(); // version
		$layers_c = $stream->getByte();
		for($i = 0; $i < $layers_c; $i++){
			$byte = $stream->getByte();
			$bitsPerBlock = $byte >> 1;
			$persistentBlockStates = ($byte & 1) === 0;
			$stream->get(PalettedBlockArray::getExpectedWordArraySize($bitsPerBlock));
			if($bitsPerBlock !== 0){
				$palette_c = $stream->getUnsignedVarInt() >> 1;
			}else{
				$palette_c = 1;
			}
			if($persistentBlockStates){
				$nbtSerializer = new NetworkNbtSerializer();
				$remaining = substr($stream->getBuffer(), $stream->getOffset());
				$nbt_offset = 0;
				for($j = 0; $j < $palette_c; ++$j){
					$nbtSerializer->read($remaining, $nbt_offset, 512);
				}
				$stream->get($nbt_offset);
			}else{
				for($k = 0; $k < $palette_c; ++$k){
					$stream->getUnsignedVarInt();
				}
			}
		}
	}

	private function readBiome(PacketSerializer $stream) : void{
		$biomePaletteBitsPerBlock = $stream->getByte() >> 1;
		$stream->get(PalettedBlockArray::getExpectedWordArraySize($biomePaletteBitsPerBlock));

		if($biomePaletteBitsPerBlock !== 0){
			$palette_c = $stream->getUnsignedVarInt() >> 1;
		}else{
			$palette_c = 1;
		}

		for($i = 0; $i < $palette_c; $i++){
			$stream->getUnsignedVarInt();
		}
	}
}