<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\world\generator\utils;

use jasonw4331\NativeDimensions\world\generator\noise\bukkit\OctaveGenerator;

/**
 * @phpstan-template T of OctaveGenerator
 * @phpstan-template U of OctaveGenerator
 * @phpstan-template V of OctaveGenerator
 * @phpstan-template W of OctaveGenerator
 * @phpstan-template X of OctaveGenerator
 * @phpstan-template Y of OctaveGenerator
 *
 * @phpstan-extends WorldOctaves<T, U, V, W>
 */
class NetherWorldOctaves extends WorldOctaves{

	/** @phpstan-var X */
	public OctaveGenerator $soul_sand;

	/** @phpstan-var Y */
	public OctaveGenerator $gravel;

	/**
	 * @phpstan-param T $height
	 * @phpstan-param U $roughness
	 * @phpstan-param U $roughness_2
	 * @phpstan-param V $detail
	 * @phpstan-param W $surface
	 * @phpstan-param X $soul_sand
	 * @phpstan-param Y $gravel
	 */
	public function __construct(
		OctaveGenerator $height,
		OctaveGenerator $roughness,
		OctaveGenerator $roughness_2,
		OctaveGenerator $detail,
		OctaveGenerator $surface,
		OctaveGenerator $soul_sand,
		OctaveGenerator $gravel
	){
		parent::__construct($height, $roughness, $roughness_2, $detail, $surface);
		$this->soul_sand = $soul_sand;
		$this->gravel = $gravel;
	}
}
