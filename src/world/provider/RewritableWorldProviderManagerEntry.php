<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\world\provider;

use Closure;
use LevelDB;
use Logger;
use pocketmine\world\format\io\WritableWorldProvider;
use pocketmine\world\WorldCreationOptions;

/**
 * @phpstan-type FromPath \Closure(string $path, Logger $logger, int $dimension, \LevelDB $db) : DimensionLevelDBProvider
 * @phpstan-type Generate \Closure(string $path, string $name, WorldCreationOptions $options) : void
 */
final class RewritableWorldProviderManagerEntry extends DimensionProviderManagerEntry{
	/** @phpstan-var FromPath */
	private Closure $fromPath;
	/** @phpstan-var Generate */
	private Closure $generate;

	public $hasDimensions = true;

	/**
	 * @phpstan-param FromPath $fromPath
	 * @phpstan-param Generate $generate
	 */
	public function __construct(Closure $isValid, Closure $fromPath, Closure $generate){
		parent::__construct($isValid);
		$this->fromPath = $fromPath;
		$this->generate = $generate;
	}

	public function fromPath(string $path, Logger $logger, int $dimension = 0, LevelDB $db = null) : WritableWorldProvider{
		return ($this->fromPath)($path, $logger, $dimension, $db);
	}

	/**
	 * Generates world manifest files and any other things needed to initialize a new world on disk
	 */
	public function generate(string $path, string $name, WorldCreationOptions $options) : void{
		($this->generate)($path, $name, $options);
	}
}
