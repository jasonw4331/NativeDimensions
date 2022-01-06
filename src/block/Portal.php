<?php
declare(strict_types = 1);
namespace jasonwynn10\NativeDimensions\block;

use jasonwynn10\NativeDimensions\Main;
use jasonwynn10\NativeDimensions\world\DimensionalWorld;
use pocketmine\block\Air;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIdentifier as BID;
use pocketmine\block\BlockLegacyIds as Ids;
use pocketmine\block\NetherPortal;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\ChunkLoader;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\World;

class Portal extends NetherPortal {

	public function __construct(){
		parent::__construct(new BID(Ids::PORTAL, 0), "Nether Portal", BlockBreakInfo::indestructible(0.0));
	}

	public function onBreak(Item $item, Player $player = null): bool{
		$position = $this->getPosition();
		if($this->getSide(Facing::WEST) instanceof NetherPortal or
			$this->getSide(Facing::EAST) instanceof NetherPortal
		){//x direction
			for($x = $position->x; $this->getPosition()->getWorld()->getBlockAt($x, $position->y, $position->z) instanceof NetherPortal; $x++){
				for($y = $position->y; $this->getPosition()->getWorld()->getBlockAt($x, $y, $position->z) instanceof NetherPortal; $y++){
					$this->getPosition()->getWorld()->setBlock(new Vector3($x, $y, $position->z), VanillaBlocks::AIR());
				}
				for($y = $position->y - 1; $this->getPosition()->getWorld()->getBlockAt($x, $y, $position->z) instanceof NetherPortal; $y--){
					$this->getPosition()->getWorld()->setBlock(new Vector3($x, $y, $position->z), VanillaBlocks::AIR());
				}
			}
			for($x = $position->x - 1; $this->getPosition()->getWorld()->getBlockAt($x, $position->y, $position->z) instanceof NetherPortal; $x--){
				for($y = $position->y; $this->getPosition()->getWorld()->getBlockAt($x, $y, $position->z) instanceof NetherPortal; $y++){
					$this->getPosition()->getWorld()->setBlock(new Vector3($x, $y, $position->z), VanillaBlocks::AIR());
				}
				for($y = $position->y - 1; $this->getPosition()->getWorld()->getBlockAt($x, $y, $position->z) instanceof NetherPortal; $y--){
					$this->getPosition()->getWorld()->setBlock(new Vector3($x, $y, $position->z), VanillaBlocks::AIR());
				}
			}
		}else{//z direction
			for($z = $position->z; $this->getPosition()->getWorld()->getBlockAt($position->x, $position->y, $z) instanceof NetherPortal; $z++){
				for($y = $position->y; $this->getPosition()->getWorld()->getBlockAt($position->x, $y, $z) instanceof NetherPortal; $y++){
					$this->getPosition()->getWorld()->setBlock(new Vector3($position->x, $y, $z), VanillaBlocks::AIR());
				}
				for($y = $position->y - 1; $this->getPosition()->getWorld()->getBlockAt($position->x, $y, $z) instanceof NetherPortal; $y--){
					$this->getPosition()->getWorld()->setBlock(new Vector3($position->x, $y, $z), VanillaBlocks::AIR());
				}
			}
			for($z = $position->z - 1; $this->getPosition()->getWorld()->getBlockAt($position->x, $position->y, $z) instanceof NetherPortal; $z--){
				for($y = $position->y; $this->getPosition()->getWorld()->getBlockAt($position->x, $y, $z) instanceof NetherPortal; $y++){
					$this->getPosition()->getWorld()->setBlock(new Vector3($position->x, $y, $z), VanillaBlocks::AIR());
				}
				for($y = $position->y - 1; $this->getPosition()->getWorld()->getBlockAt($position->x, $y, $z) instanceof NetherPortal; $y--){
					$this->getPosition()->getWorld()->setBlock(new Vector3($position->x, $y, $z), VanillaBlocks::AIR());
				}
			}
		}

		return parent::onBreak($item, $player);
	}

	public function onPostPlace() : void{
		// TODO: levelDB portal mapping
	}

