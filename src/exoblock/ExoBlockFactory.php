<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\exoblock;

use jasonw4331\NativeDimensions\Main;
use jasonw4331\NativeDimensions\vanilla\ExtraVanillaBlocks;
use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;

final class ExoBlockFactory{

	/** @var ExoBlock[] */
	private static array $blocks = [];

	public static function init(Main $loader) : void{
		$loader->getServer()->getPluginManager()->registerEvents(new ExoBlockEventHandler(), $loader);
		self::initNether();
		self::initEnd();
	}

	private static function initNether() : void{
		self::register(
			new NetherPortalFrameExoBlock(
				24,
				24
			),
			VanillaBlocks::OBSIDIAN()
		);
		self::register(new NetherPortalExoBlock(), VanillaBlocks::NETHER_PORTAL());
	}

	private static function initEnd() : void{
		self::register(new EndPortalFrameExoBlock(), VanillaBlocks::END_PORTAL_FRAME());
		self::register(new EndPortalExoBlock(), ExtraVanillaBlocks::END_PORTAL());
	}

	public static function register(ExoBlock $exo_block, Block $block) : void{
		self::$blocks[$block->getStateId()] = $exo_block;
		foreach(RuntimeBlockStateRegistry::getInstance()->getAllKnownStates() as $state){
			if($state->getTypeId() === $block->getTypeId()){
				self::$blocks[$state->getStateId()] = $exo_block;
			}
		}
	}

	public static function get(Block $block) : ?ExoBlock{
		return self::$blocks[$block->getStateId()] ?? null;
	}
}