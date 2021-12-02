<?php

declare(strict_types=1);

namespace IvanCraft623\WorldManager\form;

use IvanCraft623\WorldManager\WorldManager;
use IvanCraft623\WorldManager\form\api\CustomForm;
use IvanCraft623\WorldManager\form\api\ModalForm;
use IvanCraft623\WorldManager\form\api\SimpleForm;

use pocketmine\player\Player;
use pocketmine\utils\Limits;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\generator\normal\Normal;

final class WorldManageForm {

	private WorldManager $plugin;

	private Player $player;

	private ?World $world = null;

	private array $difficulties = ["Peaceful", "Easy", "Normal", "Hard"];

	public function __construct(Player $player) {
		$this->plugin = WorldManager::getInstance();
		$this->player = $player;
	}

	private function deleteWorld(World $world): void {
		$this->removeDir($this->plugin->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . $world->getFolderName() . DIRECTORY_SEPARATOR);
	}

	private function removeDir(string $path): void {
		if (!file_exists($path) || basename($path) == "." || basename($path) == "..") {
			return;
		}
		$scandir = scandir($path);
		if (is_array($scandir)) {
			foreach ($scandir as $item) {
				if ($item != "." || $item != "..") {
					if (is_dir($path . DIRECTORY_SEPARATOR . $item)) {
						self::removeDir($path . DIRECTORY_SEPARATOR . $item);
					}
					if (is_file($path . DIRECTORY_SEPARATOR . $item)) {
						unlink($path . DIRECTORY_SEPARATOR . $item);
					}
				}
			}
		}
		rmdir($path);
	}

	public function sendMain(): void {
		$form = new SimpleForm(function (Player $player, string $result = null) {
			if ($result === null) {
				return;
			}

			switch ($result) {
				case 'Create':
					$this->sendCreateWorld();
				break;

				case 'List':
					$this->sendWorldList();
				break;

				case 'Load':
					$this->sendLoadWorld();
				break;
			}
		});
		$form->setTitle("World Manager");
		$form->setContent("Select an option");
		$form->addButton("§5§lCreate\n§r§7Touch to see", -1, "", "Create");
		$form->addButton("§5§lWorld list\n§r§7Touch to see", 0, "", "List");
		$form->addButton("§5§lLoad\n§r§7Touch to see", 0, "", "Load");
		$form->sendToPlayer($this->player);
	}

	public function sendCreateWorld(string $name = "", string $seed = "", string $generator = "", string $error = ""): void {
		$form = new CustomForm(function (Player $player, array $result = null) {
			if ($result === null) {
				$this->sendMain();
				return;
			}

			# Check Name
			if ($result["name"] === "") {
				$this->sendCreateWorld($result["name"], $result["seed"], $result["generator"], "You must provide a valid name!");
				return;
			}
			if ($this->plugin->getServer()->getWorldManager()->isWorldGenerated($result["name"])) {
				$this->sendCreateWorld($result["name"], $result["seed"], $result["generator"], "There is already a world with that name!");
				return;
			}
			# Check Seed
			if ($result["seed"] !== "") {
				if (!is_numeric(($result["seed"]))) {
					$this->sendCreateWorld($result["name"], $result["seed"], $result["generator"], "Seed must be type numeric!");
					return;
				}
				$seed = (int)$result["seed"];
				$seed = min($seed, Limits::INT32_MAX);
				$seed = max($seed, Limits::INT32_MIN);
			} else {
				$seed = random_int(Limits::INT32_MIN, Limits::INT32_MAX);
			}
			# Check Generator
			if ($result["generator"] !== "") {
				$generatorEntry = GeneratorManager::getInstance()->getGenerator($result["generator"]);
				if ($generatorEntry === null) {
					$this->sendCreateWorld($result["name"], $result["seed"], $result["generator"], "Generator does not exists!");
					return;
				}
				$generator = $generatorEntry->getGeneratorClass();
			} else {
				$generator = Normal::class;
			}
			# Generate world
			$options = WorldCreationOptions::create();
			$options->setSeed($seed);
			$options->setGeneratorClass($generator);
			$this->plugin->getServer()->getWorldManager()->generateWorld($result["name"], $options);
			$player->sendMessage($this->plugin->getPrefix() . "§bWorld §e".$result["name"]." §bhas been §agenerated §bwith the seed §e".$seed." §band generator §e".$result["generator"]." §bsuccessfully!");
		});
		$form->setTitle("World Manager");
		$form->addLabel("Create a world.".($error === "" ? "" : "\n\n§c".$error));
		$form->addInput("Name:", "", $name, "name");
		$form->addInput("Seed:", "", $seed, "seed");
		$form->addInput("Generator:", "", $generator, "generator");
		$form->sendToPlayer($this->player);
	}

