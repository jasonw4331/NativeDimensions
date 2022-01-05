<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\block;

use pocketmine\block\Air;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\Fire as PMFire;
use pocketmine\math\Facing;
use pocketmine\world\Position;

class Fire extends PMFire {

	public function onNearbyBlockChange() : void {
		foreach($this->getAllSides() as $block) {
			if($block->getIdInfo()->getBlockId() !== BlockLegacyIds::OBSIDIAN) {
				continue;
			}
			$minWidth = 2;
			if($this->testDirectionForObsidian(Facing::NORTH, $this->getPosition(), $widthA) and $this->testDirectionForObsidian(Facing::SOUTH, $this->getPosition(), $widthB)) {
				$totalWidth = $widthA + $widthB - 1;
				if($totalWidth < $minWidth) {
					parent::onNearbyBlockChange();
					return; // portal cannot be made
				}
				$direction = Facing::NORTH;
			}elseif($this->testDirectionForObsidian(Facing::EAST, $this->getPosition(), $widthA) and $this->testDirectionForObsidian(Facing::WEST, $this->getPosition(), $widthB)) {
				$totalWidth = $widthA + $widthB - 1;
				if($totalWidth < $minWidth) {
					parent::onNearbyBlockChange();
					return;
				}
				$direction = Facing::EAST;
			}else{
				parent::onNearbyBlockChange();
				return;
			}

			$minHeight = 3;
			if($this->testDirectionForObsidian(Facing::UP, $this->getPosition(), $heightA) and $this->testDirectionForObsidian(Facing::DOWN, $this->getPosition(), $heightB)) {
				$totalHeight = $heightA + $heightB - 1;
				if($totalHeight < $minHeight) {
					parent::onNearbyBlockChange();
					return; // portal cannot be made
				}
			}else{
				parent::onNearbyBlockChange();
				return;
			}

			$this->testDirectionForObsidian($direction, $this->getPosition(), $horizblocks);
			$start = $this->getPosition()->getSide($direction, $horizblocks-1);
			$this->testDirectionForObsidian(Facing::UP, $this->getPosition(), $vertblocks);
			$start = Position::fromObject($start->add(0, $vertblocks-1, 0), $start->getWorld());

			for($j = 0; $j < $totalHeight; ++$j) {
				for($k = 0; $k < $totalWidth; ++$k) {
					if($direction == Facing::NORTH) {
						$start->getWorld()->setBlock($start->add(0, -$j, $k), BlockFactory::getInstance()->get(BlockLegacyIds::PORTAL, 0), false);
					}else{
						$start->getWorld()->setBlock($start->add(-$k, -$j, 0), BlockFactory::getInstance()->get(BlockLegacyIds::PORTAL, 0), false);
					}
				}
			}
			return;
		}
		parent::onNearbyBlockChange();
	}

	private function testDirectionForObsidian(int $direction, Position $start, ?int &$distance = null) : bool {
		for($i = 1; $i <= 23; ++$i) {
			$testPos = $start->getSide($direction, $i);
			if($testPos->getWorld()->getBlock($testPos, true, false) instanceof Obsidian) {
				$distance = $i;
				return true;
			}elseif(!$testPos->getWorld()->getBlock($testPos, true, false) instanceof Air) {
				return false;
			}
		}
		return false;
	}

}