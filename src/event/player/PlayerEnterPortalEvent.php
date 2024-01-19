<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\event\player;

use jasonw4331\NativeDimensions\event\DimensionPortalsEvent;
use jasonw4331\NativeDimensions\exoblock\PortalExoBlock;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\player\Player;

class PlayerEnterPortalEvent extends DimensionPortalsEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		readonly public Player $player,
		readonly public PortalExoBlock $block,
		public int $teleport_duration
	){}

	public function getPlayer() : Player{
		return $this->player;
	}

	public function getBlock() : PortalExoBlock{
		return $this->block;
	}

	public function getTeleportDuration() : int{
		return $this->teleport_duration;
	}

	public function setTeleportDuration(int $teleport_duration) : void{
		$this->teleport_duration = $teleport_duration;
	}
}