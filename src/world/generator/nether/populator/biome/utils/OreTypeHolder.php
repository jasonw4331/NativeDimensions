<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\world\generator\nether\populator\biome\utils;

use jasonw4331\NativeDimensions\world\generator\object\OreType;

final class OreTypeHolder{

	public OreType $type;

	public int $value;

	public function __construct(OreType $type, int $value){
		$this->type = $type;
		$this->value = $value;
	}
}
