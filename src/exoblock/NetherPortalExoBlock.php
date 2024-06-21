<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\exoblock;

use jasonw4331\NativeDimensions\utils\WorldUtils;
use jasonw4331\NativeDimensions\vanilla\ExtraVanillaBlocks;
use pocketmine\block\Block;
use pocketmine\block\NetherPortal;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\World;
use function assert;

class NetherPortalExoBlock extends PortalExoBlock{

	public function __construct(){
		parent::__construct(20 * 6);
	}

	public function getTargetWorldDimensionId() : int{
		return DimensionIds::NETHER;
	}

	public function meetsSupportConditions(BlockTransaction $transaction, Vector3 $pos) : bool{
		$faces = [];
		if($pos->y < World::Y_MAX - 1){
			$faces[] = Facing::UP;
		}
		if($pos->y > World::Y_MIN){
			$faces[] = Facing::DOWN;
		}
		$portal_block = $transaction->fetchBlockAt($pos->x, $pos->y, $pos->z);
		if($portal_block instanceof NetherPortal){
			$axis = $portal_block->getAxis();
		}else{
			$axis = Axis::Z;
		}
		if($axis === Axis::Z){
			$faces[] = Facing::SOUTH;
			$faces[] = Facing::NORTH;
		}else{
			assert($axis === Axis::X);
			$faces[] = Facing::WEST;
			$faces[] = Facing::EAST;
		}
		foreach($faces as $face){
			$side_pos = $pos->getSide($face);
			$block = $transaction->fetchBlockAt($side_pos->x, $side_pos->y, $side_pos->z);
			if(!$this->isValid($block)){
				return false;
			}
		}
		return true;
	}

	public function update(Block $wrapping) : bool{
		assert($wrapping instanceof NetherPortal);
		$pos = $wrapping->getPosition();
		$world = $pos->getWorld();
		if(!$this->meetsSupportConditions(new BlockTransaction($world), $pos)){
			$check_sides = [Facing::UP, Facing::DOWN];
			$axis = $wrapping->getAxis();
			if($axis === Axis::X){
				$check_sides[] = Facing::EAST;
				$check_sides[] = Facing::WEST;
			}else{
				assert($axis === Axis::Z);
				$check_sides[] = Facing::NORTH;
				$check_sides[] = Facing::SOUTH;
			}
			return WorldUtils::removeTouchingBlocks($world, ExtraVanillaBlocks::END_PORTAL()->getTypeId(), $pos, $check_sides)?->apply() ?? false;
		}
		return false;
	}

	public function interact(Block $wrapping, Player $player, Item $item, int $face) : bool{
		return false;
	}

	public function isValid(Block $block) : bool{
		$blockId = $block->getTypeId();
		return $blockId === VanillaBlocks::END_PORTAL_FRAME()->getTypeId() || $blockId === ExtraVanillaBlocks::END_PORTAL()->getTypeId();
	}
}