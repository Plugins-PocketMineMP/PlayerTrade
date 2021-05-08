<?php

declare(strict_types=1);

namespace alvin0319\PlayerTrade\task;

use alvin0319\PlayerTrade\PlayerTrade;
use pocketmine\scheduler\Task;

final class TradeCheckTask extends Task{

	public function onRun(int $_) : void{
		PlayerTrade::getInstance()->checkRequests();
	}
}