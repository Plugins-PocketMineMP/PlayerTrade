<?php

declare(strict_types=1);

namespace alvin0319\PlayerTrade;

use alvin0319\PlayerTrade\command\TradeCommand;
use alvin0319\PlayerTrade\task\TradeCheckTask;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use function spl_object_id;
use function time;

final class PlayerTrade extends PluginBase implements Listener{
	use SingletonTrait;

	public static string $prefix = "§b§l[PlayerTrade] §r§7";

	/** @var TradeQueue[] */
	protected array $queues = [];
	/** @var TradeQueue[] */
	protected array $player2queue = [];

	protected array $requests = [];

	public function onLoad() : void{ self::setInstance($this); }

	public function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->getScheduler()->scheduleRepeatingTask(new TradeCheckTask(), 20);

		$this->getServer()->getCommandMap()->register("playertrade", new TradeCommand());
	}

	public function onDisable() : void{
		foreach($this->queues as $id => $queue){
			$queue->cancel(false, false);
		}
	}

	public function addToQueue(Player $sender, Player $receiver) : void{
		$queue = new TradeQueue($sender, $receiver);
		$this->queues[spl_object_id($queue)] = $queue;
		$this->player2queue[$sender->getName()] = $queue;
		$this->player2queue[$receiver->getName()] = $queue;
	}

	public function removeFromQueue(TradeQueue $queue) : void{
		unset($this->queues[spl_object_id($queue)]);
		unset($this->player2queue[$queue->getSender()->getName()]);
		unset($this->player2queue[$queue->getReceiver()->getName()]);
	}

	public function getQueueByPlayer(Player $player) : ?TradeQueue{
		return $this->player2queue[$player->getName()] ?? null;
	}

	public function onPlayerQuit(PlayerQuitEvent $event) : void{
		$player = $event->getPlayer();

		if(($queue = $this->getQueueByPlayer($player)) !== null){
			$queue->cancel(true, $queue->isSender($player));
		}
	}

	public function addRequest(Player $sender, Player $receiver) : void{
		$this->requests[$sender->getName()] = [
			"receiver" => $receiver->getName(),
			"expireAt" => time() + 15
		];
	}

	public function hasRequest(Player $player) : bool{
		return isset($this->requests[$player->getName()]);
	}

	public function denyRequest(Player $player) : void{
		foreach($this->requests as $senderName => $requestData){
			if($requestData["receiver"] === $player->getName()){
				unset($this->requests[$senderName]);
				break;
			}
		}
	}

	public function hasRequestFrom(Player $player, Player $sender) : bool{
		foreach($this->requests as $senderName => $requestData){
			if($requestData["receiver"] === $player->getName() && $sender->getName() === $senderName){
				return true;
			}
		}
		return false;
	}

	public function acceptRequest(Player $player) : void{
		$found = false;
		foreach($this->requests as $senderName => $requestData){
			if($requestData["receiver"] === $player->getName()){
				$sender = $this->getServer()->getPlayerExact($senderName);
				if($sender === null){
					$player->sendMessage(PlayerTrade::$prefix . "You can't accept request from {$senderName} because sender has left game.");
					break;
				}
				$found = true;
				$this->addToQueue($sender, $player);
				unset($this->requests[$senderName]);
				break;
			}
		}
		if(!$found){
			$player->sendMessage(PlayerTrade::$prefix . "You don't have any request.");
		}
	}

	public function checkRequests() : void{
		foreach($this->requests as $senderName => $requestData){
			$sender = $this->getServer()->getPlayerExact($senderName);
			$receiver = $this->getServer()->getPlayerExact($requestData["receiver"]);
			$expireAt = $requestData["expireAt"];
			if($sender === null || $receiver === null){
				if($sender !== null){
					$sender->sendMessage(PlayerTrade::$prefix . "Your request has canceled because receiver has left game.");
				}
				if($receiver !== null){
					$receiver->sendMessage(PlayerTrade::$prefix . "Your request from {$senderName} has canceled because sender has left game.");
				}
				unset($this->requests[$senderName]);
				continue;
			}
			if(time() > $expireAt){
				$sender->sendMessage(PlayerTrade::$prefix . "Your trade request has expired.");
				$receiver->sendMessage(PlayerTrade::$prefix . "Your trade request from {$senderName} has expired.");
				unset($this->requests[$senderName]);
				continue;
			}
		}
	}
}