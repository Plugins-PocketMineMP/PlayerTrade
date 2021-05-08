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
		if(!$sender instanceof Player){
			$sender->sendMessage(PlayerTrade::$prefix . "You can't use this command on console.");
			return false;
		}
		if(count($args) < 2){
			throw new InvalidCommandSyntaxException();
		}
		switch(array_shift($args)){
			case "request":
				if(PlayerTrade::getInstance()->hasRequest($sender)){
					$sender->sendMessage(PlayerTrade::$prefix . "You already have a request!");
					return false;
				}
				$player = $sender->getServer()->getPlayerExact(array_shift($args));
				if($player === null){
					$sender->sendMessage(PlayerTrade::$prefix . "This player is offline.");
					return false;
				}
				PlayerTrade::getInstance()->addRequest($sender, $player);
				$sender->sendMessage(PlayerTrade::$prefix . "You requested trade to {$player->getName()}.");
				$player->sendMessage(PlayerTrade::$prefix . "You received trade request from {$sender->getName()}");
				$player->sendMessage(PlayerTrade::$prefix . "To accept, use /trade accept {$sender->getName()}");
				break;
			case "accept":
				$player = $sender->getServer()->getPlayerExact(array_shift($args));
				if($player === null){
					$sender->sendMessage(PlayerTrade::$prefix . "This player is offline.");
					return false;
				}
				if(!PlayerTrade::getInstance()->hasRequestFrom($sender, $player)){
					$sender->sendMessage(PlayerTrade::$prefix . "You don't have any request from {$player->getName()}.");
					return false;
				}
				PlayerTrade::getInstance()->acceptRequest($sender);
				break;
			case "deny":
				$player = $sender->getServer()->getPlayerExact(array_shift($args));
				if($player === null){
					$sender->sendMessage(PlayerTrade::$prefix . "This player is offline.");
					return false;
				}
				if(!PlayerTrade::getInstance()->hasRequestFrom($sender, $player)){
					$sender->sendMessage(PlayerTrade::$prefix . "You don't have any request from {$player->getName()}.");
					return false;
				}
				PlayerTrade::getInstance()->denyRequest($sender);
				$sender->sendMessage(PlayerTrade::$prefix . "You denied request from {$player->getName()}");
				$player->sendMessage(PlayerTrade::$prefix . "Your trade request has denied.");
				break;
			default:
				throw new InvalidCommandSyntaxException();
		}
		return true;
	}
}