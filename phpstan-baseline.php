<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$x of method pocketmine\\\\world\\\\World\\:\\:setBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$needle of function str_contains expects string, mixed given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$y of method pocketmine\\\\world\\\\World\\:\\:setBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$z of method pocketmine\\\\world\\\\World\\:\\:setBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\!\\=\\= between jasonw4331\\\\NativeDimensions\\\\world\\\\DimensionalWorld and null will always evaluate to true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$world of static method jasonw4331\\\\NativeDimensions\\\\Main\\:\\:makeEndSpawn\\(\\) expects jasonw4331\\\\NativeDimensions\\\\world\\\\DimensionalWorld, pocketmine\\\\world\\\\World given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/block/EndPortal.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$x of method pocketmine\\\\world\\\\World\\:\\:getBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 12,
	'path' => __DIR__ . '/src/block/NetherPortal.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$y of method pocketmine\\\\world\\\\World\\:\\:getBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 12,
	'path' => __DIR__ . '/src/block/NetherPortal.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$z of method pocketmine\\\\world\\\\World\\:\\:getBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 12,
	'path' => __DIR__ . '/src/block/NetherPortal.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method pocketmine\\\\block\\\\Block\\:\\:isSameType\\(\\)\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/event/DimensionListener.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method getId\\(\\) on pocketmine\\\\player\\\\Player\\|null\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/event/DimensionListener.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method getNetworkSession\\(\\) on pocketmine\\\\player\\\\Player\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/event/DimensionListener.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method getPosition\\(\\) on pocketmine\\\\block\\\\Bed\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/event/DimensionListener.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method getPosition\\(\\) on pocketmine\\\\player\\\\Player\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/event/DimensionListener.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method getWorld\\(\\) on pocketmine\\\\player\\\\Player\\|null\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/src/event/DimensionListener.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$direction of method jasonw4331\\\\NativeDimensions\\\\event\\\\DimensionListener\\:\\:testDirectionForObsidian\\(\\) expects int, int\\|null given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/event/DimensionListener.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$side of method pocketmine\\\\world\\\\Position\\:\\:getSide\\(\\) expects int, int\\|null given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/event/DimensionListener.php',
];
$ignoreErrors[] = [
	'message' => '#^Undefined variable\\: \\$widthA$#',
	'count' => 1,
	'path' => __DIR__ . '/src/event/DimensionListener.php',
];
$ignoreErrors[] = [
	'message' => '#^Undefined variable\\: \\$widthB$#',
	'count' => 1,
	'path' => __DIR__ . '/src/event/DimensionListener.php',
];
$ignoreErrors[] = [
	'message' => '#^Method jasonw4331\\\\NativeDimensions\\\\world\\\\DimensionalWorld\\:\\:getDimensionId\\(\\) should return 0\\|1\\|2 but returns int\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/DimensionalWorld.php',
];
$ignoreErrors[] = [
	'message' => '#^Method jasonw4331\\\\NativeDimensions\\\\world\\\\DimensionalWorld\\:\\:getEnd\\(\\) should return pocketmine\\\\world\\\\World but returns pocketmine\\\\world\\\\World\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/DimensionalWorld.php',
];
$ignoreErrors[] = [
	'message' => '#^Method jasonw4331\\\\NativeDimensions\\\\world\\\\DimensionalWorld\\:\\:getNether\\(\\) should return pocketmine\\\\world\\\\World but returns pocketmine\\\\world\\\\World\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/DimensionalWorld.php',
];
$ignoreErrors[] = [
	'message' => '#^Method jasonw4331\\\\NativeDimensions\\\\world\\\\DimensionalWorld\\:\\:getOverworld\\(\\) should return pocketmine\\\\world\\\\World but returns pocketmine\\\\world\\\\World\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/DimensionalWorld.php',
];
$ignoreErrors[] = [
	'message' => '#^Method pocketmine\\\\world\\\\format\\\\io\\\\WorldData\\:\\:getSpawn\\(\\) invoked with 1 parameter, 0 required\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/DimensionalWorld.php',
];
$ignoreErrors[] = [
	'message' => '#^Method pocketmine\\\\world\\\\format\\\\io\\\\WorldData\\:\\:save\\(\\) invoked with 1 parameter, 0 required\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/DimensionalWorld.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method pocketmine\\\\world\\\\format\\\\io\\\\WritableWorldProvider\\:\\:getDatabase\\(\\)\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/world/DimensionalWorldManager.php',
];
$ignoreErrors[] = [
	'message' => '#^Method jasonw4331\\\\NativeDimensions\\\\world\\\\provider\\\\DimensionProviderManagerEntry\\:\\:fromPath\\(\\) invoked with 4 parameters, 2\\-3 required\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/world/DimensionalWorldManager.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$provider of class jasonw4331\\\\NativeDimensions\\\\world\\\\DimensionalWorld constructor expects pocketmine\\\\world\\\\format\\\\io\\\\WritableWorldProvider, pocketmine\\\\world\\\\format\\\\io\\\\WorldProvider given\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/world/DimensionalWorldManager.php',
];
$ignoreErrors[] = [
	'message' => '#^Property jasonw4331\\\\NativeDimensions\\\\world\\\\DimensionalWorldManager\\:\\:\\$worlds \\(array\\<jasonw4331\\\\NativeDimensions\\\\world\\\\DimensionalWorld\\>\\) does not accept array\\<pocketmine\\\\world\\\\World\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/DimensionalWorldManager.php',
];
$ignoreErrors[] = [
	'message' => '#^jasonw4331\\\\NativeDimensions\\\\world\\\\DimensionalWorldManager\\:\\:__construct\\(\\) does not call parent constructor from pocketmine\\\\world\\\\WorldManager\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/DimensionalWorldManager.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method pocketmine\\\\world\\\\format\\\\io\\\\LoadedChunkData\\:\\:getChunk\\(\\)\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/converter/DimensionalFormatConverter.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method pocketmine\\\\world\\\\format\\\\io\\\\WritableWorldProvider\\:\\:getDatabase\\(\\)\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/src/world/converter/DimensionalFormatConverter.php',
];
$ignoreErrors[] = [
	'message' => '#^Method jasonw4331\\\\NativeDimensions\\\\world\\\\converter\\\\DimensionalFormatConverter\\:\\:generateNew\\(\\) should return array\\<jasonw4331\\\\NativeDimensions\\\\world\\\\provider\\\\DimensionLevelDBProvider\\> but returns array\\<int, pocketmine\\\\world\\\\format\\\\io\\\\WritableWorldProvider\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/converter/DimensionalFormatConverter.php',
];
$ignoreErrors[] = [
	'message' => '#^Method pocketmine\\\\world\\\\format\\\\io\\\\WorldData\\:\\:save\\(\\) invoked with 1 parameter, 0 required\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/converter/DimensionalFormatConverter.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$logger of method jasonw4331\\\\NativeDimensions\\\\world\\\\provider\\\\RewritableWorldProviderManagerEntry\\:\\:fromPath\\(\\) expects Logger, int given\\.$#',
	'count' => 6,
	'path' => __DIR__ . '/src/world/converter/DimensionalFormatConverter.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$chunkData of method jasonw4331\\\\NativeDimensions\\\\world\\\\provider\\\\DimensionLevelDBProvider\\:\\:saveChunk\\(\\) expects pocketmine\\\\world\\\\format\\\\io\\\\ChunkData, pocketmine\\\\world\\\\format\\\\io\\\\LoadedChunkData given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/converter/DimensionalFormatConverter.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method generateValues\\(\\) on jasonw4331\\\\NativeDimensions\\\\world\\\\generator\\\\biomegrid\\\\MapLayer\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/VanillaGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$y might not be defined\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/ender/populator/EnderPilar.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#4 \\$biomeId of method pocketmine\\\\world\\\\format\\\\Chunk\\:\\:setBiomeId\\(\\) expects int, int\\|null given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/nether/NetherGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$x of method pocketmine\\\\world\\\\ChunkManager\\:\\:getBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/nether/decorator/GlowstoneDecorator.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$y of method pocketmine\\\\world\\\\ChunkManager\\:\\:getBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/nether/decorator/GlowstoneDecorator.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$z of method pocketmine\\\\world\\\\ChunkManager\\:\\:getBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/nether/decorator/GlowstoneDecorator.php',
];
$ignoreErrors[] = [
	'message' => '#^jasonw4331\\\\NativeDimensions\\\\world\\\\generator\\\\nether\\\\populator\\\\OrePopulator\\:\\:__construct\\(\\) does not call parent constructor from jasonw4331\\\\NativeDimensions\\\\world\\\\generator\\\\nether\\\\populator\\\\biome\\\\OrePopulator\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/nether/populator/OrePopulator.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable variables are not allowed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/noise/bukkit/SimplexNoiseGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^jasonw4331\\\\NativeDimensions\\\\world\\\\generator\\\\noise\\\\glowstone\\\\PerlinNoise\\:\\:__construct\\(\\) does not call parent constructor from jasonw4331\\\\NativeDimensions\\\\world\\\\generator\\\\noise\\\\bukkit\\\\BasePerlinNoiseGenerator\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/noise/glowstone/PerlinNoise.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property jasonw4331\\\\NativeDimensions\\\\world\\\\generator\\\\object\\\\TerrainObject\\:\\:\\$PLANT_TYPES is never read, only written\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/object/TerrainObject.php',
];
$ignoreErrors[] = [
	'message' => '#^Part \\$value \\(mixed\\) of encapsed string cannot be cast to string\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/utils/preset/SimpleGeneratorPreset.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method pocketmine\\\\world\\\\format\\\\io\\\\ChunkData\\:\\:getChunk\\(\\)\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/provider/DimensionLevelDBProvider.php',
];
$ignoreErrors[] = [
	'message' => '#^Class pocketmine\\\\world\\\\format\\\\io\\\\ChunkData constructor invoked with 3 parameters, 4 required\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/provider/DimensionLevelDBProvider.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$subChunks of class pocketmine\\\\world\\\\format\\\\io\\\\ChunkData constructor expects array\\<pocketmine\\\\world\\\\format\\\\SubChunk\\>, pocketmine\\\\world\\\\format\\\\Chunk given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/provider/DimensionLevelDBProvider.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$populated of class pocketmine\\\\world\\\\format\\\\io\\\\ChunkData constructor expects bool, array\\<pocketmine\\\\nbt\\\\tag\\\\CompoundTag\\> given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/provider/DimensionLevelDBProvider.php',
];
$ignoreErrors[] = [
	'message' => '#^jasonw4331\\\\NativeDimensions\\\\world\\\\provider\\\\DimensionLevelDBProvider\\:\\:__construct\\(\\) does not call parent constructor from pocketmine\\\\world\\\\format\\\\io\\\\leveldb\\\\LevelDB\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/provider/DimensionLevelDBProvider.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$logger of class jasonw4331\\\\NativeDimensions\\\\world\\\\provider\\\\DimensionLevelDBProvider constructor expects Logger, int given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/provider/DimensionalWorldProviderManager.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$dimension of class jasonw4331\\\\NativeDimensions\\\\world\\\\provider\\\\DimensionLevelDBProvider constructor expects int, LevelDB\\|null given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/provider/DimensionalWorldProviderManager.php',
];
$ignoreErrors[] = [
	'message' => '#^Argument of an invalid type array\\<int, string\\>\\|false supplied for foreach, only iterables are supported\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/provider/EnderAnvilProvider.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset 1 on mixed\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/world/provider/EnderAnvilProvider.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset 2 on mixed\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/world/provider/EnderAnvilProvider.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot cast mixed to int\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/src/world/provider/EnderAnvilProvider.php',
];
$ignoreErrors[] = [
	'message' => '#^Argument of an invalid type array\\<int, string\\>\\|false supplied for foreach, only iterables are supported\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/provider/NetherAnvilProvider.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset 1 on mixed\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/world/provider/NetherAnvilProvider.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset 2 on mixed\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/world/provider/NetherAnvilProvider.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot cast mixed to int\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/src/world/provider/NetherAnvilProvider.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$db of closure expects LevelDB, LevelDB\\|null given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/provider/RewritableWorldProviderManagerEntry.php',
];
$ignoreErrors[] = [
	'message' => '#^Property jasonw4331\\\\NativeDimensions\\\\world\\\\provider\\\\RewritableWorldProviderManagerEntry\\:\\:\\$hasDimensions has no type specified\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/provider/RewritableWorldProviderManagerEntry.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
