<?php

declare(strict_types=1);

namespace alvin0319\PlayerTrade\event;

use pocketmine\event\Event;
use pocketmine\Player;

abstract class TradeEvent extends Event{

	protected Player $sender;

	protected Player $receiver;

	public function __construct(Player $sender, Player $receiver){
		$this->sender = $sender;
		$this->receiver = $receiver;
	}

	public function getSender() : Player{
		return $this->sender;
	}

	public function getReceiver() : Player{
		return $this->receiver;
	}
}