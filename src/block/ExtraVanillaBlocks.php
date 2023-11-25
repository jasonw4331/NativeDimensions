<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\block;

use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo as BreakInfo;
use pocketmine\block\BlockIdentifier as BID;
use pocketmine\block\BlockTypeIds as Ids;
use pocketmine\block\BlockTypeInfo as Info;
use pocketmine\utils\CloningRegistryTrait;

/**
 * @generate-registry-docblock
 *
 * @method static EndPortal END_PORTAL()
 * @method static NetherPortal NETHER_PORTAL()
 */
final class ExtraVanillaBlocks{
	use CloningRegistryTrait;

	private function __construct(){
		//NOOP
	}

	protected static function register(string $name, Block $block) : void{
		self::_registryRegister($name, $block);
	}

	/**
	 * @return Block[]
	 * @phpstan-return array<string, Block>
	 */
	public static function getAll() : array{
		//phpstan doesn't support generic traits yet :(
		/** @var Block[] $result */
		$result = self::_registryGetAll();
		return $result;
	}

	protected static function setup() : void{
		self::register('end_portal', new EndPortal(new BID(Ids::newId()), "End Portal", new Info(BreakInfo::indestructible())));
		self::register('nether_portal', new NetherPortal(new BID(Ids::NETHER_PORTAL), "Nether Portal", new Info(BreakInfo::indestructible())));
	}
}
