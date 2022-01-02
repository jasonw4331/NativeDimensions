<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\world\provider;

use pocketmine\world\format\io\WorldData;
use pocketmine\world\format\io\WorldProvider;
use pocketmine\world\format\io\WorldProviderManagerEntry;

/**
 * @phpstan-type FromPath \Closure(string $path, int $dimension) : WorldProvider
 */
class ReadOnlyWorldProviderManagerEntry extends WorldProviderManagerEntry{

	/** @phpstan-var FromPath */
	private \Closure $fromPath;

	/** @phpstan-param FromPath $fromPath */
	public function __construct(\Closure $isValid, \Closure $fromPath){
		parent::__construct($isValid);
		$this->fromPath = $fromPath;
	}

	/**
	 * Tells if the path is a valid world.
	 * This must tell if the current format supports opening the files in the directory
	 */
	public function isValid(string $path) : bool{ return ($this->isValid)($path); }

	public function fromPath(string $path, ?WorldData $data = null) : WorldProvider{ return ($this->fromPath)($path, $data); }
}