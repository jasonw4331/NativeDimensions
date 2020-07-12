<?php
declare(strict_types=1);

namespace jasonwynn10\NativeDimensions\provider;

use pocketmine\level\format\io\leveldb\LevelDB;
use pocketmine\nbt\tag\IntTag;
use pocketmine\utils\Binary;

class NetherLevelDBProvider extends LevelDB {

	protected function fixLevelData() : void{
		if($this->levelData->hasTag("NetherScale", IntTag::class))
			$this->levelData->setInt("NetherScale", 8);

		parent::fixLevelData();
	}

	public static function chunkIndex(int $chunkX, int $chunkZ) : string{
		return Binary::writeLInt($chunkX) . Binary::writeLInt($chunkZ); // TODO: remake index for nether
	}
}