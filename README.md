# NativeDimensions
[![Discord](https://img.shields.io/badge/chat-on%20discord-7289da.svg)](https://discord.gg/R7kdetE)
[![Poggit-Ci](https://poggit.pmmp.io/ci.shield/jasonw4331/NativeDimensions/NativeDimensions)](https://poggit.pmmp.io/ci/jasonw4331/NativeDimensions/NativeDimensions)
[![Download count](https://poggit.pmmp.io/shield.dl.total/NativeDimensions)](https://poggit.pmmp.io/p/NativeDimensions)

# Intro
Minecraft places all dimensions inside of a single world folder, but pocketmine doesn't use the data - until now.

This plugin was built to add the missing functionality for pocketmine to load multiple dimensions from a single world save and have everything be accessible.

This plugin even converts old anvil worlds to new leveldb worlds by itself!

# Features
* Automatically reads dimension data from anvil worlds
* Automatically reads and writes dimension data to and from leveldb worlds
* Automatic Anvil to LevelDB world conversion

# Future Additions
* Leveldb nether portal mapping
* Batch writes for more efficiency

# About
This plugin has been a long-running project to allow the use of dimensions from within one world save instead of having multiple. This is a proof of concept for the first approach @dktapps laid out [in this RFC](https://forums.pmmp.io/threads/dimensions-support-and-new-level-dimensions-api.361/).
