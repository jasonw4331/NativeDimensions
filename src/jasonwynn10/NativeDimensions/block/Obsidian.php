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
 * @link   https://CortexPE.xyz
 *
 */

declare(strict_types=1);

namespace jasonwynn10\NativeDimensions\block;

use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\Obsidian as PMObsidian;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\Player;

class Obsidian extends PMObsidian {

	public function onBreak(Item $item, Player $player = null): bool {
		parent::onBreak($item);
		foreach($this->getAllSides() as $i => $block){
			if($block instanceof Portal){
				if($block->getSide(Vector3::SIDE_WEST) instanceof Portal or
					$block->getSide(Vector3::SIDE_EAST) instanceof Portal
				) {//x方向
					for(
						$x = $block->x; $this->getLevel()->getBlockIdAt($x, $block->y, $block->z) == Block::PORTAL; $x++
					) {
						for(
							$y = $block->y; $this->getLevel()->getBlockIdAt($x, $y, $block->z) == Block::PORTAL; $y++
						) {
							$this->getLevel()->setBlock(new Vector3($x, $y, $block->z), new Air());
						}
						for(
							$y = $block->y - 1; $this->getLevel()->getBlockIdAt($x, $y, $block->z) == Block::PORTAL; $y--
						) {
							$this->getLevel()->setBlock(new Vector3($x, $y, $block->z), new Air());
						}
					}
					for(
						$x = $block->x - 1; $this->getLevel()->getBlockIdAt($x, $block->y, $block->z) == Block::PORTAL; $x--
					) {
						for(
							$y = $block->y; $this->getLevel()->getBlockIdAt($x, $y, $block->z) == Block::PORTAL; $y++
						) {
							$this->getLevel()->setBlock(new Vector3($x, $y, $block->z), new Air());
						}
						for(
							$y = $block->y - 1; $this->getLevel()->getBlockIdAt($x, $y, $block->z) == Block::PORTAL; $y--
						) {
							$this->getLevel()->setBlock(new Vector3($x, $y, $block->z), new Air());
						}
					}
				} else {//z方向
					for(
						$z = $block->z; $this->getLevel()->getBlockIdAt($block->x, $block->y, $z) == Block::PORTAL; $z++
					) {
						for(
							$y = $block->y; $this->getLevel()->getBlockIdAt($block->x, $y, $z) == Block::PORTAL; $y++
						) {
							$this->getLevel()->setBlock(new Vector3($block->x, $y, $z), new Air());
						}
						for(
							$y = $block->y - 1; $this->getLevel()->getBlockIdAt($block->x, $y, $z) == Block::PORTAL; $y--
						) {
							$this->getLevel()->setBlock(new Vector3($block->x, $y, $z), new Air());
						}
					}
					for(
						$z = $block->z - 1;$this->getLevel()->getBlockIdAt($block->x, $block->y, $z) == Block::PORTAL; $z--
					) {
						for(
							$y = $block->y; $this->getLevel()->getBlockIdAt($block->x, $y, $z) == Block::PORTAL; $y++
						) {
							$this->getLevel()->setBlock(new Vector3($block->x, $y, $z), new Air());
						}
						for(
							$y = $block->y - 1; $this->getLevel()->getBlockIdAt($block->x, $y, $z) == Block::PORTAL; $y--
						) {
							$this->getLevel()->setBlock(new Vector3($block->x, $y, $z), new Air());
						}
					}
				}
				return true;
			}
		}

		return true;
	}
}