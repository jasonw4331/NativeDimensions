<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\world\provider;

use pocketmine\world\format\io\WorldProvider;
use pocketmine\world\format\io\WorldProviderManagerEntry;

/**
 * @phpstan-type IsValid \Closure(string $path) : bool
 */
abstract class DimensionProviderManagerEntry extends WorldProviderManagerEntry{

	/**
	 * @inheritDoc
	 */
	abstract public function fromPath(string $path, int $dimension = 0) : WorldProvider;
}
