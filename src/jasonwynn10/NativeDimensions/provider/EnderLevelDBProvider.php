<?php
declare(strict_types=1);

namespace jasonwynn10\NativeDimensions\provider;

use pocketmine\level\format\io\leveldb\LevelDB;
use pocketmine\utils\Binary;

class EnderLevelDBProvider extends LevelDB {
	public static function chunkIndex(int $chunkX, int $chunkZ) : string{
		return Binary::writeLInt($chunkX) . Binary::writeLInt($chunkZ); // TODO: remake index for end
	}
}