	public function sendWorldList(): void {
		$this->world = null;
		$form = new SimpleForm(function (Player $player, World $result = null) {
			if ($result === null) {
				$this->sendMain();
				return;
			}

			$this->world = $result;
			$this->sendWorldManage();
		});
		$form->setTitle("World Manager");
		$form->setContent("World list.");
		foreach ($this->plugin->getServer()->getWorldManager()->getWorlds() as $world) {
			$form->addButton("§9§l".$world->getFolderName()."\n§r§a".count($world->getPlayers())." Players", -1, "", $world);
		}
		$form->sendToPlayer($this->player);
	}

	public function sendWorldManage(): void {
		$world = $this->world;
		if (!$world->isLoaded()) {
			$this->player->sendMessage($this->plugin->getPrefix() . "§cWorld has been unloaded!");
			return;
		}
		$form = new SimpleForm(function (Player $player, string $result = null) {
			if ($result === null) {
				$this->sendWorldList();
				return;
			}
			$world = $this->world;
			if (!$world->isLoaded()) {
				$player->sendMessage($this->plugin->getPrefix() . "§cWorld has been unloaded!");
				return;
			}

			switch ($result) {
				case 'Teleport':
					$spawnpoint = $world->getSpawnLocation();
					$world->loadChunk($spawnpoint->getFloorX(), $spawnpoint->getFloorZ());
					$player->teleport($world->getSafeSpawn());
					$player->sendMessage($this->plugin->getPrefix() . "§bYou have been teleported to §e".$world->getFolderName()."§b!");
				break;

				case 'Save':
					$world->save(true);
					$player->sendMessage($this->plugin->getPrefix() . "§bWorld §e".$world->getFolderName()." §bhas been §asaved §bsuccessfully!");
				break;

				case 'Auto-Save':
					$this->sendWorldAutoSave();
				break;

				case 'Difficulty':
					$this->sendWorldDifficulty();
				break;

				case 'Unload':
					if ($world === $this->plugin->getServer()->getWorldManager()->getDefaultWorld()) {
						$player->sendMessage($this->plugin->getPrefix() . "§cThe default world cannot be unloaded while running, please switch worlds.");
						return;
					}
					$this->plugin->getServer()->getWorldManager()->unloadWorld($world);
					$player->sendMessage($this->plugin->getPrefix() . "§bWorld §e".$world->getFolderName()." §bhas been §cunloaded §bsuccessfully!");
				break;

				case 'Delete':
					$this->sendWorldDelete();
				break;
			}
		});
		$spawnpoint = $world->getSpawnLocation();
		$form->setTitle("World Manager");
		$form->setContent(
			"Manage world: §b".$world->getFolderName()."\n\n".
			"§aGenerator: §e".$world->getProvider()->getWorldData()->getGenerator()."\n".
			"§aSpawnpoint: §e"."x: ".$spawnpoint->x.", y: ".$spawnpoint->y.", z: ".$spawnpoint->z."\n".
			"§aDifficulty: §e".$this->difficulties[$world->getDifficulty()]."\n".
			"§aSeed: §e".$world->getSeed()."\n".
			"§aAuto Save: §e".($world->getAutoSave() ? "enabled" : "disabled")."\n".
			"§aPlayers: §e".count($world->getPlayers())."\n".
			"§aEntities: §e".count($world->getEntities())."\n".
			"§aLoaded Chunks: §e".count($world->getLoadedChunks())
		);
		$form->addButton("Teleport", -1, "", "Teleport");
		$form->addButton("Save", -1, "", "Save");
		$form->addButton("Auto-Save", -1, "", "Auto-Save");
		$form->addButton("Difficulty", -1, "", "Difficulty");
		$form->addButton("Unload", -1, "", "Unload");
		$form->addButton("§4Delete", -1, "", "Delete");
		$form->sendToPlayer($this->player);
	}

