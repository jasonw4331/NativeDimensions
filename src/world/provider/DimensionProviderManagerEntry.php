<?php

namespace jasonwynn10\NativeDimensions\world\provider;

use pocketmine\world\format\io\exception\CorruptedWorldException;
use pocketmine\world\format\io\exception\UnsupportedWorldFormatException;
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