<?php

/*
 *
 *  _____   _____   __   _   _   _____  __    __  _____
 * /  ___| | ____| |  \ | | | | /  ___/ \ \  / / /  ___/
 * | |     | |__   |   \| | | | | |___   \ \/ /  | |___
 * | |  _  |  __|  | |\   | | | \___  \   \  /   \___  \
 * | |_| | | |___  | | \  | | |  ___| |   / /     ___| |
 * \_____/ |_____| |_|  \_| |_| /_____/  /_/     /_____/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author iTX Technologies
 * @link https://itxtech.org
 *
 */

declare(strict_types = 1);

namespace jasonwynn10\DimensionAPI\block;

use jasonwynn10\DimensionAPI\Main;
use jasonwynn10\DimensionAPI\task\DimensionTeleportTask;
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\BlockToolType;
use pocketmine\block\Transparent;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\level\generator\hell\Nether;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\Player;
use pocketmine\Server;

class Portal extends Transparent {

	/** @var int $id */
	protected $id = Block::PORTAL;

	public function __construct($meta = 0){
		$this->meta = $meta;
	}

	public function getName(): string{
		return "Portal";
	}

	public function getHardness(): float{
		return -1;
	}

	public function getResistance(): float{
		return 0;
	}

	public function getToolType(): int{
		return BlockToolType::TYPE_PICKAXE;
	}

	public function canPassThrough(): bool{
		return true;
	}

	public function canBeFlowedInto() : bool{
		return true;
	}

	public function hasEntityCollision(): bool{
		return true;
	}

	/**
	 * @param Item $item
	 * @param Player|null $player
	 * @return bool
	 */
	public function onBreak(Item $item, Player $player = null): bool{
		if($this->getSide(Vector3::SIDE_WEST) instanceof Portal or
			$this->getSide(Vector3::SIDE_EAST) instanceof Portal
		){//x方向
			for($x = $this->x; $this->getLevel()->getBlockIdAt($x, $this->y, $this->z) == Block::PORTAL; $x++){
				for($y = $this->y; $this->getLevel()->getBlockIdAt($x, $y, $this->z) == Block::PORTAL; $y++){
					$this->getLevel()->setBlock(new Vector3($x, $y, $this->z), new Air());
				}
				for($y = $this->y - 1; $this->getLevel()->getBlockIdAt($x, $y, $this->z) == Block::PORTAL; $y--){
					$this->getLevel()->setBlock(new Vector3($x, $y, $this->z), new Air());
				}
			}
			for($x = $this->x - 1; $this->getLevel()->getBlockIdAt($x, $this->y, $this->z) == Block::PORTAL; $x--){
				for($y = $this->y; $this->getLevel()->getBlockIdAt($x, $y, $this->z) == Block::PORTAL; $y++){
					$this->getLevel()->setBlock(new Vector3($x, $y, $this->z), new Air());
				}
				for($y = $this->y - 1; $this->getLevel()->getBlockIdAt($x, $y, $this->z) == Block::PORTAL; $y--){
					$this->getLevel()->setBlock(new Vector3($x, $y, $this->z), new Air());
				}
			}
		}else{//z方向
			for($z = $this->z; $this->getLevel()->getBlockIdAt($this->x, $this->y, $z) == Block::PORTAL; $z++){
				for($y = $this->y; $this->getLevel()->getBlockIdAt($this->x, $y, $z) == Block::PORTAL; $y++){
					$this->getLevel()->setBlock(new Vector3($this->x, $y, $z), new Air());
				}
				for($y = $this->y - 1; $this->getLevel()->getBlockIdAt($this->x, $y, $z) == Block::PORTAL; $y--){
					$this->getLevel()->setBlock(new Vector3($this->x, $y, $z), new Air());
				}
			}
			for($z = $this->z - 1; $this->getLevel()->getBlockIdAt($this->x, $this->y, $z) == Block::PORTAL; $z--){
				for($y = $this->y; $this->getLevel()->getBlockIdAt($this->x, $y, $z) == Block::PORTAL; $y++){
					$this->getLevel()->setBlock(new Vector3($this->x, $y, $z), new Air());
				}
				for($y = $this->y - 1; $this->getLevel()->getBlockIdAt($this->x, $y, $z) == Block::PORTAL; $y--){
					$this->getLevel()->setBlock(new Vector3($this->x, $y, $z), new Air());
				}
			}
		}

		return true;
	}