	public function sendWorldAutoSave(): void {
		$form = new CustomForm(function (Player $player, array $result = null) {
			if ($result === null) {
				$this->sendWorldManage();
				return;
			}
			if (!$this->world->isLoaded()) {
				$player->sendMessage($this->plugin->getPrefix() . "§cWorld has been unloaded!");
				return;
			}
			$this->world->setAutoSave($result["Auto-Save"]);
			$player->sendMessage($this->plugin->getPrefix() . "§bYou have toggled §e".$world->getFolderName()." §bauto-save to ".($result["Auto-Save"] ? "§atrue" : "§cfalse")."§b!");
		});
		$form->setTitle("World Manager");
		$form->addToggle("Auto-Save", $this->world->getAutoSave(), "Auto-Save");
		$form->sendToPlayer($this->player);
	}

	public function sendWorldDifficulty(): void {
		$form = new CustomForm(function (Player $player, array $result = null) {
			if ($result === null) {
				$this->sendWorldManage();
				return;
			}
			if (!$this->world->isLoaded()) {
				$player->sendMessage($this->plugin->getPrefix() . "§cWorld has been unloaded!");
				return;
			}
			$this->world->setDifficulty($result["Difficulty"]);
			$player->sendMessage($this->plugin->getPrefix() . "§bYou have set the world §e".$this->world->getFolderName()." §bdifficulty to §e".$this->difficulties[$result["Difficulty"]]."§b!");
		});
		$form->setTitle("World Manager");
		$form->addDropdown("Difficulty", $this->difficulties, $this->world->getDifficulty(), "Difficulty");
		$form->sendToPlayer($this->player);
	}

	public function sendWorldDelete(): void {
		$form = new ModalForm(function (Player $player, bool $result = null) {
			if ($result === null || !$result) {
				$this->sendWorldManage();
				return;
			}
			if (!$this->world->isLoaded()) {
				$player->sendMessage($this->plugin->getPrefix() . "§cWorld has been unloaded!");
				return;
			}
			if ($this->world === $this->plugin->getServer()->getWorldManager()->getDefaultWorld()) {
				$player->sendMessage($this->plugin->getPrefix() . "§cThe default world cannot be deleted while running, please switch worlds.");
				return;
			}
			$this->plugin->getServer()->getWorldManager()->unloadWorld($this->world);
			$this->deleteWorld($this->world);
			$player->sendMessage($this->plugin->getPrefix() . "§bWorld §e".$this->world->getFolderName()." §bhas been §cdeleted §bsuccessfully!");
		});
		$form->setTitle("World Manager");
		$form->setContent("Are you sure you want to delete the world: §c".$this->world->getFolderName()."§r?\nThis action will be irreversible!");
		$form->setButton1("§4Delete");
		$form->setButton2("Cancel");
		$form->sendToPlayer($this->player);
	}

	public function sendLoadWorld(): void {
		$form = new SimpleForm(function (Player $player, string $result = null) {
			if ($result === null) {
				$this->sendMain();
				return;
			}

			if ($this->plugin->getServer()->getWorldManager()->loadWorld($result, true)) {
				$player->sendMessage($this->plugin->getPrefix() . "§bWorld §e".$result." §bhas been §aloaded §bsuccessfully!");
			} else {
				$player->sendMessage($this->plugin->getPrefix() . "§cCouldn't load the world ".$result."!");
			}
		});
		$form->setTitle("World Manager");
		$form->setContent("Load world list.");
		foreach (array_diff(scandir($this->plugin->getServer()->getDataPath() . "worlds"), ["..", "."]) as $worldName) {
			if (!$this->plugin->getServer()->getWorldManager()->isWorldLoaded($worldName)) {
				$form->addButton("§9§l".$worldName."\n§r§7Click to load", -1, "", $worldName);
			}
		}
		$form->sendToPlayer($this->player);
	}
}