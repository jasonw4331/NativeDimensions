<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\exoblock;

use jasonw4331\NativeDimensions\utils\WorldUtils;
use muqsit\dimensionportals\vanilla\ExtraVanillaBlocks;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

class EndPortalExoBlock extends PortalExoBlock{

	public function getTargetWorldDimensionId() : int{
		return DimensionIds::THE_END;
	}

	public function interact(Block $wrapping, Player $player, Item $item, int $face) : bool{
		return false;
	}

	public function meetsSupportConditions(BlockTransaction $transaction, Vector3 $pos) : bool{
		foreach(Facing::HORIZONTAL as $side){
			$side_pos = $pos->getSide($side);
			$type_id = $transaction->fetchBlockAt($side_pos->x, $side_pos->y, $side_pos->z)->getTypeId();
			if($type_id !== VanillaBlocks::END_PORTAL_FRAME()->getTypeId() && $type_id !== ExtraVanillaBlocks::END_PORTAL()->getTypeId()){
				return false;
			}
		}
		return true;
	}

	public function update(Block $wrapping) : bool{
		$pos = $wrapping->getPosition();
		if(!$this->meetsSupportConditions(new BlockTransaction($pos->getWorld()), $pos)){
			WorldUtils::removeTouchingBlocks($pos->getWorld(), ExtraVanillaBlocks::END_PORTAL()->getTypeId(), $pos, Facing::HORIZONTAL)?->apply();
		}
		return false;
	}
}