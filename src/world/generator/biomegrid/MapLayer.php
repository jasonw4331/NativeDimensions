<?php

declare(strict_types=1);

namespace jasonwynn10\NativeDimensions\world\generator\biomegrid;

use jasonwynn10\NativeDimensions\world\generator\biomegrid\utils\MapLayerPair;
use jasonwynn10\NativeDimensions\world\generator\Environment;
use jasonwynn10\NativeDimensions\world\generator\nether\biome\BiomeIds;
use jasonwynn10\NativeDimensions\world\generator\nether\WorldType;
use pocketmine\utils\Random;

abstract class MapLayer{

	public static function initialize(int $seed, int $environment, string $world_type) : MapLayerPair{
		if($environment === Environment::THE_END){
			return new MapLayerPair(new ConstantBiomeMapLayer($seed, BiomeIds::SKY), null);
		}
		return new MapLayerPair(new ConstantBiomeMapLayer($seed, BiomeIds::HELL), null);
	}

	private Random $random;
	private int $seed;

	public function __construct(int $seed){
		$this->random = new Random();
		$this->seed = $seed;
	}

	public function setCoordsSeed(int $x, int $z) : void{
		$this->random->setSeed($this->seed);
		$this->random->setSeed($x * $this->random->nextInt() + $z * $this->random->nextInt() ^ $this->seed);
	}

	public function nextInt(int $max) : int{
		return $this->random->nextBoundedInt($max);
	}

	/**
	 * @param int $x
	 * @param int $z
	 * @param int $size_x
	 * @param int $size_z
	 * @return int[]
	 */
	abstract public function generateValues(int $x, int $z, int $size_x, int $size_z) : array;
}