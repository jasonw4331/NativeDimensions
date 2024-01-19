<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\event\player;

use jasonw4331\NativeDimensions\event\DimensionPortalsEvent;
use jasonw4331\NativeDimensions\exoblock\PortalExoBlock;
use pocketmine\entity\Location;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\player\Player;

class PlayerPortalTeleportEvent extends DimensionPortalsEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		readonly public Player $player,
		readonly public PortalExoBlock $block,
		public Location $target
	){}

	public function getPlayer() : Player{
		return $this->player;
	}

	public function getBlock() : PortalExoBlock{
		return $this->block;
	}

	public function getTarget() : Location{
		return $this->target->asLocation();
	}

	public function setTarget(Location $target) : void{
		$this->target = $target->asLocation();
	}
}