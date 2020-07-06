<?php

/**
 *
 * MMP""MM""YMM               .M"""bgd
 * P'   MM   `7              ,MI    "Y
 *      MM  .gP"Ya   ,6"Yb.  `MMb.   `7MMpdMAo.  ,pW"Wq.   ,pW"Wq.`7MMpMMMb.
 *      MM ,M'   Yb 8)   MM    `YMMNq. MM   `Wb 6W'   `Wb 6W'   `Wb MM    MM
 *      MM 8M""""""  ,pm9MM  .     `MM MM    M8 8M     M8 8M     M8 MM    MM
 *      MM YM.    , 8M   MM  Mb     dM MM   ,AP YA.   ,A9 YA.   ,A9 MM    MM
 *    .JMML.`Mbmmd' `Moo9^Yo.P"Ybmmd"  MMbmmd'   `Ybmd9'   `Ybmd9'.JMML  JMML.
 *                                     MM
 *                                   .JMML.
 * This file is part of TeaSpoon.
 *
 * TeaSpoon is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * TeaSpoon is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with TeaSpoon.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author CortexPE
 * @link https://CortexPE.xyz
 *
 */

declare(strict_types = 1);

namespace jasonwynn10\NativeDimensions\block;

use jasonwynn10\NativeDimensions\Main;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIds;
use pocketmine\block\Solid;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ShowCreditsPacket;
use pocketmine\Player;
use pocketmine\Server;

class EndPortal extends Solid {

	/** @var int $id */
	protected $id = Block::END_PORTAL;

	public function __construct($meta = 0){
		$this->meta = $meta;
	}

	/**
	 * @return int
	 */
	public function getLightLevel(): int{
		return 1;
	}

	/**
	 * @return string
	 */
	public function getName(): string{
		return "End Portal";
	}

	/**
	 * @return float
	 */
	public function getHardness(): float{
		return -1;
	}

	/**
	 * @return float
	 */
	public function getBlastResistance(): float{
		return 18000000;
	}

	/**
	 * @param Item $item
	 * @return bool
	 */
	public function isBreakable(Item $item): bool{
		return false;
	}

	/**
	 * @return bool
	 */
	public function canPassThrough(): bool{
		return true;
	}

	/**
	 * @return bool
	 */
	public function hasEntityCollision(): bool{
		return true;
	}


	/**
	 * @param Entity $entity
	 *
	 */
	public function onEntityCollide(Entity $entity): void{
		$level = $entity->getLevel();
		if(strpos($level->getFolderName(), " dim1") !== false) {
			$overworldLevel = Main::getDimensionBaseLevel($level);
			if($overworldLevel === null) {
				return;
			}
			$overworld = $overworldLevel->getSpawnLocation();
			if($entity instanceof Player) {
				if(in_array($entity->getId(), Main::getTeleporting()))
					return;
				Main::addTeleportingId($entity->getId());
				$data = Server::getInstance()->getOfflinePlayerData($entity->getName());
				if($data->getByte("seenCredits", 0, true) == 0) {
					$data->setByte("seenCredits", 1);
					Server::getInstance()->saveOfflinePlayerData($entity->getName(), $data);
					$pk = new ShowCreditsPacket();
					$pk->playerEid = $entity->getId();
					$pk->status = ShowCreditsPacket::STATUS_START_CREDITS;
					$entity->sendDataPacket($pk);
				}
			}
			$entity->teleport($overworld);
		}else{
			$enderWorldName = $level->getFolderName()." dim1";
			if(!Main::dimensionExists($level, 1)) {
				Main::getInstance()->generateLevelDimension($level->getFolderName(), 1, $level->getSeed());
				Main::getInstance()->getServer()->loadLevel($enderWorldName);
				return;
			}
			$enderLevel = Server::getInstance()->getLevelByName($enderWorldName);
			if($enderLevel !== null) {
				$entity->teleport(new Position(100, 49, 0, $enderLevel));
				for($x = 100 - 2; $x <= 100 + 2; ++$x) {
					for($z = 100 - 2; $z <= 100 + 2; ++$z) {
						$enderLevel->setBlock(new Vector3($x, 48, $z), BlockFactory::get(BlockIds::OBSIDIAN), false, false);
					}
				}
			}
		}
	}
}