<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\block;

use pocketmine\block\Air;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIds;
use pocketmine\block\Fire as PMFire;
use pocketmine\level\Position;
use pocketmine\math\Vector3;

class Fire extends PMFire {

	public function onNearbyBlockChange() : void {
		foreach($this->getAllSides() as $side => $block) {
			if(!$block instanceof \pocketmine\block\Obsidian) {
				continue;
			}
			$minWidth = 2;
			if($this->testDirectionForObsidian(Vector3::SIDE_NORTH, $this, $widthA) and $this->testDirectionForObsidian(Vector3::SIDE_SOUTH, $this, $widthB)) {
				$totalWidth = $widthA + $widthB - 1;
				if($totalWidth < $minWidth) {
					parent::onNearbyBlockChange();
					return; // portal cannot be made
				}
				$direction = Vector3::SIDE_NORTH;
			}elseif($this->testDirectionForObsidian(Vector3::SIDE_EAST, $this, $widthA) and $this->testDirectionForObsidian(Vector3::SIDE_WEST, $this, $widthB)) {
				$totalWidth = $widthA + $widthB - 1;
				if($totalWidth < $minWidth) {
					parent::onNearbyBlockChange();
					return;
				}
				$direction = Vector3::SIDE_EAST;
			}else{
				parent::onNearbyBlockChange();
				return;
			}

			$minHeight = 3;
			if($this->testDirectionForObsidian(Vector3::SIDE_UP, $this, $heightA) and $this->testDirectionForObsidian(Vector3::SIDE_DOWN, $this, $heightB)) {
				$totalHeight = $heightA + $heightB - 1;
				if($totalHeight < $minHeight) {
					parent::onNearbyBlockChange();
					return; // portal cannot be made
				}
			}else{
				parent::onNearbyBlockChange();
				return;
			}

			$this->testDirectionForObsidian($direction, $this, $horizblocks);
			$start = $this->asPosition()->getSide($direction, $horizblocks-1);
			$this->testDirectionForObsidian(Vector3::SIDE_UP, $this, $vertblocks);
			$start = Position::fromObject($start->add(0, $vertblocks-1, 0), $start->getLevelNonNull());

			for($j = 0; $j < $totalHeight; ++$j) {
				for($k = 0; $k < $totalWidth; ++$k) {
					if($direction == Vector3::SIDE_NORTH) {
						$start->getLevelNonNull()->setBlock($start->add(0, -$j, $k), BlockFactory::get(BlockIds::PORTAL), false, false);
					}else{
						$start->getLevelNonNull()->setBlock($start->add(-$k, -$j), BlockFactory::get(BlockIds::PORTAL), false, false);
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
			if($testPos->getLevelNonNull()->getBlock($testPos, true, false) instanceof \pocketmine\block\Obsidian) {
				$distance = $i;
				return true;
			}elseif(!$testPos->getLevelNonNull()->getBlock($testPos, true, false) instanceof Air) {
				return false;
			}
		}
		return false;
	}

}