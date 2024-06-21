<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\exoblock;

use jasonw4331\NativeDimensions\event\player\PlayerCreateEndPortalEvent;
use jasonw4331\NativeDimensions\vanilla\ExtraVanillaBlocks;
use jasonw4331\NativeDimensions\vanilla\ExtraVanillaItems;
use pocketmine\block\Block;
use pocketmine\block\EndPortalFrame;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

class EndPortalFrameExoBlock implements ExoBlock{

	public function interact(Block $wrapping, Player $player, Item $item, int $face) : bool{
		/** @var EndPortalFrame $wrapping */
		if(!$wrapping->hasEye()){
			if($item->getTypeId() === ExtraVanillaItems::ENDER_EYE()->getTypeId()){
				$pos = $wrapping->getPosition();
				$transaction = new BlockTransaction($pos->getWorld());
				$transaction->addBlockAt($pos->x, $pos->y, $pos->z, (clone $wrapping)->setEye(true));
				$center = $this->findPortalCenterFromFrame($wrapping);
				if($center !== null){
					$frame_blocks = $this->collectFrameBlocks($transaction, $center);
					if($frame_blocks !== null){
						$this->createPortal($transaction, $center);
						($ev = new PlayerCreateEndPortalEvent($player, $pos, $frame_blocks, $transaction))->call();
						if($ev->isCancelled()){
							return true;
						}
					}
				}
				if($transaction->apply()){
					$item->pop();
				}
			}
		}elseif($item->getTypeId() !== ExtraVanillaItems::ENDER_EYE()->getTypeId()){
			$pos = $wrapping->getPosition();
			$world = $pos->getWorld();
			$transaction = new BlockTransaction($world);
			$transaction->addBlockAt($pos->x, $pos->y, $pos->z, (clone $wrapping)->setEye(false));
			$center = $this->findPortalCenterFromFrame($wrapping);
			if($center !== null && $this->collectFrameBlocks($transaction, $center) === null){
				$this->destroyPortal($transaction, $center);
				$transaction->apply();
				$world->dropItem($pos->add(0.5, 0.75, 0.5), ExtraVanillaItems::ENDER_EYE());
			}
			return true;
		}
		return false;
	}

	public function update(Block $wrapping) : bool{
		return false;
	}

	public function onPlayerMoveInside(Player $player, Block $block) : void{
	}

	public function onPlayerMoveOutside(Player $player, Block $block) : void{
	}

	public function findPortalCenterFromFrame(EndPortalFrame $block) : ?Vector3{
		$facing = $block->getFacing();
		$pos = $block->getPosition();
		$left = $block->getSide(Facing::rotateY($facing, false))->hasSameTypeId($block);
		$right = $block->getSide(Facing::rotateY($facing, true))->hasSameTypeId($block);
		if($left && $right){
			return $pos->getSide($facing, 2);
		}
		if($left){
			return $pos->getSide($facing, 2)->getSide(Facing::rotateY($facing, false));
		}
		if($right){
			return $pos->getSide($facing, 2)->getSide(Facing::rotateY($facing, true));
		}
		$facing_block = $block->getSide($facing);
		if($facing_block->getSide(Facing::rotateY($facing, false))->hasSameTypeId($block)){
			return $pos->getSide($facing, 2)->getSide(Facing::rotateY($facing, true));
		}
		if($facing_block->getSide(Facing::rotateY($facing, true))->hasSameTypeId($block)){
			return $pos->getSide($facing, 2)->getSide(Facing::rotateY($facing, false));
		}
		return null;
	}

	/**
	 * @param BlockTransaction $transaction
	 * @param Vector3          $center
	 *
	 * @return list<Block>|null
	 */
	public function collectFrameBlocks(BlockTransaction $transaction, Vector3 $center) : ?array{
		$blocks = [];
		foreach(Facing::HORIZONTAL as $side){
			$pos = $center->getSide($side, 2);
			$left = $pos->getSide(Facing::rotateY($side, false));
			$right = $pos->getSide(Facing::rotateY($side, true));
			foreach([
				$transaction->fetchBlockAt($pos->x, $pos->y, $pos->z),
				$transaction->fetchBlockAt($left->x, $left->y, $left->z),
				$transaction->fetchBlockAt($right->x, $right->y, $right->z)
			] as $block){
				if(!($block instanceof EndPortalFrame) || !$block->hasEye()){
					return null;
				}
				$blocks[] = $block;
			}
		}
		return $blocks;
	}

	public function createPortal(BlockTransaction $transaction, Vector3 $center) : void{
		$type_id = ExtraVanillaBlocks::END_PORTAL()->getTypeId();
		for($i = -1; $i <= 1; ++$i){
			for($j = -1; $j <= 1; ++$j){
				if($transaction->fetchBlockAt($center->x + $i, $center->y, $center->z + $j) !== $type_id){
					$transaction->addBlockAt($center->x + $i, $center->y, $center->z + $j, $this->portal_block);
				}
			}
		}
	}

	public function destroyPortal(BlockTransaction $transaction, Vector3 $center) : void{
		$type_id = ExtraVanillaBlocks::END_PORTAL()->getTypeId();
		for($i = -1; $i <= 1; ++$i){
			for($j = -1; $j <= 1; ++$j){
				if($transaction->fetchBlockAt($center->x + $i, $center->y, $center->z + $j)->getTypeId() === $type_id){
					$transaction->addBlockAt($center->x + $i, $center->y, $center->z + $j, VanillaBlocks::AIR());
				}
			}
		}
	}
}