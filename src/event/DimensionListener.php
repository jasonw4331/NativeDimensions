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
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\network\mcpe\protocol\types\SpawnSettings;
use pocketmine\player\Player;
use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\Position;

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

	public function onReceivePacket(DataPacketReceiveEvent $event) {
		$pk = $event->getPacket();
		if($pk instanceof PlayerActionPacket and $pk->action === PlayerAction::DIMENSION_CHANGE_ACK) {
			$player = $event->getOrigin()->getPlayer();

			if(!in_array($player->getId(), Main::getTeleporting()))
				return;

			$this->plugin->getLogger()->debug("Valid Dimension ACK received");

			$this->plugin->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(function() use($player) : void {
				if($player->getWorld()->getBlock($player->getPosition()) instanceof NetherPortal) {
					$this->plugin->getLogger()->debug("Player has not left portal after teleport");
					return;
				}
				Main::removeTeleportingId($player->getId());
				throw new CancelTaskException();
			}), 20 * 10, 20 * 5);
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
			Main::removeTeleportingId($player->getId());
		}
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