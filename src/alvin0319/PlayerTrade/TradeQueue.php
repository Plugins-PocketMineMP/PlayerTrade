<?php

declare(strict_types=1);

namespace alvin0319\PlayerTrade;

use alvin0319\PlayerTrade\task\TradeQueueCheckTask;
use Closure;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\Player;
use pocketmine\scheduler\TaskHandler;
use function count;
use function in_array;

final class TradeQueue{

	public const SENDER_SLOTS = [
		0, 1, 2, 3, 9, 10, 11, 12, 18, 19, 20, 21, 27, 28, 29, 30, 36, 37, 38, 39, 46, 47, 48
	];

	public const RECEIVER_SLOTS = [
		5, 6, 7, 8, 14, 15, 16, 17, 23, 24, 25, 26, 32, 33, 34, 35, 41, 42, 43, 44, 50, 51, 52, 53
	];

	public const BORDER_SLOTS = [
		4, 13, 22, 31, 40, 49
	];

	public const SENDER_DONE_SLOT = 45;

	public const RECEIVER_DONE_SLOT = 53;

	protected Player $sender;

	protected Player $receiver;

	protected InvMenu $senderMenu;

	protected InvMenu $receiverMenu;

	protected bool $done = false;

	protected bool $isSenderDone = false;

	protected bool $isReceiverDone = false;

	protected TaskHandler $handler;

	public function __construct(Player $sender, Player $receiver){
		$this->sender = $sender;
		$this->receiver = $receiver;
		$this->senderMenu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
		$this->senderMenu->setName("You      |     {$receiver->getName()}");
		$this->senderMenu->setListener(Closure::fromCallable([$this, "handleInventoryTransaction"]));
		$this->receiverMenu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
		$this->receiverMenu->setName("{$receiver->getName()}   |     You");
		$this->receiverMenu->setListener(Closure::fromCallable([$this, "handleInventoryTransaction"]));
		$this->senderMenu->setInventoryCloseListener(Closure::fromCallable([$this, "onInventoryClose"]));
		$this->receiverMenu->setInventoryCloseListener(Closure::fromCallable([$this, "onInventoryClose"]));

		$borderItem = ItemFactory::get(-161, 0, 1);
		$borderItem->setCustomName("§l ");

		$redItem = ItemFactory::get(ItemIds::TERRACOTTA, 14);
		$redItem->setCustomName("§cWait!");

		foreach(self::BORDER_SLOTS as $slot){
			$this->senderMenu->getInventory()->setItem($slot, $borderItem);
			$this->receiverMenu->getInventory()->setItem($slot, $borderItem);
		}

		$this->senderMenu->getInventory()->setItem(self::SENDER_DONE_SLOT, $redItem);
		$this->senderMenu->getInventory()->setItem(self::RECEIVER_DONE_SLOT, $redItem);
		$this->receiverMenu->getInventory()->setItem(self::SENDER_DONE_SLOT, $redItem);
		$this->receiverMenu->getInventory()->setItem(self::RECEIVER_DONE_SLOT, $redItem);

		$this->startTrade();
	}

	public function startTrade() : void{
		if(!$this->sender->isOnline() || !$this->receiver->isOnline()){
			return;
		}
		$this->senderMenu->send($this->sender);
		$this->receiverMenu->send($this->receiver);

		$this->handler = PlayerTrade::getInstance()->getScheduler()->scheduleRepeatingTask(new TradeQueueCheckTask($this), 20);
	}

	public function getSender() : Player{
		return $this->sender;
	}

	public function getReceiver() : Player{
		return $this->receiver;
	}

	public function done() : void{
		$senderRemains = [];
		$receiverRemains = [];
		foreach(self::SENDER_SLOTS as $slot){
			$item = $this->senderMenu->getInventory()->getItem($slot);
			if(!$item->isNull()){
				$senderRemains[] = $this->sender->getInventory()->addItem($item);
			}
		}
		foreach(self::RECEIVER_SLOTS as $slot){
			$item = $this->receiverMenu->getInventory()->getItem($slot);
			if(!$item->isNull()){
				$receiverRemains[] = $this->receiver->getInventory()->addItem($item);
			}
		}
		if(count($senderRemains) > 0){
			$this->sender->sendMessage(PlayerTrade::$prefix . "Your inventory is full!");
			foreach($senderRemains as $remain)
				$this->sender->dropItem($remain);
		}
		if(count($receiverRemains) > 0){
			$this->receiver->sendMessage(PlayerTrade::$prefix . "Your inventory is full!");
			foreach($receiverRemains as $remain)
				$this->receiver->dropItem($remain);
		}
		$this->senderMenu->onClose($this->sender);
		$this->receiverMenu->onClose($this->receiver);
		$this->done = true;
		$this->removeFrom();
	}

