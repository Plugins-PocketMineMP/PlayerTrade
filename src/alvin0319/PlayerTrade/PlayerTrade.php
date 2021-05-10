<?php

declare(strict_types=1);

namespace alvin0319\PlayerTrade;

use alvin0319\PlayerTrade\command\TradeCommand;
use alvin0319\PlayerTrade\task\TradeCheckTask;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\lang\BaseLang;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use function file_exists;
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

	protected BaseLang $lang;

	protected int $expireTime = 15;

	public function onLoad() : void{ self::setInstance($this); }

	public function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->getScheduler()->scheduleRepeatingTask(new TradeCheckTask(), 20);

		$this->getServer()->getCommandMap()->register("playertrade", new TradeCommand());

		$this->saveDefaultConfig();

		if(!file_exists($this->getDataFolder() . "lang/" . $this->getConfig()->get("lang", "eng") . ".ini") && $this->saveResource("lang/{$this->getConfig()->get("lang", "eng")}.ini")){
			$this->getLogger()->alert("Language file not found... use english as default...");
			$this->getConfig()->set("lang", "eng");
		}

		$this->saveResource($path = "lang/" . $this->getConfig()->get("lang", "eng"));

		$this->lang = new BaseLang($this->getConfig()->get("lang", "eng"), $this->getDataFolder() . "lang/");

		self::$prefix = $this->getConfig()->get("prefix", "§b§l[PlayerTrade] §r§7");

		$this->expireTime = (int) $this->getConfig()->get("requestExpire", 15);
	}

	public function getLanguage() : BaseLang{
		return $this->lang;
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
			"expireAt" => time() + $this->expireTime
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
					$player->sendMessage(PlayerTrade::$prefix . $this->getLanguage()->translateString("trade.cannotAcceptRequest", [
						$senderName
						]));
					break;
				}
				$found = true;
				$this->addToQueue($sender, $player);
				unset($this->requests[$senderName]);
				break;
			}
		}
		if(!$found){
			$player->sendMessage(PlayerTrade::$prefix . $this->getLanguage()->translateString("trade.noRequest"));
		}
	}

	public function checkRequests() : void{
		foreach($this->requests as $senderName => $requestData){
			$sender = $this->getServer()->getPlayerExact($senderName);
			$receiver = $this->getServer()->getPlayerExact($requestData["receiver"]);
			$expireAt = $requestData["expireAt"];
			if($sender === null || $receiver === null){
				if($sender !== null){
					$sender->sendMessage(PlayerTrade::$prefix . $this->getLanguage()->translateString("trade.requestCanceled.receiverLeft"));
				}
				if($receiver !== null){
					$receiver->sendMessage(PlayerTrade::$prefix . $this->getLanguage()->translateString("trade.requestCanceled.senderLeft", [
						$senderName
						]));
				}
				unset($this->requests[$senderName]);
				continue;
			}
			if(time() > $expireAt){
				$sender->sendMessage(PlayerTrade::$prefix . $this->getLanguage()->translateString("trade.requestExpired.sender"));
				$receiver->sendMessage(PlayerTrade::$prefix . $this->getLanguage()->translateString("trade.requestExpired.receiver", [
					$senderName
					]));
				unset($this->requests[$senderName]);
				continue;
			}
		}
	}
}