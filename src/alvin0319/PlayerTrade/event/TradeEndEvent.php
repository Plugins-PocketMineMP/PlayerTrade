<?php

declare(strict_types=1);

namespace alvin0319\PlayerTrade\event;

use pocketmine\Player;

final class TradeEndEvent extends TradeEvent{

	public const REASON_SUCCESS = 0;

	public const REASON_SENDER_QUIT = 1;

	public const REASON_RECEIVER_QUIT = 2;

	public const REASON_SENDER_CANCEL = 3;

	public const REASON_RECEIVER_CANCEL = 4;

	protected int $reason = self::REASON_SUCCESS;

	public function __construct(Player $sender, Player $receiver, int $reason = self::REASON_SUCCESS){
		parent::__construct($sender, $receiver);
		$this->reason = $reason;
	}

	public function getReason() : int{
		return $this->reason;
	}

	public function setReason(int $reason) : void{
		$this->reason = $reason;
	}

	public function isTradeCanceled() : bool{
		return $this->reason !== self::REASON_SUCCESS;
	}
}