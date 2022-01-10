<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\world\data;

use pocketmine\math\Vector3;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\world\format\io\data\BedrockWorldData;

class DimensionalBedrockWorldData extends BedrockWorldData {
	protected function fix() : void{
		if(!($this->compoundTag->getTag("NetherScale")) instanceof IntTag) {
			$this->compoundTag->setInt("NetherScale", 8);
		}
		parent::fix();
	}

	public function setGenerator(string $generatorName) {
		$this->compoundTag->setString("generatorName", $generatorName);
		$this->fix();
	}

	public function setName(string $name) {
		$this->compoundTag->setString("LevelName", $name);
	}

	public function save(int $dimension = 0) : void{
		if($dimension !== 0)
			return;
		parent::save();
	}

	public function getSpawn(int $dimension = 0) : Vector3{
		if($dimension === DimensionIds::THE_END)
			return new Vector3(100, 50, 0);
		return new Vector3($this->compoundTag->getInt("SpawnX"), $this->compoundTag->getInt("SpawnY"), $this->compoundTag->getInt("SpawnZ"));
	}
}