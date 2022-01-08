<?php

declare(strict_types=1);

namespace jasonwynn10\NativeDimensions\world\generator\nether\populator\biome\utils;

use jasonwynn10\NativeDimensions\world\generator\object\OreType;

final class OreTypeHolder{

	/** @var OreType */
	public OreType $type;

	/** @var int */
	public int $value;

	public function __construct(OreType $type, int $value){
		$this->type = $type;
		$this->value = $value;
	}
}