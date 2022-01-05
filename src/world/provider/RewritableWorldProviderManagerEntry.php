<?php
declare(strict_types=1);
namespace jasonwynn10\NativeDimensions\world\provider;

use pocketmine\world\format\io\WorldProviderManagerEntry;
use pocketmine\world\format\io\WritableWorldProvider;
use pocketmine\world\WorldCreationOptions;

/**
 * @phpstan-type FromPath \Closure(string $path, int $dimension, \LevelDB $db) : WritableWorldProvider
 * @phpstan-type Generate \Closure(string $path, string $name, WorldCreationOptions $options) : void
 */
final class RewritableWorldProviderManagerEntry extends WorldProviderManagerEntry{
	/** @phpstan-var FromPath */
	private \Closure $fromPath;
	/** @phpstan-var Generate */
	private \Closure $generate;

	public $hasDimensions = true;

	/**
	 * @phpstan-param FromPath $fromPath
	 * @phpstan-param Generate $generate
	 */
	public function __construct(\Closure $isValid, \Closure $fromPath, \Closure $generate){
		parent::__construct($isValid);
		$this->fromPath = $fromPath;
		$this->generate = $generate;
	}

	public function fromPath(string $path, int $dimension = 0, \LevelDB $db = null) : WritableWorldProvider{
		return ($this->fromPath)($path, $dimension, $db);
	}

	/**
	 * Generates world manifest files and any other things needed to initialize a new world on disk
	 */
	public function generate(string $path, string $name, WorldCreationOptions $options) : void{
		($this->generate)($path, $name, $options);
	}
}