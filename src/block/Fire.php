<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\block;

use pocketmine\block\Air;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier as BID;
use pocketmine\block\BlockLegacyIds as Ids;
use pocketmine\block\Fire as PMFire;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\world\Position;

class Fire extends PMFire {

	public function __construct(){
		parent::__construct(new BID(Ids::FIRE, 0), "Fire Block", BlockBreakInfo::instant());
	}

	public function onNearbyBlockChange() : void {
		$world = $this->getPosition()->getWorld();
		if($world->getEnd() === $world) {
			parent::onNearbyBlockChange();
			return;
		}
		foreach($this->getAllSides() as $block) {
			if($block->getIdInfo()->getBlockId() !== Ids::OBSIDIAN) {
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
						$start->getWorld()->setBlock($start->add(0, -$j, $k), (new Portal())->setAxis(Axis::Z), false);
					}else{
						$start->getWorld()->setBlock($start->add(-$k, -$j, 0), (new Portal())->setAxis(Axis::X), false);
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