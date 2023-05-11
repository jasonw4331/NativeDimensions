<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\world\generator\biomegrid\utils;

use jasonw4331\NativeDimensions\world\generator\biomegrid\MapLayer;

final class MapLayerPair{

	public MapLayer $high_resolution;
	public ?MapLayer $low_resolution;

	public function __construct(MapLayer $high_resolution, ?MapLayer $low_resolution){
		$this->high_resolution = $high_resolution;
		$this->low_resolution = $low_resolution;
	}
}
