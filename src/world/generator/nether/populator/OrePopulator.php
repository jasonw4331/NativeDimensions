<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\world\generator\nether\populator;

use jasonw4331\NativeDimensions\world\generator\nether\populator\biome\OrePopulator as OverworldOrePopulator;
use jasonw4331\NativeDimensions\world\generator\object\OreType;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\World;

class OrePopulator extends OverworldOrePopulator{

	/**
	 * @noinspection MagicMethodsValidityInspection
	 * @noinspection PhpMissingParentConstructorInspection
	 */
	public function __construct(int $world_height = World::Y_MAX){
		$this->addOre(new OreType(VanillaBlocks::NETHER_QUARTZ_ORE(), 10, $world_height - (10 * ($world_height >> 7)), 13, BlockLegacyIds::NETHERRACK), 16);
		$this->addOre(new OreType(VanillaBlocks::MAGMA(), 26, 32 + (5 * ($world_height >> 7)), 32, BlockLegacyIds::NETHERRACK), 16);
	}
}
