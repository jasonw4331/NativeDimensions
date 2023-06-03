<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\world\generator;

use InvalidArgumentException;
use function strtolower;

final class Environment{

	public static function fromString(string $string) : int{
		return match (strtolower($string)) {
			"overworld" => self::OVERWORLD,
			"nether" => self::NETHER,
			"end", "the_end" => self::THE_END,
			default => throw new InvalidArgumentException("Could not convert string \"{$string}\" to a " . self::class . " constant")
		};
	}

	public const OVERWORLD = 0;
	public const NETHER = -1;
	public const THE_END = 1;

	private function __construct(){
	}
}
