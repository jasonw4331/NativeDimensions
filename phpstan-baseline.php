<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$value of function count expects array\\|Countable, mixed given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$x of method pocketmine\\\\world\\\\World\\:\\:setBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$dimension_id of method jasonwynn10\\\\NativeDimensions\\\\Main\\:\\:applyToWorld\\(\\) expects 0\\|1\\|2, int\\<min, \\-1\\>\\|int\\<1, max\\> given\\.$#',
	'count' => 1,
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
	'message' => '#^Strict comparison using \\!\\=\\= between jasonwynn10\\\\NativeDimensions\\\\world\\\\DimensionalWorld and null will always evaluate to true\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Main.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$world of static method jasonwynn10\\\\NativeDimensions\\\\Main\\:\\:makeEndSpawn\\(\\) expects jasonwynn10\\\\NativeDimensions\\\\world\\\\DimensionalWorld, pocketmine\\\\world\\\\World given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/block/EndPortal.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$x of method pocketmine\\\\world\\\\World\\:\\:getBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 12,
	'path' => __DIR__ . '/src/block/Portal.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$y of method pocketmine\\\\world\\\\World\\:\\:getBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 12,
	'path' => __DIR__ . '/src/block/Portal.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$z of method pocketmine\\\\world\\\\World\\:\\:getBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 12,
	'path' => __DIR__ . '/src/block/Portal.php',
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
	'message' => '#^Cannot call method getPlayer\\(\\) on pocketmine\\\\network\\\\mcpe\\\\NetworkSession\\|null\\.$#',
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
	'message' => '#^Parameter \\#1 \\$array of function array_shift is passed by reference, so it expects variables only\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/event/DimensionListener.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$direction of method jasonwynn10\\\\NativeDimensions\\\\event\\\\DimensionListener\\:\\:testDirectionForObsidian\\(\\) expects int, int\\|null given\\.$#',
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
	'message' => '#^Method jasonwynn10\\\\NativeDimensions\\\\world\\\\DimensionalWorld\\:\\:getEnd\\(\\) should return pocketmine\\\\world\\\\World but returns pocketmine\\\\world\\\\World\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/DimensionalWorld.php',
];
$ignoreErrors[] = [
	'message' => '#^Method jasonwynn10\\\\NativeDimensions\\\\world\\\\DimensionalWorld\\:\\:getNether\\(\\) should return pocketmine\\\\world\\\\World but returns pocketmine\\\\world\\\\World\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/DimensionalWorld.php',
];
$ignoreErrors[] = [
	'message' => '#^Method jasonwynn10\\\\NativeDimensions\\\\world\\\\DimensionalWorld\\:\\:getOverworld\\(\\) should return pocketmine\\\\world\\\\World but returns pocketmine\\\\world\\\\World\\|null\\.$#',
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
	'message' => '#^Method jasonwynn10\\\\NativeDimensions\\\\world\\\\provider\\\\DimensionProviderManagerEntry\\:\\:fromPath\\(\\) invoked with 3 parameters, 1\\-2 required\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/world/DimensionalWorldManager.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$provider of class jasonwynn10\\\\NativeDimensions\\\\world\\\\DimensionalWorld constructor expects pocketmine\\\\world\\\\format\\\\io\\\\WritableWorldProvider, pocketmine\\\\world\\\\format\\\\io\\\\WorldProvider given\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/world/DimensionalWorldManager.php',
];
$ignoreErrors[] = [
	'message' => '#^Property jasonwynn10\\\\NativeDimensions\\\\world\\\\DimensionalWorldManager\\:\\:\\$worlds type has no value type specified in iterable type array\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/DimensionalWorldManager.php',
];
$ignoreErrors[] = [
	'message' => '#^jasonwynn10\\\\NativeDimensions\\\\world\\\\DimensionalWorldManager\\:\\:__construct\\(\\) does not call parent constructor from pocketmine\\\\world\\\\WorldManager\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/DimensionalWorldManager.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method pocketmine\\\\world\\\\format\\\\io\\\\WritableWorldProvider\\:\\:getDatabase\\(\\)\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/src/world/converter/DimensionalFormatConverter.php',
];
$ignoreErrors[] = [
	'message' => '#^Method jasonwynn10\\\\NativeDimensions\\\\world\\\\converter\\\\DimensionalFormatConverter\\:\\:generateNew\\(\\) should return array\\<jasonwynn10\\\\NativeDimensions\\\\world\\\\provider\\\\DimensionLevelDBProvider\\> but returns array\\<int, pocketmine\\\\world\\\\format\\\\io\\\\WritableWorldProvider\\>\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/converter/DimensionalFormatConverter.php',
];
$ignoreErrors[] = [
	'message' => '#^Method pocketmine\\\\world\\\\format\\\\io\\\\WorldData\\:\\:save\\(\\) invoked with 1 parameter, 0 required\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/converter/DimensionalFormatConverter.php',
];
$ignoreErrors[] = [
	'message' => '#^Method jasonwynn10\\\\NativeDimensions\\\\world\\\\data\\\\DimensionalBedrockWorldData\\:\\:setGenerator\\(\\) has no return type specified\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/data/DimensionalBedrockWorldData.php',
];
$ignoreErrors[] = [
	'message' => '#^Method jasonwynn10\\\\NativeDimensions\\\\world\\\\data\\\\DimensionalBedrockWorldData\\:\\:setName\\(\\) has no return type specified\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/data/DimensionalBedrockWorldData.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method generateValues\\(\\) on jasonwynn10\\\\NativeDimensions\\\\world\\\\generator\\\\biomegrid\\\\MapLayer\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/VanillaGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method setBiomeId\\(\\) on pocketmine\\\\world\\\\format\\\\Chunk\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/ender/EnderGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method setFullBlock\\(\\) on pocketmine\\\\world\\\\format\\\\Chunk\\|null\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/src/world/generator/ender/EnderGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset int\\<0, 15\\> does not exist on SplFixedArray\\<float\\>\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/ender/EnderGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable \\$y might not be defined\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/ender/populator/EnderPilar.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$biomeId of method pocketmine\\\\world\\\\format\\\\Chunk\\:\\:setBiomeId\\(\\) expects int, int\\|null given\\.$#',
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
	'message' => '#^jasonwynn10\\\\NativeDimensions\\\\world\\\\generator\\\\nether\\\\populator\\\\OrePopulator\\:\\:__construct\\(\\) does not call parent constructor from jasonwynn10\\\\NativeDimensions\\\\world\\\\generator\\\\nether\\\\populator\\\\biome\\\\OrePopulator\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/nether/populator/OrePopulator.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable variables are not allowed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/noise/bukkit/SimplexNoiseGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^jasonwynn10\\\\NativeDimensions\\\\world\\\\generator\\\\noise\\\\glowstone\\\\PerlinNoise\\:\\:__construct\\(\\) does not call parent constructor from jasonwynn10\\\\NativeDimensions\\\\world\\\\generator\\\\noise\\\\bukkit\\\\BasePerlinNoiseGenerator\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/noise/glowstone/PerlinNoise.php',
];
$ignoreErrors[] = [
	'message' => '#^Static property jasonwynn10\\\\NativeDimensions\\\\world\\\\generator\\\\object\\\\TerrainObject\\:\\:\\$PLANT_TYPES is never read, only written\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/generator/object/TerrainObject.php',
];
$ignoreErrors[] = [
	'message' => '#^jasonwynn10\\\\NativeDimensions\\\\world\\\\provider\\\\DimensionLevelDBProvider\\:\\:__construct\\(\\) does not call parent constructor from pocketmine\\\\world\\\\format\\\\io\\\\leveldb\\\\LevelDB\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/provider/DimensionLevelDBProvider.php',
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
	'message' => '#^Property jasonwynn10\\\\NativeDimensions\\\\world\\\\provider\\\\RewritableWorldProviderManagerEntry\\:\\:\\$hasDimensions has no type specified\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/provider/RewritableWorldProviderManagerEntry.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
