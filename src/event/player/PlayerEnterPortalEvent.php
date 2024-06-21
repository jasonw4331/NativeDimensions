<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\event\player;

use jasonw4331\NativeDimensions\event\DimensionPortalsEvent;
use jasonw4331\NativeDimensions\exoblock\PortalExoBlock;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\player\Player;
use pocketmine\world\Position;

class PlayerEnterPortalEvent extends DimensionPortalsEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		readonly public Player $player,
		readonly public PortalExoBlock $block,
		readonly public Position $block_position,
		readonly public int $teleport_duration
	){}

	public function getPlayer() : Player{
		return $this->player;
	}

	public function getBlock() : PortalExoBlock{
		return $this->block;
	}
}