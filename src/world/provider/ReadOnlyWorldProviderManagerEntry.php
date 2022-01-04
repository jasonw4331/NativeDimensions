<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\world\provider;

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

	public function fromPath(string $path, int $dimension = 0) : WorldProvider{ return ($this->fromPath)($path, $dimension); }
}