	/**
	 * @param Item $item
	 * @param Block $block
	 * @param Block $target
	 * @param int $face
	 * @param Vector3 $facePos
	 * @param Player|null $player
	 * @return bool
	 */
	public function place(Item $item, Block $block, Block $target, int $face, Vector3 $facePos, Player $player = null): bool{
		if($player instanceof Player){
			$this->meta = $player->getDirection() & 0x01;
		}
		if(strpos($this->getLevel()->getFolderName(), " dim1") !== false)
			return true;
		$this->getLevel()->setBlock($block, $this, true, true);

		// TODO: levelDB portal mapping

		return true;
	}

	/**
	 * @param Item $item
	 * @return array
	 */
	public function getDrops(Item $item): array{
		return [];
	}

	/**
	 * @param Entity $entity
	 */
	public function onEntityCollide(Entity $entity): void{
		// TODO: track recent teleports
		$level = $entity->getLevel();
		if(strpos($level->getFolderName(), " dim-1") !== false) {
			$overworldLevel = Main::getDimensionBaseLevel($level);
			if($overworldLevel !== null) {
				$overworldX = $this->x * 8;
				$overworldZ = $this->z * 8;
				$overworldY = $this->y;
				/*for($i = 0; $i < $overworldLevel->getWorldHeight(); ++$i) {
					for($c = -$i; $c < $i; ++$c) {
						$block = $overworldLevel->getBlock(new Vector3($overworldX + $c, $overworldY, $overworldZ));
						if($block instanceof Portal and !$block->getSide(Vector3::SIDE_UP) instanceof Obsidian) {
							Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::OVERWORLD, new Position($overworldX, $overworldY, $overworldZ, $overworldLevel)), 20 * 4);
							return;
						}
						$block = $overworldLevel->getBlock(new Vector3($overworldX, $overworldY + $c, $overworldZ));
						if($block instanceof Portal and !$block->getSide(Vector3::SIDE_UP) instanceof Obsidian) {
							Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::OVERWORLD, new Position($overworldX, $overworldY, $overworldZ, $overworldLevel)), 20 * 4);
							return;
						}
						$block = $overworldLevel->getBlock(new Vector3($overworldX, $overworldY, $overworldZ + $c));
						if($block instanceof Portal and !$block->getSide(Vector3::SIDE_UP) instanceof Obsidian) {
							Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::OVERWORLD, new Position($overworldX, $overworldY, $overworldZ, $overworldLevel)), 20 * 4);
							return;
						}
						$block = $overworldLevel->getBlock(new Vector3($overworldX + $c, $overworldY + $c, $overworldZ));
						if($block instanceof Portal and !$block->getSide(Vector3::SIDE_UP) instanceof Obsidian) {
							Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::OVERWORLD, new Position($overworldX, $overworldY, $overworldZ, $overworldLevel)), 20 * 4);
							return;
						}
						$block = $overworldLevel->getBlock(new Vector3($overworldX + $c, $overworldY, $overworldZ + $c));
						if($block instanceof Portal and !$block->getSide(Vector3::SIDE_UP) instanceof Obsidian) {
							Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::OVERWORLD, new Position($overworldX, $overworldY, $overworldZ, $overworldLevel)), 20 * 4);
							return;
						}
						$block = $overworldLevel->getBlock(new Vector3($overworldX, $overworldY + $c, $overworldZ + $c));
						if($block instanceof Portal and !$block->getSide(Vector3::SIDE_UP) instanceof Obsidian) {
							Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::OVERWORLD, new Position($overworldX, $overworldY, $overworldZ, $overworldLevel)), 20 * 4);
							return;
						}
					}
				}*/

				$exists = false;
				$chunk = $overworldLevel->getChunk($overworldX >> 4, $overworldZ >> 4, true);
				for($k = 0; $k < 256; ++$k) {
					for($i = 0; $i < 16; ++$i) {
						for($j = 0; $j < 16; ++$j) {
							$id = $chunk->getBlockId($i, $k, $j);
							if($id === Block::PORTAL) {
								$exists = true;
								Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::OVERWORLD, new Position($chunk->getX() << 4, $j, $chunk->getZ() << 4, $overworldLevel)), 20 * 4);
								break 3;
							}
						}
					}
				}
				if(!$exists) {
					/** @var Position|null $validBlock */
					$validBlock = null;
					for($i = 0; $i < $overworldLevel->getWorldHeight(); ++$i) {
						for($c = -$i; $c < $i; ++$c) {
							$validBlock = $overworldLevel->getBlock(new Vector3($overworldX + $c, $overworldY, $overworldZ));
							if($validBlock instanceof Air) {
								Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::OVERWORLD, new Position($overworldX, $overworldY, $overworldZ, $overworldLevel)), 20 * 4);
								return;
							}
							$validBlock = $overworldLevel->getBlock(new Vector3($overworldX, $overworldY + $c, $overworldZ));
							if($validBlock instanceof Air) {
								Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::OVERWORLD, new Position($overworldX, $overworldY, $overworldZ, $overworldLevel)), 20 * 4);
								return;
							}
							$validBlock = $overworldLevel->getBlock(new Vector3($overworldX, $overworldY, $overworldZ + $c));
							if($validBlock instanceof Air) {
								Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::OVERWORLD, new Position($overworldX, $overworldY, $overworldZ, $overworldLevel)), 20 * 4);
								return;
							}
							$validBlock = $overworldLevel->getBlock(new Vector3($overworldX + $c, $overworldY + $c, $overworldZ));
							if($validBlock instanceof Air) {
								Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::OVERWORLD, new Position($overworldX, $overworldY, $overworldZ, $overworldLevel)), 20 * 4);
								return;
							}
							$validBlock = $overworldLevel->getBlock(new Vector3($overworldX + $c, $overworldY, $overworldZ + $c));
							if($validBlock instanceof Air) {
								Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::OVERWORLD, new Position($overworldX, $overworldY, $overworldZ, $overworldLevel)), 20 * 4);
								return;
							}
							$validBlock = $overworldLevel->getBlock(new Vector3($overworldX, $overworldY + $c, $overworldZ + $c));
							if($validBlock instanceof Air) {
								Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::OVERWORLD, new Position($overworldX, $overworldY, $overworldZ, $overworldLevel)), 20 * 4);
								return;
							}
						}
					}
					if($validBlock !== null){
						Main::getInstance()->makePortal($validBlock->asPosition());
					}else {
						$validBlock = new Position($overworldX, $overworldY, $overworldZ, $overworldLevel);
						Main::getInstance()->makePortal($validBlock);
					}
					Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::OVERWORLD, $validBlock->asPosition()), 20 * 4);
					Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::OVERWORLD, $validBlock->asPosition()), 20 * 4 + 5);
				}
			}
			return;
		}
		$netherWorldName = $level->getFolderName()." dim-1";
		if(!Main::dimensionExists($level, -1)) {
			Main::getInstance()->generateLevelDimension($level->getFolderName(), $level->getSeed(), Nether::class, [], -1);
		}
		$netherLevel = Server::getInstance()->getLevelByName($netherWorldName); // 23.35 x 31.6 z
		$netherX = $this->x / 8;
		$netherZ = $this->z / 8;
		$netherY = $this->y;

		$exists = false;
		$chunk = $netherLevel->getChunk($netherX >> 4, $netherZ >> 4, true);
		for($k = 0; $k < 256; ++$k) {
			for($i = 0; $i < 16; ++$i) {
				for($j = 0; $j < 16; ++$j) {
					$id = $chunk->getBlockId($i, $k, $j);
					if($id === Block::PORTAL) {
						$exists = true;
						Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::NETHER, new Position($chunk->getX() << 4, $j, $chunk->getZ() << 4, $netherLevel)), 20 * 4);
						break 3;
					}
				}
			}
		}
		if(!$exists) {
			/** @var Block|null $validBlock */
			$validBlock = null;
			for($i = 0; $i < $netherLevel->getWorldHeight(); ++$i) {
				for($c = -$i; $c < $i; ++$c) {
					$validBlock = $netherLevel->getBlock(new Vector3($netherX + $c, $netherY, $netherZ));
					if($validBlock instanceof Air)
						break;
					$validBlock = $netherLevel->getBlock(new Vector3($netherX, $netherY + $c, $netherZ));
					if($validBlock instanceof Air)
						break;
					$validBlock = $netherLevel->getBlock(new Vector3($netherX, $netherY, $netherZ + $c));
					if($validBlock instanceof Air)
						break;
					$validBlock = $netherLevel->getBlock(new Vector3($netherX + $c, $netherY + $c, $netherZ));
					if($validBlock instanceof Air)
						break;
					$validBlock = $netherLevel->getBlock(new Vector3($netherX + $c, $netherY, $netherZ + $c));
					if($validBlock instanceof Air)
						break;
					$validBlock = $netherLevel->getBlock(new Vector3($netherX, $netherY + $c, $netherZ + $c));
					if($validBlock instanceof Air)
						break;
				}
			}
			if($validBlock !== null) {
				Main::getInstance()->makePortal($validBlock->asPosition());
			}else {
				$validBlock = new Position($netherX, $netherY, $netherZ, $netherLevel);
				Main::getInstance()->makePortal($validBlock);
			}
			Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::NETHER, $validBlock->asPosition()), 20 * 4);
			Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::NETHER, $validBlock->asPosition()), 20 * 4 + 5);
		}
	}
}
