<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\player;

use jasonw4331\NativeDimensions\exoblock\PortalExoBlock;

final class PlayerPortalInfo{

	private int $duration = 0;

	public function __construct(
		readonly public PortalExoBlock $block,
		readonly public int $max_duration
	){}

	public function tick() : bool{
		if($this->duration === $this->max_duration){
			$this->duration = 0;
			return true;
		}

		++$this->duration;
		return false;
	}
}