<?php

declare(strict_types=1);

namespace jasonw4331\NativeDimensions\utils;

use pocketmine\network\mcpe\cache\ChunkCache;
use pocketmine\network\mcpe\ChunkRequestTask;
use pocketmine\network\mcpe\compression\CompressBatchPromise;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

final class DimensionChunkCache extends ChunkCache{

	public static function from(ChunkCache $parent, int $dimension_id) : self{
		static $_world = new ReflectionProperty(ChunkCache::class, "world");
		static $_caches = new ReflectionProperty(ChunkCache::class, "caches");
		static $_hits = new ReflectionProperty(ChunkCache::class, "hits");
		static $_misses = new ReflectionProperty(ChunkCache::class, "misses");
		static $_compressor = new ReflectionProperty(ChunkCache::class, "compressor");
		static $_this = new ReflectionClass(self::class);
		$instance = $_this->newInstanceWithoutConstructor();
		$instance->dimension_id = $dimension_id;
		$_world->setValue($instance, $_world->getValue($parent));
		$_compressor->setValue($instance, $_compressor->getValue($parent));
		$_caches->setValue($instance, $_caches->getValue($parent));
		$_hits->setValue($instance, $_hits->getValue($parent));
		$_misses->setValue($instance, $_misses->getValue($parent));
		return $instance;
	}

	/** @var DimensionIds::* */
	public int $dimension_id;

	public function request(int $chunkX, int $chunkZ) : CompressBatchPromise{
		static $_world = new ReflectionProperty(ChunkCache::class, "world");
		static $_caches = new ReflectionProperty(ChunkCache::class, "caches");
		static $_hits = new ReflectionProperty(ChunkCache::class, "hits");
		static $_misses = new ReflectionProperty(ChunkCache::class, "misses");
		static $_compressor = new ReflectionProperty(ChunkCache::class, "compressor");

		$world = $_world->getValue($this);
		$caches = $_caches->getValue($this);
		$hits = $_hits->getValue($this);
		$misses = $_misses->getValue($this);
		$compressor = $_compressor->getValue($this);

		$world->registerChunkListener($this, $chunkX, $chunkZ);
		$chunk = $world->getChunk($chunkX, $chunkZ);
		if($chunk === null){
			throw new \InvalidArgumentException("Cannot request an unloaded chunk");
		}
		$chunkHash = World::chunkHash($chunkX, $chunkZ);

		if(isset($caches[$chunkHash])){
			++$hits;
			$_hits->setValue($this, $hits);
			return $caches[$chunkHash];
		}

		++$misses;
		$_misses->setValue($this, $misses);

		$world->timings->syncChunkSendPrepare->startTiming();
		try{
			$caches[$chunkHash] = new CompressBatchPromise();
			$_caches->setValue($this, $caches);

			$world->getServer()->getAsyncPool()->submitTask(
				new ChunkRequestTask(
					$chunkX,
					$chunkZ,
					$this->dimension_id,
					$chunk,
					$caches[$chunkHash],
					$compressor
				)
			);

			return $caches[$chunkHash];
		}finally{
			$world->timings->syncChunkSendPrepare->stopTiming();
		}
	}
}