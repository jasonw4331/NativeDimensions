<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\world\generator\nether;

use jasonw4331\NativeDimensions\world\generator\Environment;
use jasonw4331\NativeDimensions\world\generator\nether\populator\NetherPopulator;
use jasonw4331\NativeDimensions\world\generator\noise\glowstone\PerlinOctaveGenerator;
use jasonw4331\NativeDimensions\world\generator\utils\NetherWorldOctaves;
use jasonw4331\NativeDimensions\world\generator\VanillaBiomeGrid;
use jasonw4331\NativeDimensions\world\generator\VanillaGenerator;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use function cos;
use function max;
use function min;
use const M_PI;

/**
 * @phpstan-extends VanillaGenerator<NetherWorldOctaves<PerlinOctaveGenerator, PerlinOctaveGenerator, PerlinOctaveGenerator, PerlinOctaveGenerator, PerlinOctaveGenerator, PerlinOctaveGenerator>>
 */
class NetherGenerator extends VanillaGenerator{

	protected const COORDINATE_SCALE = 684.412;
	protected const HEIGHT_SCALE = 2053.236;
	protected const HEIGHT_NOISE_SCALE_X = 100.0;
	protected const HEIGHT_NOISE_SCALE_Z = 100.0;
	protected const DETAIL_NOISE_SCALE_X = 80.0;
	protected const DETAIL_NOISE_SCALE_Y = 60.0;
	protected const DETAIL_NOISE_SCALE_Z = 80.0;
	protected const SURFACE_SCALE = 0.0625;

	/**
	 * @param int $i 0-4
	 * @param int $j 0-4
	 * @param int $k 0-32
	 */
	private static function densityHash(int $i, int $j, int $k) : int{
		return ($k << 6) | ($j << 3) | $i;
	}

	protected int $bedrock_roughness = 5;

	public function __construct(int $seed, string $preset){
		parent::__construct($seed, Environment::NETHER, null, $preset);
		$this->addPopulators(new NetherPopulator($this->getMaxY())); // This isn't faithful to original code. Was $world->getWorldHeight()
	}

	public function getBedrockRoughness() : int{
		return $this->bedrock_roughness;
	}

	public function setBedrockRoughness(int $bedrock_roughness) : void{
		$this->bedrock_roughness = $bedrock_roughness;
	}

	public function getMaxY() : int{
		return 128;
	}

