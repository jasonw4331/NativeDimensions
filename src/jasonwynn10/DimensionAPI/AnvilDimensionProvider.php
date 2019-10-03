<?php
declare(strict_types=1);

namespace jasonwynn10\DimensionAPI;


use pocketmine\level\format\io\region\Anvil;

class AnvilDimensionProvider extends Anvil {

	/** @var int */
	protected $dimension;

	public function __construct(string $path, int $dimension){
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
		return $this->path . "dim".$this->dimension."/region/r.$regionX.$regionZ." . static::REGION_FILE_EXTENSION;
	}


	public static function getProviderName() : string{
		return "anvildimensions";
	}
}