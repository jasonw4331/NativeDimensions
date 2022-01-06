<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\block;

use jasonwynn10\NativeDimensions\world\DimensionalWorld;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier as BID;
use pocketmine\block\BlockLegacyIds as Ids;
use pocketmine\block\Opaque;
use pocketmine\block\utils\FacesOppositePlacingPlayerTrait;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\entity\Entity;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\player\Player;
use pocketmine\world\Position;

class EndPortal extends Opaque{
	use FacesOppositePlacingPlayerTrait;
	use HorizontalFacingTrait;

	public function __construct(){
		parent::__construct(new BID(Ids::END_PORTAL, 0), "End Portal", BlockBreakInfo::indestructible());
	}

	public function getLightLevel() : int{
		return 15;
	}

	/**
	 * @return AxisAlignedBB[]
	 */
	protected function recalculateCollisionBoxes() : array{
		return [AxisAlignedBB::one()->trim(Facing::UP, 1 / 4)];
	}

	public function hasEntityCollision() : bool{
		return true;
	}

	public function onEntityInside(Entity $entity): bool{
		/** @var DimensionalWorld $world */
		$world = $entity->getPosition()->getWorld();
		if($world->getEnd() === $world)
			return true;

		if($world->getOverworld() === $world) {
			$position = new Position(100, 50, 0, $world->getEnd());
			$entity->getWorld()->getLogger()->debug("Teleporting to The End");
		}else {
			$position = $entity instanceof Player ? ($entity->getSpawn() ?? $world->getOverworld()->getSafeSpawn()) : $world->getOverworld()->getSafeSpawn();
			$entity->getWorld()->getLogger()->debug("Teleporting to Overworld");
		}

		$entity->teleport($position);
		return true;
	}

}