<?php

declare(strict_types=1);

namespace alvin0319\PlayerTrade\command;

use alvin0319\PlayerTrade\PlayerTrade;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\Player;
use function array_shift;
use function count;

final class TradeCommand extends PluginCommand{

	public function __construct(){
		parent::__construct("trade", PlayerTrade::getInstance());
		$this->setPermission("playertrade.command");
		$this->setDescription("Trade with other player!");
		$this->setUsage("/trade <accept|request|deny> <player>");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$this->testPermission($sender)){
			return false;
		}
		$plugin = PlayerTrade::getInstance();
		if(!$sender instanceof Player){
			$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.ingameOnly"));
			return false;
		}
		if(count($args) < 2){
			throw new InvalidCommandSyntaxException();
		}
		switch(array_shift($args)){
			case "request":
				if(PlayerTrade::getInstance()->hasRequest($sender)){
					$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.alreadyHaveRequest"));
					return false;
				}
				$player = $sender->getServer()->getPlayerExact(array_shift($args));
				if($player === null){
					$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.offlinePlayer"));
					return false;
				}
				PlayerTrade::getInstance()->addRequest($sender, $player);
				$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.requestSuccess", [
					$player->getName()
					]));
				$player->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.receiveRequest1", [
					$sender->getName()
					]));
				$player->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.receiveRequest2", [
					$sender->getName()
					]));
				break;
			case "accept":
				$player = $sender->getServer()->getPlayerExact(array_shift($args));
				if($player === null){
					$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.offlinePlayer"));
					return false;
				}
				if(!PlayerTrade::getInstance()->hasRequestFrom($sender, $player)){
					$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.noAnyRequest", [
						$player->getName()
						]));
					return false;
				}
				PlayerTrade::getInstance()->acceptRequest($sender);
				break;
			case "deny":
				$player = $sender->getServer()->getPlayerExact(array_shift($args));
				if($player === null){
					$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.offlinePlayer"));
					return false;
				}
				if(!PlayerTrade::getInstance()->hasRequestFrom($sender, $player)){
					$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.noAnyRequest", [
						$player->getName()
						]));
					return false;
				}
				PlayerTrade::getInstance()->denyRequest($sender);
				$sender->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.requestDeny", [
					$player->getName()
					]));
				$player->sendMessage(PlayerTrade::$prefix . $plugin->getLanguage()->translateString("command.requestDeny.sender"));
				break;
			default:
				throw new InvalidCommandSyntaxException();
		}
		return true;
	}
}