<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\world\data;

use pocketmine\math\Axis;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;

class NetherPortalData{
	public function __construct(protected int $span, protected int $axis, protected int $dimensionId, protected int $x, protected int $y, protected int $z){ }

	public function getSpan() : int{
		return $this->span;
	}

	public function getAxis() : int{
		return $this->axis;
	}

	public function getDimensionId() : int{
		return $this->dimensionId;
	}

	public function getX() : int{
		return $this->x;
	}

	public function getY() : int{
		return $this->y;
	}

	public function getZ() : int{
		return $this->z;
	}

	public function getVector3() : Vector3 {
		return new Vector3($this->x, $this->y, $this->z);
	}

	public function getCompoundTag() : CompoundTag {
		return CompoundTag::create()
			->setByte('Span', $this->span)
			->setByte('Xa', $this->axis === Axis::X ? 1 : 0)
			->setByte('Za', $this->axis === Axis::Z ? 1 : 0)
			->setInt('DimId', $this->dimensionId)
			->setInt('TpX', $this->x)
			->setInt('TpY', $this->y)
			->setInt('TpZ', $this->z);
	}

	public static function fromCompoundTag(CompoundTag $tag) : self {
		return new self(
			$tag->getByte('Span'),
			$tag->getByte('Xa') === 1 ? Axis::X : Axis::Z,
			$tag->getInt('DimId'),
			$tag->getInt('TpX'),
			$tag->getInt('TpY'),
			$tag->getInt('TpZ')
		);
	}
}