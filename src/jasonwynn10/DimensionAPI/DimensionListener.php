<?php
declare(strict_types=1);
namespace jasonwynn10\DimensionAPI;

use jasonwynn10\DimensionAPI\provider\AnvilDimension;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\level\LevelLoadEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBedEnterEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\level\format\io\region\Anvil;
use pocketmine\level\generator\GeneratorManager;
use pocketmine\level\generator\normal\Normal;
use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;

class DimensionListener implements Listener {
	/** @var Main */
	protected $plugin;

	public function __construct(Main $plugin) {
		$plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
		$this->plugin = $plugin;
	}

	/**
	 * @param LevelLoadEvent $event
	 *
	 * @throws \ReflectionException
	 */
	public function onLevelLoad(LevelLoadEvent $event) : void {
		$provider = $event->getLevel()->getProvider();
		if($provider instanceof Anvil and !$provider instanceof AnvilDimension) {
			if($this->plugin->dimensionExists($event->getLevel(), -1))
				$this->plugin->generateLevelDimension($event->getLevel()->getFolderName(), -1, $event->getLevel()->getSeed());
			if(GeneratorManager::getGenerator("ender") !== Normal::class) {
				if($this->plugin->dimensionExists($event->getLevel(), 1))
					$this->plugin->generateLevelDimension($event->getLevel()->getFolderName(), 1, $event->getLevel()->getSeed());
			}
		}
	}

	public function onDataPacket(DataPacketSendEvent $event) {
		$player = $event->getPlayer();
		$packet = $event->getPacket();
		if($packet instanceof StartGamePacket) {
			if(strpos($player->getLevel()->getFolderName(), " dim-1") !== false)
				$packet->dimension = DimensionIds::NETHER;
			elseif(strpos($player->getLevel()->getFolderName(), " dim1") !== false)
				$packet->dimension = DimensionIds::THE_END;
			else
				$packet->dimension = DimensionIds::OVERWORLD;
		}
	}

	public function onRespawn(PlayerRespawnEvent $event) : void {
		$player = $event->getPlayer();
		if(!$player->isAlive()) {
			$level = $event->getRespawnPosition()->getLevel();
			$overworldLevel = Main::getDimensionBaseLevel($level);
			if($overworldLevel !== null) {
				$event->setRespawnPosition($event->getRespawnPosition()->setLevel($overworldLevel));
			}
		}
	}

	public function onTeleport(EntityTeleportEvent $event) : void {
		$player = $event->getEntity();
		if(!$player instanceof Player)
			return;
		$level = $event->getTo()->getLevel();
		if($level === null or $level->getFolderName() === $event->getFrom()->getLevel()->getFolderName())
			return;
		$pk = new ChangeDimensionPacket();
		if(strpos($level->getFolderName(), " dim-1") !== false) {
			$pk->dimension = DimensionIds::NETHER;
			// TODO: save nether portal connections for 300 ticks after use
		}elseif(strpos($level->getFolderName(), " dim1") !== false) {
			$pk->dimension = DimensionIds::THE_END;
		}else{
			$pk->dimension = DimensionIds::OVERWORLD;
		}
		$pk->position = $event->getTo();
		$pk->respawn = false;
		//$player->sendDataPacket($pk);

		//$player->sendPlayStatus(PlayStatusPacket::PLAYER_SPAWN);

		$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function(int $currentTick) use($player) : void {
			Main::removeTeleportingId($player->getId());
		}), 20 * 10);
		// TODO: portal cooldown
	}

	/**
	 * @param PlayerBedEnterEvent $event
	 */
	public function onSleep(PlayerBedEnterEvent $event) : void {
		$pos = $event->getBed()->asPosition();
		if($pos->isValid()) {
			$level = $pos->getLevel();
			$overworld = Main::getDimensionBaseLevel($level);
			if($overworld !== null)
				$event->setCancelled();
			// TODO: blow up bed
		}
	}
}