	public function onEntityInside(Entity $entity): bool{
		if(!in_array($entity->getId(), Main::getTeleporting())){
			Main::addTeleportingId($entity->getId());
			$entity->getWorld()->getLogger()->debug("Portal Teleport initialised");
			/** @var DimensionalWorld $world */
			$world = $entity->getPosition()->getWorld();
			$position = $this->getPair();
			if($position === null) {
				$position = $this->getPosition();
				if($world->getOverworld() === $world) {
					$world = $world->getNether();
					$x = $position->x * 8;
					$z = $position->z * 8;
				}else {
					$world = $world->getOverworld();
					$x = $position->x / 8;
					$z = $position->z / 8;
				}
				$y = $position->y;

				if($world->isChunkGenerated($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)) {
					$world->getLogger()->debug("Generating portal pair");
					for($chunkX = ($x >> Chunk::COORD_BIT_SIZE) - 3; $chunkX <= ($x >> Chunk::COORD_BIT_SIZE) + 4; ++$chunkX) {
						for($chunkZ = ($z >> Chunk::COORD_BIT_SIZE) - 3; $chunkZ <= ($z >> Chunk::COORD_BIT_SIZE) + 4; ++$chunkZ) {
							$validBlock = $this->getGenerationSpace($chunkX, $chunkZ, $world);
							if($validBlock instanceof Position) {
								$this->makePortal($validBlock);
								$position = $validBlock;
							}else {
								$this->makePortal($position = new Position($x, $y, $z, $world));
							}
							$world->getLogger()->debug("Portal Pair Generated");
						}
					}

					if($entity instanceof Player and !$entity->isCreative()) {
						$world->getLogger()->debug("Registering Teleport Event");
						Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use($entity, $position) : void {
							if(!$entity->getPosition()->getWorld()->getBlock($entity->getPosition()->floor()) instanceof NetherPortal) {
								return;
							}
							$entity->teleport($position);
						}), 20 * 6);
					}else{
						$world->getLogger()->debug("Teleporting entity");
						$entity->teleport($position);
					}
					return true;
				}
				$world->getLogger()->debug("Generating terrain");
				$portalMade = false;
				$loader = new class() implements ChunkLoader{};
				$this->generatePortalSpace($x, $z, $world, $loader, function(Chunk $chunk) use ($x, $y, $z, $world, $entity, $loader, &$portalMade) {
					$world->getLogger()->debug("Terrain successfully Generated");

					if($portalMade)
						return;

					$world->getLogger()->debug("Generating portal pair");
					for($k = 0; $k < $world->getMaxY(); ++$k) {
						for($i = 0; $i < 16; ++$i) {
							for($j = 0; $j < 16; ++$j) {
								$id = $chunk->getFullBlock($i, $k, $j);
								$block = BlockFactory::getInstance()->getInstance()->fromFullBlock($id);
								if($block instanceof Air) {
									$position = new Position(($x >> Chunk::COORD_BIT_SIZE) + $i, $k, ($z >> Chunk::COORD_BIT_SIZE) + $j, $world);
									Main::getInstance()->getScheduler()->scheduleTask(new ClosureTask(\Closure::fromCallable(function() use($entity, $position) : void {
										if($entity instanceof Player and !$entity->isCreative()) {
											$entity->getWorld()->getLogger()->debug("Registering Teleport Event");
											Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use($entity, $position) : void {
												if(!$entity->getPosition()->getWorld()->getBlock($entity->getPosition()->floor()) instanceof NetherPortal) {
													return;
												}
												$entity->teleport($position);
											}), 20 * 6);
										}else{
											$entity->getWorld()->getLogger()->debug("Teleporting entity");
											$entity->teleport($position);
										}
									})));
									$this->makePortal($position);
									$world->getLogger()->debug("Portal Pair Generated");
									$portalMade = true;
									break 3;
								}
							}
						}
					}
				}, fn() => Main::removeTeleportingId($entity->getId()));
			}
		}
		return true;
	}

	public function getGenerationSpace(int $chunkX, int $chunkZ, World $world) : ?Position {
		$chunk = $world->getChunk($chunkX, $chunkZ);
		for($k = 0; $k < $world->getMaxY(); ++$k) {
			for($i = 0; $i < 16; ++$i) {
				for($j = 0; $j < 16; ++$j) {
					$id = $chunk->getFullBlock($i, $k, $j);
					$block = BlockFactory::getInstance()->getInstance()->fromFullBlock($id);
					if($block instanceof Air) {
						return new Position(($chunkX << Chunk::COORD_BIT_SIZE) + $i, $k, ($chunkZ << Chunk::COORD_BIT_SIZE) + $j, $world);
					}
				}
			}
		}
		return null;
	}

	public function generatePortalSpace(float $x, float $z, World $world, ChunkLoader $loader, callable $onSuccess, callable $onFailure) : void {
		for($chunkX = ($x >> Chunk::COORD_BIT_SIZE) - 3; $chunkX <= ($x >> Chunk::COORD_BIT_SIZE) + 4; ++$chunkX) {
			for($chunkZ = ($z >> Chunk::COORD_BIT_SIZE) - 3; $chunkZ <= ($z >> Chunk::COORD_BIT_SIZE) + 4; ++$chunkZ) {
				$world->orderChunkPopulation($chunkX, $chunkZ, $loader)->onCompletion(\Closure::fromCallable($onSuccess), \Closure::fromCallable($onFailure));
			}
		}
	}

	public function getPair() : ?Position {
		/** @var DimensionalWorld $world */
		$world = $this->getPosition()->getWorld();
		if($world->getOverworld() === $world) {
			$world = $world->getNether();
			$x = (int)ceil($this->getPosition()->x * 8);
			$z = (int)ceil($this->getPosition()->z * 8);
		}else {
			$world = $world->getOverworld();
			$x = (int)ceil($this->getPosition()->x / 8);
			$z = (int)ceil($this->getPosition()->z / 8);
		}

		for($chunkX = ($x >> Chunk::COORD_BIT_SIZE) - 3; $chunkX <= ($x >> Chunk::COORD_BIT_SIZE) + 4; ++$chunkX) {
			for($chunkZ = ($z >> Chunk::COORD_BIT_SIZE) - 3; $chunkZ <= ($z >> Chunk::COORD_BIT_SIZE) + 4; ++$chunkZ) {
				$chunk = $world->loadChunk($chunkX, $chunkZ);
				if($chunk === null)
					return null;
				for($k = $world->getMinY(); $k < $world->getMaxY(); ++$k) {
					for($i = 0; $i < 16; ++$i) {
						for($j = 0; $j < 16; ++$j) {
							$id = $chunk->getFullBlock($i, $k, $j);
							$block = BlockFactory::getInstance()->getInstance()->fromFullBlock($id);
							if($block instanceof Portal and BlockFactory::getInstance()->getInstance()->fromFullBlock($chunk->getFullBlock($i, $k-1, $j)) instanceof Portal) {
								return new Position(($chunkX << Chunk::COORD_BIT_SIZE) + $i, $k, ($chunkZ << Chunk::COORD_BIT_SIZE) + $j, $world);
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
		/** @var DimensionalWorld $world */
		$world = $position->getWorld();
		if($world->getEnd() === $world)
			return false; // no portals in the end
		$xDirection = (bool)mt_rand(0,1);
		if($xDirection) {
			// portal blocks
			$world->setBlock($position, (clone $this)->setAxis(Axis::Z), true);
			$world->setBlock($position->getSide(Facing::UP), (clone $this)->setAxis(Axis::Z), true);
			$world->setBlock($position->getSide(Facing::UP, 2), (clone $this)->setAxis(Axis::Z), true);
			$world->setBlock($position->getSide(Facing::NORTH), (clone $this)->setAxis(Axis::Z), true);
			$world->setBlock($position->getSide(Facing::NORTH)->getSide(Facing::UP), (clone $this)->setAxis(Axis::Z), true);
			$world->setBlock($position->getSide(Facing::NORTH)->getSide(Facing::UP, 2), (clone $this)->setAxis(Axis::Z), true);
			// obsidian
			$world->setBlock($position->getSide(Facing::SOUTH), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::DOWN), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::UP), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::UP, 2), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::UP, 3), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::DOWN), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::DOWN)->getSide(Facing::NORTH), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::UP, 3), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::UP, 3)->getSide(Facing::NORTH), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::NORTH, 2), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::DOWN), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::UP), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::UP, 2), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::UP, 3), new Obsidian(), true);
			// air
			$world->setBlock($position->getSide(Facing::EAST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::UP)->getSide(Facing::EAST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::UP, 2)->getSide(Facing::EAST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::EAST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP)->getSide(Facing::EAST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP, 2)->getSide(Facing::EAST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::WEST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::UP)->getSide(Facing::WEST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::UP, 2)->getSide(Facing::WEST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::WEST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP)->getSide(Facing::WEST), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP, 2)->getSide(Facing::WEST), VanillaBlocks::AIR(), true);
		}else{
			// portal blocks
			$world->setBlock($position, (clone $this)->setAxis(Axis::X), true);
			$world->setBlock($position->getSide(Facing::UP), (clone $this)->setAxis(Axis::X), true);
			$world->setBlock($position->getSide(Facing::UP, 2), (clone $this)->setAxis(Axis::X), true);
			$world->setBlock($position->getSide(Facing::EAST), (clone $this)->setAxis(Axis::X), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP), (clone $this)->setAxis(Axis::X), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP, 2), (clone $this)->setAxis(Axis::X), true);
			// obsidian
			$world->setBlock($position->getSide(Facing::WEST), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::DOWN), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::UP), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::UP, 2), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::UP, 3), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::DOWN), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::DOWN)->getSide(Facing::EAST), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::UP, 3), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::UP, 3)->getSide(Facing::EAST), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::EAST, 2), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::DOWN), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::UP), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::UP, 2), new Obsidian(), true);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::UP, 3), new Obsidian(), true);
			// air
			$world->setBlock($position->getSide(Facing::NORTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::UP)->getSide(Facing::NORTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::UP, 2)->getSide(Facing::NORTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::NORTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP)->getSide(Facing::NORTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP, 2)->getSide(Facing::NORTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::SOUTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::UP)->getSide(Facing::SOUTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::UP, 2)->getSide(Facing::SOUTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::SOUTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP)->getSide(Facing::SOUTH), VanillaBlocks::AIR(), true);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP, 2)->getSide(Facing::SOUTH), VanillaBlocks::AIR(), true);
		}
		// TODO: levelDB portal map
		return true;
	}
}