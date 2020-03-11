<?php
declare(strict_types = 1);
namespace jasonwynn10\DimensionAPI\block;

use jasonwynn10\DimensionAPI\Main;
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIds;
use pocketmine\block\BlockToolType;
use pocketmine\block\Thin;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;

class Portal extends Thin {

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
		){//x direction
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
		}else{//z direction
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
		if($this->getSide(Vector3::SIDE_DOWN)->getId() === Block::PORTAL)
			return;
		$originLevel = $entity->getLevel();
		$position = $this->getPair();
		if($position === null) {
			if(strpos($originLevel->getFolderName(), " dim-1") !== false) {
				$level = Main::getDimensionBaseLevel($originLevel);
				$x = $this->x * 8;
				$z = $this->z * 8;
				$y = $this->y;
			}else {
				$worldName = $originLevel->getFolderName()." dim-1";
				if(!Main::dimensionExists($originLevel, -1)) {
					Main::getInstance()->generateLevelDimension($originLevel->getFolderName(), -1, $originLevel->getSeed());
					Main::getInstance()->getServer()->loadLevel($worldName);
					return;
				}
				$level = Server::getInstance()->getLevelByName($worldName); // 23.35 x 31.6 z
				$x = $this->x / 8;
				$z = $this->z / 8;
				$y = $this->y;
			}
			$validBlock = $this->getGenerationSpace($x, $y, $z, $level);
			if($validBlock instanceof Position) {
				$this->makePortal($validBlock);
			}else {
				$this->makePortal(new Position($x, $y, $z, $level));
			}
		}elseif(!in_array($entity->getId(), Main::getTeleporting())){
			if($entity instanceof Player) {
				Main::addTeleportingId($entity->getId());
				if($entity->isCreative()) {
					$entity->teleport($position);
					return;
				}
				Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function(int $currentTick) use($entity, $position) : void {
					if(!$entity->getLevel()->getBlock($entity->floor()) instanceof Portal) {
						return;
					}
					$entity->teleport($position);
				}), 20 * 6);
			}elseif(!$entity instanceof Player) {
				$entity->teleport($position);
			}
		}
	}

	public function getGenerationSpace(float $x, float $y, float $z, Level $level) : ?Position {
		for($chunkX = ($x >> 4) - 3; $chunkX <= ($x >> 4) + 4; ++$chunkX) {
			for($chunkZ = ($z >> 4) - 3; $chunkZ <= ($z >> 4) + 4; ++$chunkZ) {
				$chunk = $level->getChunk($chunkX, $chunkZ, true);
				for($k = 0; $k < $level->getWorldHeight(); ++$k) {
					for($i = 0; $i < 16; ++$i) {
						for($j = 0; $j < 16; ++$j) {
							$id = $chunk->getBlockId($i, $k, $j);
							if($id === Block::AIR and $chunk->getBlockId($i, $k-1, $j) !== 0) {
								return new Position(($chunk->getX() << 4) + $i, $k, ($chunk->getZ() << 4) + $j, $level);
							}
						}
					}
				}
			}
		}
		return null;
	}

	public function getPair() : ?Position {
		$currentLevel = $this->getLevel();
		if(strpos($currentLevel->getFolderName(), " dim-1") !== false) {
			$level = Main::getDimensionBaseLevel($currentLevel);
			$x = (int)ceil($this->x * 8);
			$z = (int)ceil($this->z * 8);
			//$y = $this->y;
		}else {
			$worldName = $currentLevel->getFolderName()." dim-1";
			if(!Main::dimensionExists($currentLevel, -1) or !Server::getInstance()->isLevelGenerated($worldName)) {
				Main::getInstance()->generateLevelDimension($currentLevel->getFolderName(), -1, $currentLevel->getSeed());
				Main::getInstance()->getServer()->loadLevel($worldName);
				return null;
			}
			$level = Server::getInstance()->getLevelByName($worldName);
			$x = (int)ceil($this->x / 8);
			$z = (int)ceil($this->z / 8);
			//$y = $this->y;
		}

		for($chunkX = ($x >> 4) - 3; $chunkX <= ($x >> 4) + 4; ++$chunkX) {
			for($chunkZ = ($z >> 4) - 3; $chunkZ <= ($z >> 4) + 4; ++$chunkZ) {
				$chunk = $level->getChunk($chunkX, $chunkZ, true);
				for($k = 0; $k < $level->getWorldHeight(); ++$k) {
					for($i = 0; $i < 16; ++$i) {
						for($j = 0; $j < 16; ++$j) {
							$id = $chunk->getBlockId($i, $k, $j);
							if($id === Block::PORTAL and $chunk->getBlockId($i, $k-1, $j) !== Block::PORTAL) {
								return new Position(($chunk->getX() << 4) + $i, $k, ($chunk->getZ() << 4) + $j, $level);
							}
						}
					}
				}
			}
		}
		return null;
	}

	public function makePortal(Position $position) : bool {
		if(!$position->isValid())
			return false;
		$level = $position->getLevel();
		if(strpos($level->getFolderName(), " dim1"))
			return false; // no portals in the end
		$xDirection = (bool)mt_rand(0,1);
		if($xDirection) {
			// portals
			$level->setBlock($position, BlockFactory::get(BlockIds::PORTAL), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_UP), BlockFactory::get(BlockIds::PORTAL), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_UP, 2), BlockFactory::get(BlockIds::PORTAL), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::PORTAL), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_NORTH)->getSide(Vector3::SIDE_UP), BlockFactory::get(BlockIds::PORTAL), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_NORTH)->getSide(Vector3::SIDE_UP, 2), BlockFactory::get(BlockIds::PORTAL), true, false);
			// obsidian
			$level->setBlock($position->getSide(Vector3::SIDE_SOUTH), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_SOUTH)->getSide(Vector3::SIDE_DOWN), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_SOUTH)->getSide(Vector3::SIDE_UP), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_SOUTH)->getSide(Vector3::SIDE_UP, 2), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_SOUTH)->getSide(Vector3::SIDE_UP, 3), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_DOWN), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_DOWN)->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_UP, 3), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_UP, 3)->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_NORTH, 2), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_NORTH, 2)->getSide(Vector3::SIDE_DOWN), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_NORTH, 2)->getSide(Vector3::SIDE_UP), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_NORTH, 2)->getSide(Vector3::SIDE_UP, 2), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_NORTH, 2)->getSide(Vector3::SIDE_UP, 3), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
			// air
			$level->setBlock($position->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::AIR), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_UP)->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::AIR), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_UP, 2)->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::AIR), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::AIR), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP)->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::AIR), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP, 2)->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::AIR), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_WEST), BlockFactory::get(BlockIds::AIR), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_UP)->getSide(Vector3::SIDE_WEST), BlockFactory::get(BlockIds::AIR), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_UP, 2)->getSide(Vector3::SIDE_WEST), BlockFactory::get(BlockIds::AIR), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_WEST), BlockFactory::get(BlockIds::AIR), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP)->getSide(Vector3::SIDE_WEST), BlockFactory::get(BlockIds::AIR), true, false);
			$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP, 2)->getSide(Vector3::SIDE_WEST), BlockFactory::get(BlockIds::AIR), true, false);
			return true;
		}
		// portals
		$level->setBlock($position, BlockFactory::get(BlockIds::PORTAL, 1), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_UP), BlockFactory::get(BlockIds::PORTAL, 1), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_UP, 2), BlockFactory::get(BlockIds::PORTAL, 1), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::PORTAL, 1), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP), BlockFactory::get(BlockIds::PORTAL, 1), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP, 2), BlockFactory::get(BlockIds::PORTAL, 1), true, false);
		// obsidian
		$level->setBlock($position->getSide(Vector3::SIDE_WEST), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_WEST)->getSide(Vector3::SIDE_DOWN), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_WEST)->getSide(Vector3::SIDE_UP), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_WEST)->getSide(Vector3::SIDE_UP, 2), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_WEST)->getSide(Vector3::SIDE_UP, 3), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_DOWN), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_DOWN)->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_UP, 3), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_UP, 3)->getSide(Vector3::SIDE_EAST), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST, 2), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST, 2)->getSide(Vector3::SIDE_DOWN), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST, 2)->getSide(Vector3::SIDE_UP), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST, 2)->getSide(Vector3::SIDE_UP, 2), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST, 2)->getSide(Vector3::SIDE_UP, 3), BlockFactory::get(BlockIds::OBSIDIAN), true, false);
		// air
		$level->setBlock($position->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::AIR), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_UP)->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::AIR), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_UP, 2)->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::AIR), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::AIR), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP)->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::AIR), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP, 2)->getSide(Vector3::SIDE_NORTH), BlockFactory::get(BlockIds::AIR), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_SOUTH), BlockFactory::get(BlockIds::AIR), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_UP)->getSide(Vector3::SIDE_SOUTH), BlockFactory::get(BlockIds::AIR), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_UP, 2)->getSide(Vector3::SIDE_SOUTH), BlockFactory::get(BlockIds::AIR), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_SOUTH), BlockFactory::get(BlockIds::AIR), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP)->getSide(Vector3::SIDE_SOUTH), BlockFactory::get(BlockIds::AIR), true, false);
		$level->setBlock($position->getSide(Vector3::SIDE_EAST)->getSide(Vector3::SIDE_UP, 2)->getSide(Vector3::SIDE_SOUTH), BlockFactory::get(BlockIds::AIR), true, false);
		return true;
		// TODO: levelDB portal map
	}
}