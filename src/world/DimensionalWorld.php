<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\world;

use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\scheduler\AsyncPool;
use pocketmine\Server;
use pocketmine\world\format\io\WritableWorldProvider;
use pocketmine\world\World;

class DimensionalWorld extends World {

	private int $dimensionId;

	public function __construct(Server $server, string $name, WritableWorldProvider $provider, AsyncPool $workerPool, int $dimensionId){
		$this->dimensionId = $dimensionId;
		parent::__construct($server, $name, $provider, $workerPool);
	}

	public function getOverworld() : World {
		if($this->dimensionId === DimensionIds::OVERWORLD)
			return $this;
		return $this->getServer()->getWorldManager()->getWorld($this->getId() + (DimensionIds::OVERWORLD - $this->dimensionId));
	}

	public function getNether() : World {
		if($this->dimensionId === DimensionIds::NETHER)
			return $this;
		return $this->getServer()->getWorldManager()->getWorld($this->getId() + (DimensionIds::NETHER - $this->dimensionId));
	}

	public function getEnd() : World {
		if($this->dimensionId === DimensionIds::THE_END)
			return $this;
		return $this->getServer()->getWorldManager()->getWorld($this->getId() + (DimensionIds::THE_END - $this->dimensionId));
	}

	public function getDimensionId() : int {
		return $this->dimensionId;
	}
}