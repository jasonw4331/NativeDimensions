<?php
declare(strict_types=1);

namespace jasonwynn10\NativeDimensions\block;

use pocketmine\block\BlockLegacyMetadata;
use pocketmine\block\Opaque;
use pocketmine\block\utils\BlockDataSerializer;
use pocketmine\block\utils\FacesOppositePlacingPlayerTrait;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;

class EndPortal extends Opaque{
	use FacesOppositePlacingPlayerTrait;
	use HorizontalFacingTrait;

	protected bool $eye = false;

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
}