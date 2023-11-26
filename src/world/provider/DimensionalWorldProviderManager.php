<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\world\provider;

use Closure;
use LevelDB;
use Logger;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\region\Anvil;
use function strtolower;
use function trim;

class DimensionalWorldProviderManager{
	/**
	 * @var DimensionProviderManagerEntry[]
	 * @phpstan-var array<string, DimensionProviderManagerEntry>
	 */
	protected array $providers = [];

	private RewritableWorldProviderManagerEntry $default;

	public function __construct(){
		$leveldb = new RewritableWorldProviderManagerEntry(DimensionLevelDBProvider::isValid(...), fn(string $path, Logger $logger, int $dimension, ?LevelDB $db) => new DimensionLevelDBProvider($path, $logger, $dimension, $db), DimensionLevelDBProvider::generate(...));
		$this->default = $leveldb;
		$this->addProvider($leveldb, "leveldb");

		$this->addProvider(new ReadOnlyWorldProviderManagerEntry(Anvil::isValid(...), function(string $path, Logger $logger, int $dimension = 0){
			return match ($dimension) {
				DimensionIds::OVERWORLD => new Anvil($path, $logger),
				DimensionIds::NETHER => new NetherAnvilProvider($path, $logger),
				DimensionIds::THE_END => new EnderAnvilProvider($path, $logger),
				default => throw new \UnexpectedValueException("Invalid dimension Id")
			};
		}), "anvil");
	}

	/**
	 * Returns the default format used to generate new worlds.
	 */
	public function getDefault() : RewritableWorldProviderManagerEntry{
		return $this->default;
	}

	public function setDefault(RewritableWorldProviderManagerEntry $class) : void{
		$this->default = $class;
	}

	public function addProvider(DimensionProviderManagerEntry $providerEntry, string $name, bool $overwrite = false) : void{
		$name = strtolower($name);
		if(!$overwrite && isset($this->providers[$name])){
			throw new \InvalidArgumentException("Alias \"$name\" is already assigned");
		}

		$this->providers[$name] = $providerEntry;
	}

	/**
	 * Returns a WorldProvider class for this path, or null
	 *
	 * @return DimensionProviderManagerEntry[]
	 * @phpstan-return array<string, DimensionProviderManagerEntry>
	 */
	public function getMatchingProviders(string $path) : array{
		$result = [];
		foreach(Utils::stringifyKeys($this->providers) as $alias => $providerEntry){
			if($providerEntry->isValid($path)){
				$result[$alias] = $providerEntry;
			}
		}
		return $result;
	}

	/**
	 * @return DimensionProviderManagerEntry[]
	 * @phpstan-return array<string, DimensionProviderManagerEntry>
	 */
	public function getAvailableProviders() : array{
		return $this->providers;
	}

	/**
	 * Returns a WorldProvider by name, or null if not found
	 */
	public function getProviderByName(string $name) : ?DimensionProviderManagerEntry{
		return $this->providers[trim(strtolower($name))] ?? null;
	}
}
