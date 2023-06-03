<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\world\generator\biomegrid\utils;

use jasonw4331\NativeDimensions\world\generator\biomegrid\MapLayer;

final class MapLayerPair{

	public function __construct(
		public MapLayer $high_resolution,
		public ?MapLayer $low_resolution
	){
	}
}
