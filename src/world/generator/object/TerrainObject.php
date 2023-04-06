<?php

declare(strict_types=1);

namespace jasonwynn10\NativeDimensions\world\generator\object;

use pocketmine\block\BlockLegacyIds;
use pocketmine\block\Flowable;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\World;

abstract class TerrainObject{

	/** @var int[] */
	private static array $PLANT_TYPES;

	public static function init() : void{
		self::$PLANT_TYPES = [];
		foreach([BlockLegacyIds::TALL_GRASS, BlockLegacyIds::YELLOW_FLOWER, BlockLegacyIds::RED_FLOWER, BlockLegacyIds::DOUBLE_PLANT, BlockLegacyIds::BROWN_MUSHROOM, BlockLegacyIds::RED_MUSHROOM] as $block_id){
			self::$PLANT_TYPES[$block_id] = $block_id;
		}
	}

	/**
	 * Removes weak blocks like grass, shrub, flower or mushroom directly above the given block, if present.
	 * Does not drop an item.
	 *
	 * @return bool whether a block was removed; false if none was present
	 */
	public static function killWeakBlocksAbove(ChunkManager $world, int $x, int $y, int $z) : bool{
		$cur_y = $y + 1;
		$changed = false;

		while($cur_y < World::Y_MAX){
			$block = $world->getBlockAt($x, $cur_y, $z);
			if(!($block instanceof Flowable)){
				break;
			}
			$world->setBlockAt($x, $cur_y, $z, VanillaBlocks::AIR());
			$changed = true;
			++$cur_y;
		}

		return $changed;
	}

	/**
	 * Generates this feature.
	 *
	 * @param ChunkManager $world    the world to generate in
	 * @param Random       $random   the PRNG that will choose the size and a few details of the shape
	 * @param int          $source_x the base X coordinate
	 * @param int          $source_y the base Y coordinate
	 * @param int          $source_z the base Z coordinate
	 *
	 * @return bool if successfully generated
	 */
	abstract public function generate(ChunkManager $world, Random $random, int $source_x, int $source_y, int $source_z) : bool;
}

TerrainObject::init();
