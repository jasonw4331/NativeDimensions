<?php

declare(strict_types=1);

namespace jasonwynn10\NativeDimensions\world\generator\noise\glowstone;

use pocketmine\utils\Random;
use function array_fill;

class SimplexOctaveGenerator extends PerlinOctaveGenerator{

	/**
	 * @return SimplexNoise[]
	 */
	protected static function createOctaves(Random $rand, int $octaves) : array{
		$result = [];

		for($i = 0; $i < $octaves; ++$i){
			$result[$i] = new SimplexNoise($rand);
		}

		return $result;
	}

	public static function fromRandomAndOctaves(Random $random, int $octaves, int $size_x, int $size_y, int $size_z) : self{
		return new SimplexOctaveGenerator(self::createOctaves($random, $octaves), $size_x, $size_y, $size_z);
	}

	public function getFractalBrownianMotion(float $x, float $y, float $z, float $lacunarity, float $persistence) : array{
		$this->noise = array_fill(0, $this->size_x * $this->size_y * $this->size_z, 0.0);

		$freq = 1.0;
		$amp = 1.0;

		// fBm
		/** @var SimplexNoise $octave */
		foreach($this->octaves as $octave){
			$this->noise = $octave->getNoise($this->noise, $x, $y, $z, $this->size_x, $this->size_y, $this->size_z, $this->x_scale * $freq, $this->y_scale * $freq, $this->z_scale * $freq, 0.55 / $amp);
			$freq *= $lacunarity;
			$amp *= $persistence;
		}

		return $this->noise;
	}
}
