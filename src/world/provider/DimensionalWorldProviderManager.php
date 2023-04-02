<?php

declare(strict_types=1);

namespace jasonwynn10\NativeDimensions\world\provider;

use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\region\Anvil;
use pocketmine\world\format\io\region\McRegion;
use pocketmine\world\format\io\region\PMAnvil;
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
		$leveldb = new RewritableWorldProviderManagerEntry(\Closure::fromCallable([DimensionLevelDBProvider::class, 'isValid']), fn(string $path, int $dimension, ?\LevelDB $db) => new DimensionLevelDBProvider($path, $dimension, $db), \Closure::fromCallable([DimensionLevelDBProvider::class, 'generate']));
		$this->default = $leveldb;
		$this->addProvider($leveldb, "leveldb");

		$this->addProvider(new ReadOnlyWorldProviderManagerEntry(\Closure::fromCallable([Anvil::class, 'isValid']), function(string $path, int $dimension = 0){
			return match ($dimension) {
				DimensionIds::OVERWORLD => new Anvil($path),
				DimensionIds::NETHER => new NetherAnvilProvider($path),
				DimensionIds::THE_END => new EnderAnvilProvider($path),
				default => throw new \UnexpectedValueException("Invalid dimension Id")
			};
		}), "anvil");
		$this->addProvider(new ReadOnlyWorldProviderManagerEntry(\Closure::fromCallable([McRegion::class, 'isValid']), fn(string $path) => new McRegion($path)), "mcregion");
		$this->addProvider(new ReadOnlyWorldProviderManagerEntry(\Closure::fromCallable([PMAnvil::class, 'isValid']), fn(string $path) => new PMAnvil($path)), "pmanvil");
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
