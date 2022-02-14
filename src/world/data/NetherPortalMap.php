<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\world\data;

use jasonwynn10\NativeDimensions\Main;
use jasonwynn10\NativeDimensions\world\DimensionalWorld;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\utils\SingletonTrait;

final class NetherPortalMap {
	use SingletonTrait;

	/** @var NetherPortalData[][][] $portals */
	private array $portals = [];

	public function addPortal(DimensionalWorld $world, NetherPortalData $portal) : void {
		if(!isset($this->portals[$world->getOverworld()->getId()])){
			$this->portals[$world->getOverworld()->getId()] = [
				DimensionIds::OVERWORLD => [],
				DimensionIds::NETHER => [],
				DimensionIds::THE_END => []
			];
		}
		Main::getInstance()->getLogger()->debug("Mapped portal to world {$world->getDisplayName()}");
		$this->portals[$world->getOverworld()->getId()][$portal->getDimensionId()][] = $portal;
	}

	public function removePortal(DimensionalWorld $world, int $x, int $y, int $z) : void {
		if(!isset($this->portals[$world->getOverworld()->getId()]))
			return;

		Main::getInstance()->getLogger()->debug("Unmapped portal from world {$world->getDisplayName()}");
		$this->portals = array_filter(
			$this->portals[$world->getOverworld()->getId()][$world->getDimensionId()],
			function(NetherPortalData $portal) use($x, $y, $z) : bool {
				return $portal->getX() === $x and $portal->getY() === $y and $portal->getZ() === $z;
			}
		);
	}

	public function getPortals(DimensionalWorld $world) : array {
		if(!isset($this->portals[$world->getOverworld()->getId()]))
			return [];

		Main::getInstance()->getLogger()->debug("Retrieved portals from world {$world->getDisplayName()}");
		return $this->portals[$world->getOverworld()->getId()][$world->getDimensionId()] ?? [];
	}
}