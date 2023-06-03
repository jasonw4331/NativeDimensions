<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\world\generator\biome;

class BiomeClimate{

	public function __construct(
		readonly public float $temperature,
		readonly public float $humidity,
		readonly public bool $rainy
	){
	}
}
