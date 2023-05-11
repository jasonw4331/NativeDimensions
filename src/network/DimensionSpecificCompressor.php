<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\network;

use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\convert\GlobalItemTypeDictionary;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializerContext;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\serializer\ChunkSerializer;
use function assert;
use function substr;
use function substr_replace;

final class DimensionSpecificCompressor implements Compressor{

	private int $biome_palettes_to_reduce;

	/**
	 * @param Compressor $inner
	 * @param DimensionIds::* $dimension_id
	 */
	public function __construct(
		private Compressor $inner,
		int $dimension_id
	){
		$this->biome_palettes_to_reduce = match($dimension_id){ // values relative to 24
			DimensionIds::NETHER => 16,
			DimensionIds::THE_END => 8,
			default => 0
		};
	}

	public function getCompressionThreshold() : ?int{
		return $this->inner->getCompressionThreshold();
	}

	public function decompress(string $payload) : string{
		return $this->inner->decompress($payload);
	}

	public function compress(string $payload) : string{
		$context = new PacketSerializerContext(GlobalItemTypeDictionary::getInstance()->getDictionary());
		foreach((new PacketBatch($payload))->getPackets(PacketPool::getInstance(), $context, 1) as [$packet, $buffer]){
			if($packet instanceof LevelChunkPacket){
				$packet->decode(PacketSerializer::decoder($buffer, 0, $context));
				$payload_with_reduced_biomes = $this->reduceBiomePalettesInPayload($context, $packet->getSubChunkCount() - ChunkSerializer::LOWER_PADDING_SIZE, $packet->getExtraPayload(), $this->biome_palettes_to_reduce);
				$payload = PacketBatch::fromPackets($context, LevelChunkPacket::create(
					$packet->getChunkPosition(),
					$packet->getSubChunkCount() - ChunkSerializer::LOWER_PADDING_SIZE,
					$packet->isClientSubChunkRequestEnabled(),
					$packet->getUsedBlobHashes(),
					substr($payload_with_reduced_biomes, ChunkSerializer::LOWER_PADDING_SIZE * 2)
				))->getBuffer();
			}
		}
		return $this->inner->compress($payload);
	}

	/**
	 * This method reduces the number of biome palettes in a chunk's extra payload relative to this value:
	 * https://github.com/pmmp/PocketMine-MP/blob/38d6284671e8b657ba557e765a6c29b24a7705f5/src/network/mcpe/serializer/ChunkSerializer.php#L81
	 *
	 * @param PacketSerializerContext $context
	 * @param int $sub_chunk_count
	 * @param string $payload
	 * @param int $reduction
	 * @return string
	 */
	private function reduceBiomePalettesInPayload(PacketSerializerContext $context, int $sub_chunk_count, string $payload, int $reduction) : string{
		$extra_payload = PacketSerializer::decoder($payload, 0, $context);
		$extra_payload->get(ChunkSerializer::LOWER_PADDING_SIZE * 2); // undo fake subchunks for negative space

		static $BITS_PER_BLOCK_TO_WORD_ARRAY_LENGTH = [0, 512, 1024, 1640, 2048, 2732, 3280];

		for($y = 0; $y < $sub_chunk_count; ++$y){
			$version = $extra_payload->getByte();
			assert($version === 8);

			$layers = $extra_payload->getByte();
			for($i = 0; $i < $layers; ++$i){
				$byte = $extra_payload->getByte();
				$bits_per_block = $byte >> 1;
				$persistent_block_states = ($byte & 0x01) === 0;

				$extra_payload->get($BITS_PER_BLOCK_TO_WORD_ARRAY_LENGTH[$bits_per_block]); // words
				if($bits_per_block !== 0){
					$palette_count = $extra_payload->getUnsignedVarInt() >> 1;
				}else{
					$palette_count = 1;
				}
				if($persistent_block_states){
					$nbt_serializer = new NetworkNbtSerializer();
					$remaining = substr($extra_payload->getBuffer(), $extra_payload->getOffset());
					$nbt_offset = 0;
					for($j = 0; $j < $palette_count; ++$j){
						$nbt_serializer->read($remaining, $nbt_offset, 512);
					}
					$extra_payload->get($nbt_offset);
				}else{
					for($j = 0; $j < $palette_count; ++$j){
						$extra_payload->getUnsignedVarInt();
					}
				}
			}
		}

		// read one biome to find length of a biome
		$biome_start_offset = $extra_payload->getOffset();
		$biome_palette_bits_per_block = $extra_payload->getByte() >> 1;
		$extra_payload->get($BITS_PER_BLOCK_TO_WORD_ARRAY_LENGTH[$biome_palette_bits_per_block]); // word array
		if($biome_palette_bits_per_block !== 0){
			$biome_palette_count = $extra_payload->getUnsignedVarInt() >> 1;
		}else{
			$biome_palette_count = 1;
		}
		for($i = 0; $i < $biome_palette_count; ++$i){
			$extra_payload->getUnsignedVarInt();
		}
		$biome_end_offset = $extra_payload->getOffset();
		$biome_length = $biome_end_offset - $biome_start_offset;

		return substr_replace($payload, "", $biome_start_offset + 1, $biome_length * $reduction);
	}
}