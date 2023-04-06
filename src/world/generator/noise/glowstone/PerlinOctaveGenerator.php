<?php

declare(strict_types=1);

namespace jasonwynn10\NativeDimensions\world\generator\noise\glowstone;

use jasonwynn10\NativeDimensions\world\generator\noise\bukkit\NoiseGenerator;
use jasonwynn10\NativeDimensions\world\generator\noise\bukkit\OctaveGenerator;
use pocketmine\utils\Random;
use function array_fill;

class PerlinOctaveGenerator extends OctaveGenerator{

	/**
	 * @return PerlinNoise[]
	 */
	protected static function createOctaves(Random $rand, int $octaves) : array{
		$result = [];

		for($i = 0; $i < $octaves; ++$i){
			$result[$i] = new PerlinNoise($rand);
		}

		return $result;
	}

	protected static function floor(float $x) : int{
		return $x >= 0 ? (int) $x : (int) $x - 1;
	}

	public static function fromRandomAndOctaves(Random $random, int $octaves, int $size_x, int $size_y, int $size_z) : self{
		return new PerlinOctaveGenerator(self::createOctaves($random, $octaves), $size_x, $size_y, $size_z);
	}

	protected int $size_x;
	protected int $size_y;
	protected int $size_z;

	/** @var float[] */
	protected array $noise;

	/**
	 * Creates a generator for multiple layers of Perlin noise.
	 *
	 * @param NoiseGenerator[] $octaves the noise generators
	 * @param int              $size_x  the size on the X axis
	 * @param int              $size_y  the size on the Y axis
	 * @param int              $size_z  the size on the Z axis
	 */
	public function __construct(array $octaves, int $size_x, int $size_y, int $size_z){
		parent::__construct($octaves);
		$this->size_x = $size_x;
		$this->size_y = $size_y;
		$this->size_z = $size_z;
		$this->noise = array_fill(0, $size_x * $size_y * $size_z, 0.0);
	}

	public function getSizeX() : int{
		return $this->size_x;
	}

	public function getSizeY() : int{
		return $this->size_y;
	}

	public function getSizeZ() : int{
		return $this->size_z;
	}

	public function setSizeX(int $size_x) : void{
		$this->size_x = $size_x;
	}

	public function setSizeY(int $size_y) : void{
		$this->size_y = $size_y;
	}

	public function setSizeZ(int $size_z) : void{
		$this->size_z = $size_z;
	}

	/**
	 * Generates multiple layers of noise.
	 *
	 * @param float $x           the starting X coordinate
	 * @param float $y           the starting Y coordinate
	 * @param float $z           the starting Z coordinate
	 * @param float $lacunarity  layer n's frequency as a fraction of layer {@code n - 1}'s frequency
	 * @param float $persistence layer n's amplitude as a multiple of layer {@code n - 1}'s amplitude
	 *
	 * @return float[] the noise array
	 */
	public function getFractalBrownianMotion(float $x, float $y, float $z, float $lacunarity, float $persistence) : array{
		$this->noise = array_fill(0, $this->size_x * $this->size_y * $this->size_z, 0.0);

		$freq = 1;
		$amp = 1;

		$x *= $this->x_scale;
		$y *= $this->y_scale;
		$z *= $this->z_scale;

		// fBm
		// the noise have to be periodic over x and z axis: otherwise it can go crazy with high
		// input, leading to strange oddities in terrain generation like the old minecraft farland
		// symptoms.
		/** @var PerlinNoise $octave */
		foreach($this->octaves as $octave){
			$dx = $x * $freq;
			$dz = $z * $freq;
			// compute integer part
			$lx = self::floor($dx);
			$lz = self::floor($dz);
			// compute fractional part
			$dx -= $lx;
			$dz -= $lz;
			// wrap integer part to 0..16777216
			$lx %= 16777216;
			$lz %= 16777216;
			// add to fractional part
			$dx += $lx;
			$dz += $lz;

			$dy = $y * $freq;
			$this->noise = $octave->getNoise($this->noise, $dx, $dy, $dz, $this->size_x, $this->size_y, $this->size_z, $this->x_scale * $freq, $this->y_scale * $freq, $this->z_scale * $freq, $amp);
			$freq *= $lacunarity;
			$amp *= $persistence;
		}

		return $this->noise;
	}
}
