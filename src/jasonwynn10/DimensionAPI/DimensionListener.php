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
use pocketmine\level\generator\hell\Nether;
use pocketmine\level\generator\normal\Normal;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\DimensionIds;

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
				$this->plugin->generateLevelDimension($event->getLevel()->getFolderName(), $event->getLevel()->getSeed(), Nether::class, [], -1);
			if($this->plugin->getEndGenerator() !== Normal::class) {
				if($this->plugin->dimensionExists($event->getLevel(), 1))
					$this->plugin->generateLevelDimension($event->getLevel()->getFolderName(), $event->getLevel()->getSeed(), $this->plugin->getEndGenerator(), [], 1);
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
		// TODO: track teleports for portal interactions
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