<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\event\player;

use jasonw4331\NativeDimensions\event\DimensionPortalsEvent;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\player\Player;
use pocketmine\world\Position;

class PlayerCreatePortalEvent extends DimensionPortalsEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		readonly public Player $player,
		readonly public Position $block_pos
	){}

	final public function getPlayer() : Player{
		return $this->player;
	}

	final public function getBlockPos() : Position{
		return $this->block_pos;
	}
}