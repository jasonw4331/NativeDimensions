<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\world\generator;

use jasonw4331\NativeDimensions\world\generator\biomegrid\BiomeGrid;
use function array_key_exists;

class VanillaBiomeGrid implements BiomeGrid{

	/** @var int[] */
	public array $biomes = [];

	public function getBiome(int $x, int $z) : ?int{
		// upcasting is very important to get extended biomes
		return array_key_exists($hash = $x | $z << 4, $this->biomes) ? $this->biomes[$hash] & 0xFF : null;
	}

	public function setBiome(int $x, int $z, int $biome_id) : void{
		$this->biomes[$x | $z << 4] = $biome_id;
	}
}
