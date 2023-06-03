<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\world\data;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\world\format\io\data\BedrockWorldData;

class DimensionalBedrockWorldData extends BedrockWorldData{

	private const TAG_NETHER_SCALE = "NetherScale";

	protected function fix() : void{
		if($this->compoundTag->getInt(self::TAG_NETHER_SCALE, -1) === -1){
			$this->compoundTag->setInt(self::TAG_NETHER_SCALE, 8);
		}
		parent::fix();
	}

	public function setGenerator(string $generatorName) : void{
		$this->compoundTag->setString(self::TAG_GENERATOR_NAME, $generatorName);
		$this->fix();
	}

	public function save(int $dimension = 0) : void{
		if($dimension !== 0)
			return;
		parent::save();
	}

	/**
	 * @phpstan-param DimensionIds::* $dimension
	 */
	public function getSpawn(int $dimension = 0) : Vector3{
		if($dimension === DimensionIds::THE_END)
			return new Vector3(100, 50, 0);
		return new Vector3($this->compoundTag->getInt("SpawnX"), $this->compoundTag->getInt("SpawnY"), $this->compoundTag->getInt("SpawnZ"));
	}
}