	public function cancel(bool $offline = true, bool $causedBySender = false) : void{
		foreach(self::SENDER_SLOTS as $slot){
			$item = $this->senderMenu->getInventory()->getItem($slot);
			if(!$item->isNull()){
				$this->sender->getInventory()->addItem($item);
			}
		}
		foreach(self::RECEIVER_SLOTS as $slot){
			$item = $this->receiverMenu->getInventory()->getItem($slot);
			if(!$item->isNull()){
				$this->receiver->getInventory()->addItem($item);
			}
		}
		if($offline){
			if($causedBySender){
				$this->receiverMenu->onClose($this->receiver);
				$this->receiver->sendMessage(PlayerTrade::$prefix . "Your trade was canceled because sender has left game.");
			}else{
				$this->senderMenu->onClose($this->sender);
				$this->sender->sendMessage(PlayerTrade::$prefix . "Your trade was canceled because receiver has left game.");
			}
		}else{
			$this->senderMenu->onClose($this->sender);
			$this->receiverMenu->onClose($this->receiver);
			$message = "Your trade was canceled because " . ($causedBySender ? "sender" : "receiver") . " has canceled the trade";
			$this->sender->sendMessage(PlayerTrade::$prefix . $message);
			$this->receiver->sendMessage(PlayerTrade::$prefix . $message);
		}
		$this->removeFrom();
	}

	public function removeFrom() : void{
		PlayerTrade::getInstance()->removeFromQueue($this);

		$this->handler->cancel();
	}

	public function check() : void{
		if($this->isSenderDone && $this->isReceiverDone){
			$this->done();
		}
	}

	public function handleInventoryTransaction(InvMenuTransaction $action) : InvMenuTransactionResult{
		$discard = $action->discard();
		$continue = $action->continue();
		$player = $action->getPlayer();
		$slot = $action->getAction()->getSlot();
		try{
			if($this->done) return $discard;
			if($this->isSender($player)){
				if($this->isSenderDone){
					return $discard;
				}
				if(!in_array($slot, self::SENDER_SLOTS) || $slot !== self::SENDER_DONE_SLOT){
					return $discard;
				}
				if($slot === self::SENDER_DONE_SLOT){
					$this->isSenderDone = true;
					return $discard;
				}
			}else{
				if($this->isReceiverDone){
					return $discard;
				}
				if(!in_array($slot, self::RECEIVER_SLOTS) || $slot !== self::RECEIVER_DONE_SLOT){
					return $discard;
				}
				if($slot === self::RECEIVER_DONE_SLOT){
					$this->isReceiverDone = true;
					return $discard;
				}
			}
			return $continue;
		}finally{
			$this->syncWith();
		}
	}

	public function onInventoryClose(Player $player) : void{
		if(!$this->done){
			$this->cancel(false, $this->isSender($player));
		}
	}

	public function isReceiver(Player $player) : bool{
		return $this->receiver->getName() === $player->getName();
	}

	public function isSender(Player $player) : bool{
		return $this->sender->getName() === $player->getName();
	}

	public function isDone() : bool{
		return $this->done;
	}

	public function syncWith() : void{
		$greenItem = ItemFactory::get(ItemIds::TERRACOTTA, 13);
		$greenItem->setCustomName("§aDone!");
		foreach(self::SENDER_SLOTS as $slot){
			$senderItem = $this->senderMenu->getInventory()->getItem($slot);
			$receiverItem = $this->receiverMenu->getInventory()->getItem($slot);
			if(!$senderItem->equalsExact($receiverItem)){
				$this->receiverMenu->getInventory()->setItem($slot, $senderItem);
			}
		}
		foreach(self::RECEIVER_SLOTS as $slot){
			$senderItem = $this->senderMenu->getInventory()->getItem($slot);
			$receiverItem = $this->receiverMenu->getInventory()->getItem($slot);
			if(!$receiverItem->equalsExact($senderItem)){
				$this->senderMenu->getInventory()->setItem($slot, $receiverItem);
			}
		}
		if($this->isSenderDone){
			if(!$this->senderMenu->getInventory()->getItem(self::SENDER_DONE_SLOT)->equalsExact($greenItem)){
				$this->senderMenu->getInventory()->setItem(self::SENDER_DONE_SLOT, $greenItem);
			}
			if(!$this->receiverMenu->getInventory()->getItem(self::SENDER_DONE_SLOT)->equalsExact($greenItem)){
				$this->receiverMenu->getInventory()->setItem(self::SENDER_DONE_SLOT, $greenItem);
			}
		}
		if($this->isReceiverDone){
			if(!$this->senderMenu->getInventory()->getItem(self::RECEIVER_DONE_SLOT)->equalsExact($greenItem)){
				$this->senderMenu->getInventory()->setItem(self::RECEIVER_DONE_SLOT, $greenItem);
			}
			if(!$this->receiverMenu->getInventory()->getItem(self::RECEIVER_DONE_SLOT)->equalsExact($greenItem)){
				$this->receiverMenu->getInventory()->setItem(self::RECEIVER_DONE_SLOT, $greenItem);
			}
		}
		if($this->isSenderDone && $this->isReceiverDone){
			$this->done();
		}
	}
}