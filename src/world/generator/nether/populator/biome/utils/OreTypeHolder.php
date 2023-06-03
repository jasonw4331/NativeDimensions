<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\world\generator\nether\populator\biome\utils;

use jasonw4331\NativeDimensions\world\generator\object\OreType;

final class OreTypeHolder{

	public function __construct(
		public OreType $type,
		public int $value
	){
	}
}
