<?php

declare(strict_types=1);

namespace IvanCraft623\WorldManager\command;

use IvanCraft623\WorldManager\WorldManager;

use pocketmine\Server;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;

final class WorldManageCommand extends Command implements PluginOwned {

	private WorldManager $plugin;

	public function __construct(WorldManager $plugin) {
		parent::__construct('worldmanage', 'Manage worlds.');
		$this->plugin = $plugin;
		$this->setAliases(["wmanage"]);
		$this->setPermission("worldmanager.use");
	}

	public function getOwningPlugin(): WorldManager {
		return $this->plugin;
	}

	public function execute(CommandSender $sender, string $label, array $args) {
		if (!$this->checkPermission($sender)) {
			return;
		}
		if (!$sender instanceof Player) {
			$sender->sendMessage($this->plugin->getPrefix() . "§cYou can only use this command in the game!");
			return;
		}
		$this->plugin->sendWorldManageForm($sender);
	}

	public function checkPermission(CommandSender $sender): bool {
		if (!$this->testPermission($sender)) {
			$sender->sendMessage($this->plugin->getPrefix() . "§cYou do not have permission to use this command!");
			return false;
		}
		return true;
	}
}