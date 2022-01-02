<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\world\data;

use pocketmine\nbt\tag\IntTag;
use pocketmine\world\format\io\data\BedrockWorldData;

class DimensionalBedrockWorldData extends BedrockWorldData {
	protected function fix() : void{
		if(!($this->compoundTag->getTag("NetherScale")) instanceof IntTag) {
			$this->compoundTag->setInt("NetherScale", 8);
		}
		parent::fix();
	}
}