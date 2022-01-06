<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\block;

use jasonwynn10\NativeDimensions\Main;
use jasonwynn10\NativeDimensions\world\DimensionalWorld;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier as BID;
use pocketmine\block\BlockLegacyIds as Ids;
use pocketmine\block\BlockLegacyMetadata;
use pocketmine\block\Opaque;
use pocketmine\block\utils\BlockDataSerializer;
use pocketmine\block\utils\FacesOppositePlacingPlayerTrait;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\entity\Entity;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\Position;

class EndPortal extends Opaque{
	use FacesOppositePlacingPlayerTrait;
	use HorizontalFacingTrait;

	protected bool $eye = false;

	public function __construct(){
		parent::__construct(new BID(Ids::END_PORTAL, 0), "End Portal", BlockBreakInfo::indestructible());
	}

	protected function writeStateToMeta() : int{
		return BlockDataSerializer::writeLegacyHorizontalFacing($this->facing) | ($this->eye ? BlockLegacyMetadata::END_PORTAL_FRAME_FLAG_EYE : 0);
	}

	public function readStateFromData(int $id, int $stateMeta) : void{
		$this->facing = BlockDataSerializer::readLegacyHorizontalFacing($stateMeta & 0x03);
		$this->eye = ($stateMeta & BlockLegacyMetadata::END_PORTAL_FRAME_FLAG_EYE) !== 0;
	}

	public function getStateBitmask() : int{
		return 0b111;
	}

	public function getLightLevel() : int{
		return 1;
	}

	/**
	 * @return AxisAlignedBB[]
	 */
	protected function recalculateCollisionBoxes() : array{
		return [AxisAlignedBB::one()->trim(Facing::UP, 5 / 16)];
	}

	public function hasEntityCollision() : bool{
		return true;
	}

	public function onEntityInside(Entity $entity): bool{
		/** @var DimensionalWorld $world */
		$world = $entity->getPosition()->getWorld();
		if($world->getEnd() === $world)
			return true;

		if(!in_array($entity->getId(), Main::getTeleporting())){
			Main::addTeleportingId($entity->getId());

			if($world->getOverworld() === $world) {
				$position = new Position(100, 50, 0, $world->getEnd());
				$entity->getWorld()->getLogger()->debug("Teleporting to Nether");
			}else {
				$position = $entity instanceof Player ? ($entity->getSpawn() ?? $world->getOverworld()->getSafeSpawn()) : $world->getOverworld()->getSafeSpawn();
				$entity->getWorld()->getLogger()->debug("Teleporting to Overworld");
			}

			$entity->teleport($position);
		}
		return true;
	}

}