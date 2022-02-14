<?php
declare(strict_types = 1);
namespace jasonwynn10\NativeDimensions\block;

use jasonwynn10\NativeDimensions\Main;
use jasonwynn10\NativeDimensions\world\data\NetherPortalData;
use jasonwynn10\NativeDimensions\world\data\NetherPortalMap;
use jasonwynn10\NativeDimensions\world\DimensionalWorld;
use jasonwynn10\NativeDimensions\world\provider\DimensionLevelDBProvider;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier as BID;
use pocketmine\block\BlockLegacyIds as Ids;
use pocketmine\block\NetherPortal;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;

class Portal extends NetherPortal {

	public function __construct(){
		parent::__construct(new BID(Ids::PORTAL, 0), "Nether Portal", BlockBreakInfo::indestructible(0.0));
	}

	public function onBreak(Item $item, Player $player = null): bool{
		$position = $this->position;
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

		/** @noinspection PhpParamsInspection */
		NetherPortalMap::getInstance()->removePortal($position->world, $position->x, $position->y, $position->z);

		return parent::onBreak($item, $player);
	}

	public function hasEntityCollision() : bool{
		return true;
	}

	public function onEntityInside(Entity $entity): bool{
		/** @var DimensionalWorld $world */
		$world = $entity->getPosition()->getWorld();
		if($world->getEnd() === $world)
			return true;

		if(in_array($entity->getId(), Main::getTeleporting()))
			return true;

		Main::addTeleportingId($entity->getId());
		$position = $this->getPosition();
		$y = $position->y;
		if($world->getOverworld() === $world) {
			$x = $position->x / 8;
			$z = $position->z / 8;
			$portal = $this->findPortal($x, $z, $world->getNether());
			if($portal === null) {
				$world->getNether()->orderChunkPopulation($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE, null)->onCompletion(
					function(Chunk $chunk) use($x, $y, $z, $world, $entity) {
						$position = new Position($x + 0.5, $y, $z + 0.5, $world->getNether());
						// TODO: ensure space available for placement
						Main::makeNetherPortal($position, mt_rand(Axis::Z, Axis::X));
						Main::getInstance()->getLogger()->debug("Teleporting to the Nether");
						if($entity instanceof Player) {
							if(!$entity->isCreative()) {
								Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use($entity, $position) : void {
									$entity->teleport($position);
									$entity->getNetworkSession()->sendDataPacket(ChangeDimensionPacket::create(DimensionIds::NETHER, $entity->getPosition(), false));
								}), 20 * 6);
								return;
							}
							$entity->getNetworkSession()->sendDataPacket(ChangeDimensionPacket::create(DimensionIds::NETHER, $entity->getPosition(), false));
						}
						$entity->teleport($position);
					},
					function() use ($entity) {
						Main::getInstance()->getLogger()->debug("Failed to generate Nether chunks");
						Main::removeTeleportingId($entity->getId());
					}
				);
			}else{
				Main::getInstance()->getLogger()->debug("Teleporting to the Nether");
				$position = Position::fromObject($portal->getVector3()->floor()->add(0.5, 0, 0.5), $world->getNether());
				if($entity instanceof Player) {
					if(!$entity->isCreative()) {
						Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use($entity, $position) : void {
							$entity->teleport($position);
							$entity->getNetworkSession()->sendDataPacket(ChangeDimensionPacket::create(DimensionIds::NETHER, $entity->getPosition(), false));
						}), 20 * 6);
						return true;
					}
					$entity->getNetworkSession()->sendDataPacket(ChangeDimensionPacket::create(DimensionIds::NETHER, $entity->getPosition(), false));
				}
				$entity->teleport($position);
			}
		}elseif($world->getNether() === $world) {
			$x = $position->x * 8;
			$z = $position->z * 8;
			$portal = $this->findPortal($x, $z, $world->getOverworld());
			if($portal === null) {
				$world->getNether()->orderChunkPopulation($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE, null)->onCompletion(
					function(Chunk $chunk) use($x, $y, $z, $world, $entity) {
						$position = new Position($x + 0.5, $y, $z + 0.5, $world->getOverworld());
						// TODO: ensure space available for placement
						Main::makeNetherPortal($position, mt_rand(Axis::Z, Axis::X));
						Main::getInstance()->getLogger()->debug("Teleporting to the Overworld");
						if($entity instanceof Player) {
							if(!$entity->isCreative()) {
								Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use($entity, $position) : void {
									$entity->teleport($position);
									$entity->getNetworkSession()->sendDataPacket(ChangeDimensionPacket::create(DimensionIds::OVERWORLD, $entity->getPosition(), false));
								}), 20 * 6);
								return;
							}
							$entity->getNetworkSession()->sendDataPacket(ChangeDimensionPacket::create(DimensionIds::OVERWORLD, $entity->getPosition(), false));
						}
						$entity->teleport($position);
					},
					function() use ($entity) {
						Main::getInstance()->getLogger()->debug("Failed to generate Overworld chunks");
						Main::removeTeleportingId($entity->getId());
					}
				);
			}else{
				Main::getInstance()->getLogger()->debug("Teleporting to the Overworld");
				$position = Position::fromObject($portal->getVector3()->floor()->add(0.5, 0, 0.5), $world->getOverworld());
				if($entity instanceof Player) {
					if(!$entity->isCreative()) {
						Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use($entity, $position) : void {
							$entity->teleport($position);
							$entity->getNetworkSession()->sendDataPacket(ChangeDimensionPacket::create(DimensionIds::OVERWORLD, $entity->getPosition(), false));
						}), 20 * 6);
						return true;
					}
					$entity->getNetworkSession()->sendDataPacket(ChangeDimensionPacket::create(DimensionIds::OVERWORLD, $entity->getPosition(), false));
				}
				$entity->teleport($position);
			}
		}
		return true;
	}

	private function findPortal(int|float $x, int|float $z, DimensionalWorld $world) : ?NetherPortalData {
		$provider = $world->getProvider();
		if(!$provider instanceof DimensionLevelDBProvider) // TODO: better provider support
			return null;

		$v2 = new Vector2($x, $z);
		$maxDistance = $world->getDimensionId() === DimensionIds::NETHER ? 33 : 257;
		$portals = NetherPortalMap::getInstance()->getPortals($world);
		foreach($portals as $portal) {
			Main::getInstance()->getLogger()->debug('Testing '.$portal->getVector3()->__toString().' against '.$v2->__toString());
			$vec = $portal->getVector3()->floor();
			if(
				$portal->getDimensionId() === $world->getDimensionId() and
				$v2->floor()->distance(new Vector2($vec->x, $vec->z)) < $maxDistance
			) {
				Main::getInstance()->getLogger()->debug('Found Nearby Portal in '.$world->getDisplayName());
				return $portal;
			}
		}
		Main::getInstance()->getLogger()->debug('No Nearby Portals found in '.$world->getDisplayName());
		return null;
	}
}