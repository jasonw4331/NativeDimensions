<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\block;

use pocketmine\block\BlockBreakInfo as BreakInfo;
use pocketmine\block\BlockIdentifier as BID;
use pocketmine\block\BlockToolType as ToolType;
use pocketmine\block\BlockTypeIds as Ids;
use pocketmine\block\NetherPortal;
use pocketmine\block\Opaque;
use pocketmine\item\ToolTier;

class Obsidian extends Opaque {

	public function __construct(){
		parent::__construct(new BID(Ids::OBSIDIAN), "Obsidian", new BreakInfo(35.0 /* 50 in PC */, ToolType::PICKAXE, ToolTier::DIAMOND()->getHarvestLevel(), 6000.0));
	}

	public function getAffectedBlocks() : array {
		$return = [$this];
		foreach($this->getAllSides() as $block) {
			if($block instanceof NetherPortal)
				$return[] = $block;
		}
		return $return;
	}

}