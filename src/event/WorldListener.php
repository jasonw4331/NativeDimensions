<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\event;

use jasonw4331\NativeDimensions\Main;
use jasonw4331\NativeDimensions\player\PlayerManager;
use jasonw4331\NativeDimensions\world\DimensionalWorld;
use pocketmine\block\Bed;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBedEnterEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\player\Player;
use pocketmine\world\Explosion;

final class WorldListener implements Listener{

	public function __construct(Main $plugin){
		$plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
	}

	/**
	 * @param EntityTeleportEvent $event
	 * @priority MONITOR
	 */
	public function onEntityTeleport(EntityTeleportEvent $event) : void{
		$player = $event->getEntity();
		if($player instanceof Player){
			/** @var DimensionalWorld $from_world */
			$from_world = $event->getFrom()->getWorld();
			$to = $event->getTo();
			/** @var DimensionalWorld $to_world */
			$to_world = $to->getWorld();
			if($from_world->getDimensionId() !== $to_world->getDimensionId()){
				// Player can be null if a plugin teleports the player before PlayerLoginEvent @ MONITOR
				PlayerManager::getNullable($player)?->onBeginDimensionChange($to_world->getDimensionId(), $to->asVector3(), !$player->isAlive());
			}
		}
	}

	public function onSleep(PlayerBedEnterEvent $event) : void{
		$bed = $event->getBed();
		if(!$bed instanceof Bed)
			return;
		$pos = $bed->isHeadPart() ? $bed->getPosition() : $bed->getOtherHalf()->getPosition();
		/** @var DimensionalWorld $world */
		$world = $pos->getWorld();
		if($world->getOverworld() !== $world){
			$event->cancel();
			$explosion = new Explosion($pos, 5, $event->getBed());
			$explosion->explodeA();
			$explosion->explodeB();
		}
	}
}