	protected function generateChunkData(ChunkManager $world, int $chunk_x, int $chunk_z, VanillaBiomeGrid $biomes) : void{
		$this->generateRawTerrain($world, $chunk_x, $chunk_z);
		$cx = $chunk_x << 4;
		$cz = $chunk_z << 4;

		$octaves = $this->getWorldOctaves();

		$surface_noise = $octaves->surface->getFractalBrownianMotion($cx, $cz, 0, 0.5, 2.0);
		$soul_sand_noise = $octaves->soul_sand->getFractalBrownianMotion($cx, $cz, 0, 0.5, 2.0);
		$grave_noise = $octaves->gravel->getFractalBrownianMotion($cx, 0, $cz, 0.5, 2.0);

		/** @var Chunk $chunk */
		$chunk = $world->getChunk($chunk_x, $chunk_z);

		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				$chunk->setBiomeId($x, $z, $id = $biomes->getBiome($x, $z));
				$this->generateTerrainColumn($world, $cx + $x, $cz + $z, $surface_noise[$x | $z << 4], $soul_sand_noise[$x | $z << 4], $grave_noise[$x | $z << 4]);
			}
		}
	}

	protected function createWorldOctaves() : NetherWorldOctaves{
		$seed = new Random($this->random->getSeed());

		$height = PerlinOctaveGenerator::fromRandomAndOctaves($seed, 16, 5, 1, 5);
		$height->setXScale(static::HEIGHT_NOISE_SCALE_X);
		$height->setZScale(static::HEIGHT_NOISE_SCALE_Z);

		$roughness = PerlinOctaveGenerator::fromRandomAndOctaves($seed, 16, 5, 17, 5);
		$roughness->setXScale(static::COORDINATE_SCALE);
		$roughness->setYScale(static::HEIGHT_SCALE);
		$roughness->setZScale(static::COORDINATE_SCALE);

		$roughness2 = PerlinOctaveGenerator::fromRandomAndOctaves($seed, 16, 5, 17, 5);
		$roughness2->setXScale(static::COORDINATE_SCALE);
		$roughness2->setYScale(static::HEIGHT_SCALE);
		$roughness2->setZScale(static::COORDINATE_SCALE);

		$detail = PerlinOctaveGenerator::fromRandomAndOctaves($seed, 8, 5, 17, 5);
		$detail->setXScale(static::COORDINATE_SCALE / static::DETAIL_NOISE_SCALE_X);
		$detail->setYScale(static::HEIGHT_SCALE / static::DETAIL_NOISE_SCALE_Y);
		$detail->setZScale(static::COORDINATE_SCALE / static::DETAIL_NOISE_SCALE_Z);

		$surface = PerlinOctaveGenerator::fromRandomAndOctaves($seed, 4, 16, 16, 1);
		$surface->setScale(static::SURFACE_SCALE);

		$soulsand = PerlinOctaveGenerator::fromRandomAndOctaves($seed, 4, 16, 16, 1);
		$soulsand->setXScale(static::SURFACE_SCALE / 2.0);
		$soulsand->setYScale(static::SURFACE_SCALE / 2.0);

		$gravel = PerlinOctaveGenerator::fromRandomAndOctaves($seed, 4, 16, 1, 16);
		$gravel->setXScale(static::SURFACE_SCALE / 2.0);
		$gravel->setZScale(static::SURFACE_SCALE / 2.0);

		return new NetherWorldOctaves($height, $roughness, $roughness2, $detail, $surface, $soulsand, $gravel);
	}

	private function generateRawTerrain(ChunkManager $world, int $chunk_x, int $chunk_z) : void{
		$density = $this->generateTerrainDensity($chunk_x << 2, $chunk_z << 2);

		$nether_rack = VanillaBlocks::NETHERRACK()->getFullId();
		$still_lava = VanillaBlocks::LAVA()->getStillForm()->getFullId();

		/** @var Chunk $chunk */
		$chunk = $world->getChunk($chunk_x, $chunk_z);

		for($i = 0; $i < 5 - 1; ++$i){
			for($j = 0; $j < 5 - 1; ++$j){
				for($k = 0; $k < 17 - 1; ++$k){
					$d1 = $density[self::densityHash($i, $j, $k)];
					$d2 = $density[self::densityHash($i + 1, $j, $k)];
					$d3 = $density[self::densityHash($i, $j + 1, $k)];
					$d4 = $density[self::densityHash($i + 1, $j + 1, $k)];
					$d5 = ($density[self::densityHash($i, $j, $k + 1)] - $d1) / 8;
					$d6 = ($density[self::densityHash($i + 1, $j, $k + 1)] - $d2) / 8;
					$d7 = ($density[self::densityHash($i, $j + 1, $k + 1)] - $d3) / 8;
					$d8 = ($density[self::densityHash($i + 1, $j + 1, $k + 1)] - $d4) / 8;

					for($l = 0; $l < 8; ++$l){
						$d9 = $d1;
						$d10 = $d3;

						$y_pos = $l + ($k << 3);
						$y_block_pos = $y_pos & 0xf;
						$sub_chunk = $chunk->getSubChunk($y_pos >> 4);

						for($m = 0; $m < 4; ++$m){
							$dens = $d9;
							for($n = 0; $n < 4; ++$n){
								// any density higher than 0 is ground, any density lower or equal
								// to 0 is air (or lava if under the lava level).
								if($dens > 0){
									$sub_chunk->setFullBlock($m + ($i << 2), $y_block_pos, $n + ($j << 2), $nether_rack);
								}elseif($l + ($k << 3) < 32){
									$sub_chunk->setFullBlock($m + ($i << 2), $y_block_pos, $n + ($j << 2), $still_lava);
								}
								// interpolation along z
								$dens += ($d10 - $d9) / 4;
							}
							// interpolation along x
							$d9 += ($d2 - $d1) / 4;
							// interpolate along z
							$d10 += ($d4 - $d3) / 4;
						}
						// interpolation along y
						$d1 += $d5;
						$d3 += $d7;
						$d2 += $d6;
						$d4 += $d8;
					}
				}
			}
		}
	}

	/**
	 * @return float[]
	 */
	private function generateTerrainDensity(int $x, int $z) : array{
		$octaves = $this->getWorldOctaves();
		$height_noise = $octaves->height->getFractalBrownianMotion($x, 0, $z, 0.5, 2.0);
		$roughness_noise = $octaves->roughness->getFractalBrownianMotion($x, 0, $z, 0.5, 2.0);
		$roughness_noise_2 = $octaves->roughness_2->getFractalBrownianMotion($x, 0, $z, 0.5, 2.0);
		$detail_noise = $octaves->detail->getFractalBrownianMotion($x, 0, $z, 0.5, 2.0);

		$k_max = $octaves->detail->getSizeY();

		static $nv = null;
		if($nv === null){
			$nv = [];
			for($i = 0; $i < $k_max; ++$i){
				$nv[$i] = cos($i * M_PI * 6.0 / $k_max) * 2.0;
				$nh = $i > $k_max / 2 ? $k_max - 1 - $i : $i;
				if($nh < 4.0){
					$nh = 4.0 - $nh;
					$nv[$i] -= $nh * $nh * $nh * 10.0;
				}
			}
		}

		$index = 0;
		$index_height = 0;

		$density = [];

		for($i = 0; $i < 5; ++$i){
			for($j = 0; $j < 5; ++$j){

				$noise_h = $height_noise[$index_height++] / 8000.0;
				if($noise_h < 0){
					$noise_h = -$noise_h;
				}
				$noise_h = $noise_h * 3.0 - 3.0;
				if($noise_h < 0){
					$noise_h = max($noise_h * 0.5, -1) / 1.4 * 0.5;
				}else{
					$noise_h = min($noise_h, 1) / 6.0;
				}

				$noise_h = $noise_h * $k_max / 16.0;
				for($k = 0; $k < $k_max; ++$k){
					$noise_r = $roughness_noise[$index] / 512.0;
					$noise_r_2 = $roughness_noise_2[$index] / 512.0;
					$noise_d = ($detail_noise[$index] / 10.0 + 1.0) / 2.0;
					$nh = $nv[$k];
					// linear interpolation
					$dens = $noise_d < 0 ? $noise_r : ($noise_d > 1 ? $noise_r_2 : $noise_r + ($noise_r_2 - $noise_r) * $noise_d);
					$dens -= $nh;
					++$index;
					$k_cap = $k_max - 4;
					if($k > $k_cap){
						$lowering = ($k - $k_cap) / 3.0;
						$dens = $dens * (1.0 - $lowering) + $lowering * -10.0;
					}
					$density[self::densityHash($i, $j, $k)] = $dens;
				}
			}
		}

		return $density;
	}

	public function generateTerrainColumn(ChunkManager $world, int $x, int $z, float $surface_noise, float $soul_sand_noise, float $grave_noise) : void{
		$soul_sand = $soul_sand_noise + $this->random->nextFloat() * 0.2 > 0;
		$gravel = $grave_noise + $this->random->nextFloat() * 0.2 > 0;

		$surface_height = (int) ($surface_noise / 3.0 + 3.0 + $this->random->nextFloat() * 0.25);
		$deep = -1;
		$world_height = $this->getMaxY();
		$world_height_m1 = $world_height - 1;

		$block_bedrock = VanillaBlocks::BEDROCK()->getFullId();
		$block_air = VanillaBlocks::AIR()->getFullId();
		$block_nether_rack = VanillaBlocks::NETHERRACK()->getFullId();
		$block_gravel = VanillaBlocks::GRAVEL()->getFullId();
		$block_soul_sand = VanillaBlocks::SOUL_SAND()->getFullId();

		$top_mat = $block_nether_rack;
		$ground_mat = $block_nether_rack;

		/** @var Chunk $chunk */
		$chunk = $world->getChunk($x >> 4, $z >> 4);
		$chunk_block_x = $x & 0x0f;
		$chunk_block_z = $z & 0x0f;

		for($y = $world_height_m1; $y >= 0; --$y){
			if($y <= $this->random->nextBoundedInt($this->bedrock_roughness) || $y >= $world_height_m1 - $this->random->nextBoundedInt($this->bedrock_roughness)){
				$chunk->setFullBlock($chunk_block_x, $y, $chunk_block_z, $block_bedrock);
				continue;
			}
			$mat = $chunk->getFullBlock($chunk_block_x, $y, $chunk_block_z);
			if($mat === $block_air){
				$deep = -1;
			}elseif($mat === $block_nether_rack){
				if($deep === -1){
					if($surface_height <= 0){
						$top_mat = $block_air;
						$ground_mat = $block_nether_rack;
					}elseif($y >= 60 && $y <= 65){
						$top_mat = $block_nether_rack;
						$ground_mat = $block_nether_rack;
						if($gravel){
							$top_mat = $block_gravel;
							$ground_mat = $block_nether_rack;
						}
						if($soul_sand){
							$top_mat = $block_soul_sand;
							$ground_mat = $block_soul_sand;
						}
					}

					$deep = $surface_height;
					if($y >= 63){
						$chunk->setFullBlock($chunk_block_x, $y, $chunk_block_z, $top_mat);
					}else{
						$chunk->setFullBlock($chunk_block_x, $y, $chunk_block_z, $ground_mat);
					}
				}elseif($deep > 0){
					--$deep;
					$chunk->setFullBlock($chunk_block_x, $y, $chunk_block_z, $ground_mat);
				}
			}
		}
	}
}
