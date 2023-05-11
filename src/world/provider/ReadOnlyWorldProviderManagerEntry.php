<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\world\provider;

use pocketmine\world\format\io\WorldProvider;

/**
 * @phpstan-type FromPath \Closure(string $path, int $dimension) : WorldProvider
 */
class ReadOnlyWorldProviderManagerEntry extends DimensionProviderManagerEntry{

	/** @phpstan-var FromPath */
	private \Closure $fromPath;

	/** @phpstan-param FromPath $fromPath */
	public function __construct(\Closure $isValid, \Closure $fromPath){
		parent::__construct($isValid);
		$this->fromPath = $fromPath;
	}

	public function fromPath(string $path, int $dimension = 0) : WorldProvider{ return ($this->fromPath)($path, $dimension); }
}
