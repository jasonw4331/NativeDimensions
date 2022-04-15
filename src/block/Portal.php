<?php
declare(strict_types = 1);
namespace jasonwynn10\NativeDimensions\block;

use jasonwynn10\NativeDimensions\Main;
use jasonwynn10\NativeDimensions\world\DimensionalWorld;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier as BID;
use pocketmine\block\BlockLegacyIds as Ids;
use pocketmine\block\NetherPortal;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\math\Facing;
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

	public function hasEntityCollision() : bool{
		return true;
	}

	public function onEntityInside(Entity $entity): bool{
		if(in_array($entity->getId(), Main::getTeleporting()))
			return true;

		/** @var DimensionalWorld $world */
		$world = $entity->getPosition()->getWorld();

		if(Main::isPortalDisabled($world))
			return true;

		if($world->getEnd() === $world)
			return true;

		Main::addTeleportingId($entity->getId());
		$position = $this->getPosition();
		$y = $position->y;
		if($world->getOverworld() === $world){
			// TODO: levelDB portal mapping
			$x = $position->x / 8;
			$z = $position->z / 8;
			$world->getNether()->orderChunkPopulation($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE, null)->onCompletion(
				function(Chunk $chunk) use($x, $y, $z, $world, $entity) {
					$position = new Position($x + 0.5, $y, $z + 0.5, $world->getNether());
					if(!$world->getNether()->getBlock($position) instanceof NetherPortal)
						Main::makeNetherPortal($position);
					$world->getLogger()->debug("Teleporting to the Nether");
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
		}elseif($world->getNether() === $world) {
			// TODO: levelDB portal mapping
			$x = $position->x * 8;
			$z = $position->z * 8;
			$world->getOverworld()->orderChunkPopulation($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE, null)->onCompletion(
				function(Chunk $chunk) use($x, $y, $z, $world, $entity) {
					$position = new Position($x + 0.5, $y, $z + 0.5, $world->getOverworld());
					if(!$world->getOverworld()->getBlock($position) instanceof NetherPortal)
						Main::makeNetherPortal($position);
					$world->getLogger()->debug("Teleporting to the Overworld");
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
		}
		return true;
	}
}