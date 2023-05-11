<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\block;

use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier as BID;
use pocketmine\block\BlockLegacyIds as Ids;
use pocketmine\block\BlockToolType;
use pocketmine\block\NetherPortal;
use pocketmine\block\Opaque;
use pocketmine\item\ToolTier;

class Obsidian extends Opaque{

	public function __construct(){
		parent::__construct(new BID(Ids::OBSIDIAN, 0), "Obsidian", new BlockBreakInfo(35.0 /* 50 in PC */, BlockToolType::PICKAXE, ToolTier::DIAMOND()->getHarvestLevel(), 6000.0));
	}

	public function getAffectedBlocks() : array{
		$return = [$this];
		foreach($this->getAllSides() as $block){
			if($block instanceof NetherPortal)
				$return[] = $block;
		}
		return $return;
	}

}
