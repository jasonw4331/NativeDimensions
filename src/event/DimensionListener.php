<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\event;

use jasonwynn10\NativeDimensions\Main;
use jasonwynn10\NativeDimensions\world\DimensionalWorld;
use pocketmine\block\NetherPortal;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBedEnterEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\SpawnSettings;
use pocketmine\player\Player;
use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\ClosureTask;

class DimensionListener implements Listener {
	/** @var Main */
	protected $plugin;

	public function __construct(Main $plugin) {
		$plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
		$this->plugin = $plugin;
	}

	public function onDataPacket(DataPacketSendEvent $event) {
		foreach($event->getPackets() as $pk) {
			if($pk instanceof StartGamePacket) {
				foreach($event->getTargets() as $session) {
					/** @var DimensionalWorld $world */
					$world = $session->getPlayer()->getWorld();
					$settings = $pk->levelSettings->spawnSettings;
					if($world->getOverworld() === $world)
						$dimension = DimensionIds::OVERWORLD;
					elseif($world->getNether() === $world)
						$dimension = DimensionIds::NETHER;
					elseif($world->getEnd() === $world)
						$dimension = DimensionIds::THE_END;
					else
						return; // players can still go to non-dimension worlds
					$pk->levelSettings->spawnSettings = new SpawnSettings($settings->getBiomeType(), $settings->getBiomeName(), $dimension);
				}
			}
		}
	}

	public function onRespawn(PlayerRespawnEvent $event) : void {
		$player = $event->getPlayer();
		if(!$player->isAlive()) {
			/** @var DimensionalWorld $world */
			$world = $event->getRespawnPosition()->getWorld();
			$overworld = $world->getOverworld();
			$respawn = $event->getRespawnPosition();
			$respawn->world = $overworld;
			$event->setRespawnPosition($respawn);
		}
	}

	public function onTeleport(EntityTeleportEvent $event) : void {
		$entity = $event->getEntity();

		$this->plugin->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(function() use($entity) : void {
			if($entity->getPosition()->getWorld()->getBlock($entity->getPosition()->floor()) instanceof NetherPortal) {
				$this->plugin->getLogger()->debug("Player has not left portal after teleport");
				return;
			}
			Main::removeTeleportingId($entity->getId());
			$this->plugin->getLogger()->debug("Player can use a portal again");
			throw new CancelTaskException();
		}), 20 * 20, 20 * 10);

		if(!$entity instanceof Player)
			return;

		/** @var DimensionalWorld $world */
		$world = $event->getTo()->getWorld();
		if($world->getFolderName() === $event->getFrom()->getWorld()->getFolderName())
			return;
		$pk = new ChangeDimensionPacket();
		if($world->getOverworld() === $world)
			$pk->dimension = DimensionIds::OVERWORLD;
		elseif($world->getNether() === $world)
			$pk->dimension = DimensionIds::NETHER;
		elseif($world->getEnd() === $world)
			$pk->dimension = DimensionIds::THE_END;
		else
			return; // players can still go to non-dimension worlds
		$pk->position = $event->getTo()->asVector3();
		$pk->respawn = !$entity->isAlive();
		$entity->getNetworkSession()->sendDataPacket($pk);

		if(!$entity->isAlive())
			return;

		$portalPosition = $event->getTo();

		if($portalPosition->getWorld()->getBlock($portalPosition) instanceof NetherPortal)
			return;

		if($world->getEnd() === $world)
			return;

		$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(fn() => Main::makeNetherPortal($portalPosition)), 3);
		// TODO: portal cooldown
	}

	/**
	 * @param PlayerBedEnterEvent $event
	 */
	public function onSleep(PlayerBedEnterEvent $event) : void {
		$pos = $event->getBed()->getPosition();
		/** @var DimensionalWorld $world */
		$world = $pos->getWorld();
		if($world->getOverworld() !== $world) {
			$event->cancel();
			// TODO: blow up bed
		}
	}
}