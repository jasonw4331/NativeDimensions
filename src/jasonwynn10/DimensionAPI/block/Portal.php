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
		if(!$this->pairExists()) {
			if(strpos($level->getFolderName(), " dim-1") !== false) {
				$level = Main::getDimensionBaseLevel($level);
				if($level !== null) {
					$x = $this->x * 8;
					$z = $this->z * 8;
					$y = $this->y;
				}
			}else {
				$worldName = $level->getFolderName()." dim-1";
				if(!Main::dimensionExists($level, -1)) {
					Main::getInstance()->generateLevelDimension($level->getFolderName(), $level->getSeed(), Nether::class, [], -1);
				}
				$level = Server::getInstance()->getLevelByName($worldName); // 23.35 x 31.6 z
				$x = $this->x / 8;
				$z = $this->z / 8;
				$y = $this->y;
			}
			/** @var Block|null $validBlock */
			$validBlock = null;
			for($i = 0; $i < $level->getWorldHeight(); ++$i) {
				for($c = -$i; $c < $i; ++$c) {
					$validBlock = $level->getBlock(new Vector3($x + $c, $y, $z));
					if($validBlock instanceof Air)
						break;
					$validBlock = $level->getBlock(new Vector3($x, $y + $c, $z));
					if($validBlock instanceof Air)
						break;
					$validBlock = $level->getBlock(new Vector3($x, $y, $z + $c));
					if($validBlock instanceof Air)
						break;
					$validBlock = $level->getBlock(new Vector3($x + $c, $y + $c, $z));
					if($validBlock instanceof Air)
						break;
					$validBlock = $level->getBlock(new Vector3($x + $c, $y, $z + $c));
					if($validBlock instanceof Air)
						break;
					$validBlock = $level->getBlock(new Vector3($x, $y + $c, $z + $c));
					if($validBlock instanceof Air)
						break;
				}
			}
			if($validBlock !== null) {
				Main::getInstance()->makePortal($validBlock->asPosition());
			}else {
				$validBlock = new Position($x, $y, $z, $level);
				Main::getInstance()->makePortal($validBlock);
			}
		}else{
			$position = $this->getPair();
			Main::getInstance()->getScheduler()->scheduleDelayedTask(new DimensionTeleportTask($entity, DimensionIds::NETHER, $position), 20 * 4);
		}
	}

	public function pairExists() : bool {
		$level = $this->getLevel();
		if(strpos($level->getFolderName(), " dim-1") !== false) {
			$level = Main::getDimensionBaseLevel($level);
			if($level !== null) {
				$x = $this->x * 8;
				$z = $this->z * 8;
				$y = $this->y;
			}
		}else {
			$worldName = $level->getFolderName()." dim-1";
			if(!Main::dimensionExists($level, -1)) {
				Main::getInstance()->generateLevelDimension($level->getFolderName(), $level->getSeed(), Nether::class, [], -1);
			}
			$level = Server::getInstance()->getLevelByName($worldName); // 23.35 x 31.6 z
			$x = $this->x / 8;
			$z = $this->z / 8;
			$y = $this->y;
		}
		$chunk = $level->getChunk($x >> 4, $z >> 4, true);
		for($k = 0; $k < 256; ++$k) {
			for($i = 0; $i < 16; ++$i) {
				for($j = 0; $j < 16; ++$j) {
					$id = $chunk->getBlockId($i, $k, $j);
					if($id === Block::PORTAL) {
						return true;
					}
				}
			}
		}
		return false;
	}

	public function getPair() : Position {
		$level = $this->getLevel();
		if(strpos($level->getFolderName(), " dim-1") !== false) {
			$level = Main::getDimensionBaseLevel($level);
			if($level !== null) {
				$x = $this->x * 8;
				$z = $this->z * 8;
				$y = $this->y;
			}
		}else {
			$worldName = $level->getFolderName()." dim-1";
			if(!Main::dimensionExists($level, -1)) {
				Main::getInstance()->generateLevelDimension($level->getFolderName(), $level->getSeed(), Nether::class, [], -1);
			}
			$level = Server::getInstance()->getLevelByName($worldName); // 23.35 x 31.6 z
			$x = $this->x / 8;
			$z = $this->z / 8;
			$y = $this->y;
		}
		$chunk = $level->getChunk($x >> 4, $z >> 4, true);
		for($k = 0; $k < 256; ++$k) {
			for($i = 0; $i < 16; ++$i) {
				for($j = 0; $j < 16; ++$j) {
					$id = $chunk->getBlockId($i, $k, $j);
					if($id === Block::PORTAL) {
						return new Position();
					}
				}
			}
		}
		return new Position();
	}
}
