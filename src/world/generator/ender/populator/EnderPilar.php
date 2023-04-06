<?php

declare(strict_types=1);

namespace jasonwynn10\NativeDimensions\world\generator\ender\populator;

use jasonwynn10\NativeDimensions\world\generator\ender\EnderGenerator;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\SubChunk;
use pocketmine\world\generator\populator\Populator;

class EnderPilar implements Populator{
	public const MIN_RADIUS = 3;
	public const MAX_RADIUS = 5;

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		if($random->nextBoundedInt(10) > 0){
			return;
		}

		$chunk = $world->getChunk($chunkX, $chunkZ);
		if($chunk === null){
			throw new AssumptionFailedError("Populated chunk is null");
		}

		$bound = 16 - self::MAX_RADIUS * 2;

		$relativeX = self::MAX_RADIUS + $random->nextBoundedInt($bound);
		$relativeZ = self::MAX_RADIUS + $random->nextBoundedInt($bound);

		$centerY = $this->getWorkableBlockAt($chunk, $relativeX, $relativeZ) - 1;

		$air = VanillaBlocks::AIR()->getFullId();
		if($chunk->getFullBlock($relativeX, $centerY, $relativeZ) === $air){
			return;
		}

		$centerX = $chunkX * SubChunk::EDGE_LENGTH + $relativeX;
		$centerZ = $chunkZ * SubChunk::EDGE_LENGTH + $relativeZ;

		$height = $random->nextRange(28, 50);
		$radius = $random->nextRange(3, 5);
		$radiusSquared = ($radius ** 2) - 1;

		$obsidian = VanillaBlocks::OBSIDIAN();
		for($x = 0; $x <= $radius; ++$x){
			$xSquared = $x ** 2;
			for($z = 0; $z <= $radius; ++$z){
				if($xSquared + $z ** 2 >= $radiusSquared){
					break;
				}

				for($y = 0; $y < $height; ++$y){
					$world->setBlockAt($centerX + $x, $centerY + $y, $centerZ + $z, $obsidian);
					$world->setBlockAt($centerX - $x, $centerY + $y, $centerZ + $z, $obsidian);
					$world->setBlockAt($centerX + $x, $centerY + $y, $centerZ - $z, $obsidian);
					$world->setBlockAt($centerX - $x, $centerY + $y, $centerZ - $z, $obsidian);
				}
			}
		}
	}

	private function getWorkableBlockAt(Chunk $chunk, int $x, int $z) : int{
		$air = VanillaBlocks::AIR()->getFullId();
		for($y = EnderGenerator::MAX_BASE_ISLAND_HEIGHT, $maxY = EnderGenerator::MAX_BASE_ISLAND_HEIGHT + EnderGenerator::NOISE_SIZE; $y <= $maxY; ++$y){
			if($chunk->getFullBlock($x, $y, $z) === $air){
				return $y;
			}
		}

		return $y;
	}
}
