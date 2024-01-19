<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\exoblock;

use pocketmine\block\Block;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\player\Player;

class EndPortalExoBlock extends PortalExoBlock{

	public function getTargetWorldDimensionId() : int{
		return DimensionIds::THE_END;
	}

	public function getTargetWorldTeleportLocation(Player $player) : Location{
		return $player->getWorld()->getOverworld() === $player->getWorld() ? $player->getWorld()->getEnd()->getSpawnLocation() : $player->getWorld()->getOverworld()->getSpawnLocation();
	}

	public function interact(Block $wrapping, Player $player, Item $item, int $face) : bool{
		return false;
	}

	public function update(Block $wrapping) : bool{
		return false;
	}
}