<?php
declare(strict_types=1);

namespace jasonwynn10\DimensionAPI\task;

use jasonwynn10\DimensionAPI\block\EndPortal;
use jasonwynn10\DimensionAPI\block\Portal;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\Player;
use pocketmine\scheduler\Task;

class DimensionTeleportTask extends Task {
	/** @var Entity */
	protected $entity;
	/** @var int */
	protected $dimension;
	/** @var Vector3 */
	protected $position;
	/** @var bool */
	protected $respawn;

	public function __construct(Entity $entity, int $dimension, Vector3 $position, bool $respawn = false){
		$this->dimension = $dimension;
		$this->position = $position;
		$this->respawn = $respawn;
		$this->entity = $entity;
	}

	public function onRun(int $currentTick){
		if($this->entity->getLevel() === null)
			return;
		if(!$this->entity->getLevel()->getBlock($this->entity->floor()) instanceof Portal and !$this->entity->getLevel()->getBlock($this->entity->floor()) instanceof EndPortal) {
			return;
		}

		if($this->entity instanceof Player) {
			$pk = new ChangeDimensionPacket();
			$pk->dimension = $this->dimension;
			$pk->position = $this->position;
			$pk->respawn = $this->respawn;
			$this->entity->dataPacket($pk);

			$this->entity->sendPlayStatus(PlayStatusPacket::PLAYER_SPAWN);
			$this->entity->teleport($this->position);
			$this->entity->sendPlayStatus(PlayStatusPacket::PLAYER_SPAWN);
		}else{
			$this->entity->teleport($this->position);
		}

		return;
	}
}