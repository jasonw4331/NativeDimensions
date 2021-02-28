<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\provider;

use pocketmine\level\format\io\BaseLevelProvider;
use pocketmine\level\format\io\region\Anvil;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Server;

class SubAnvilProvider extends Anvil {

	public static function generate(string $path, string $name, int $seed, string $generator, array $options = [], int $dimension = 0) : void {
		if(!file_exists($path)){
			mkdir($path, 0777, true);
		}

		if(!file_exists($path . "/dim$dimension")){
			mkdir($path . "/dim$dimension", 0777);
		}
		if(!file_exists($path . "/dim$dimension/region")){
			mkdir($path . "/dim$dimension/region", 0777);
		}
	}

	public function getLevelData() : CompoundTag{
		/** @var BaseLevelProvider $parent */
		$parent = Server::getInstance()->getLevelByName(basename($this->path))->getProvider();
		return $parent->getLevelData();
	}

	public function saveLevelData() : void {
		Server::getInstance()->getLevelByName(basename($this->path))->getProvider()->saveLevelData();
	}
}