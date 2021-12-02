<?php

declare(strict_types=1);

namespace IvanCraft623\WorldManager;

use IvanCraft623\WorldManager\command\WorldManageCommand;
use IvanCraft623\WorldManager\form\WorldManageForm;

use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;

use InvalidArgumentException;

final class WorldManager extends PluginBase {
	use SingletonTrait;
	
	protected function onLoad(): void {
		self::setInstance($this);
	}
	
	protected function onEnable(): void {
		$this->getServer()->getCommandMap()->register('worldmanager', new WorldManageCommand($this));
	}
	
	public function getPrefix(): string {
		return $this->getDescription()->getPrefix();
	}
	
	public function sendWorldManageForm(Player $player): void {
		$manager = (new WorldManageForm($player))->sendMain();
	}
}