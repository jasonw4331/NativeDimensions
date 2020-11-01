<?php
declare(strict_types=1);

namespace jasonwynn10\NativeDimensions\provider;

use pocketmine\level\format\io\leveldb\LevelDB;
use pocketmine\utils\Binary;

class EnderLevelDBProvider extends LevelDB {
	public function getName() : string {
		return parent::getName()." dim1";
	}

	public static function chunkIndex(int $chunkX, int $chunkZ) : string{
		return Binary::writeLInt($chunkX) . Binary::writeLInt($chunkZ) . Binary::writeLInt(2);
	}
}