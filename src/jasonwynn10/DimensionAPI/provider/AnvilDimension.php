<?php
declare(strict_types=1);
namespace jasonwynn10\DimensionAPI\provider;

use czechpmdevs\multiworld\generator\ender\EnderGenerator;
use pocketmine\level\format\io\region\Anvil;
use pocketmine\level\generator\hell\Nether;
use pocketmine\level\generator\normal\Normal;

class AnvilDimension extends Anvil {

	/** @var int */
	protected $dimension;

	public function __construct(string $path, int $dimension = -1){
		parent::__construct($path);
		$this->dimension = $dimension;
	}

	/**
	 * Returns the path to a specific region file based on its X/Z coordinates and dimension
	 *
	 * @param int $regionX
	 * @param int $regionZ
	 *
	 * @return string
	 */
	protected function pathToRegion(int $regionX, int $regionZ) : string{
		return $this->path . "/dim".$this->dimension."/region/r.$regionX.$regionZ." . static::REGION_FILE_EXTENSION;
	}

	public static function isValid(string $path, int $dimension = -1) : bool{
		$isValid = (file_exists($path . "/level.dat") and is_dir($path . "/dim".$dimension."/region/"));

		if($isValid){
			$files = array_filter(scandir($path . "/dim".$dimension."/region/", SCANDIR_SORT_NONE), function($file){
				return substr($file, strrpos($file, ".") + 1, 2) === "mc"; //region file
			});

			foreach($files as $f){
				if(substr($f, strrpos($f, ".") + 1) !== static::REGION_FILE_EXTENSION){
					$isValid = false;
					break;
				}
			}
		}

		return $isValid;
	}

	public static function generate(string $path, string $name, int $seed, string $generator, array $options = [], int $dimension = -1){
		if(!file_exists($path)){
			mkdir($path, 0777, true);
		}

		if(!file_exists($path . "/dim".$dimension."/region")){
			mkdir($path . "/dim".$dimension."/region", 0777);
		}
	}

	public static function getProviderName() : string{
		return "anvil_dimension";
	}

	/**
	 * @return string
	 */
	public function getGenerator() : string {
		if($this->dimension === -1)
			return (new Nether())->getName();
		elseif($this->dimension === 1 and class_exists("czechpmdevs\\multiworld\\generator\\ender\\EnderGenerator"))
			return (new EnderGenerator())->getName();
		return (new Normal())->getName();
	}
}