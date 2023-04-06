<?php

declare(strict_types=1);

namespace jasonwynn10\NativeDimensions\world\generator\biomegrid;

interface BiomeGrid{

	/**
	 * Get biome at x, z within chunk being generated
	 *
	 * @param int $x - 0-15
	 * @param int $z - 0-15
	 */
	public function getBiome(int $x, int $z) : ?int;

	/**
	 * Set biome at x, z within chunk being generated
	 *
	 * @param int $x - 0-15
	 * @param int $z - 0-15
	 */
	public function setBiome(int $x, int $z, int $biome_id) : void;
}
