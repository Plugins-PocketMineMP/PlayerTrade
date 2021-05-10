# PlayerTrade
A PocketMine-MP plugin that implements trade like PC server!

# Features
* User-modifiable message
* Trade request expiration time can be set
* Clear design
* Developer API

# Commands
|command|description|
|---|---|
|/trade request <player>|Request a trade from the player.|
|/trade accept <player>|Accept the player's trade request.|
|/trade deny <player>|Decline the player's trade request.|

# Developer Docs
`\alvin0319\PlayerTrade\event\TradeStartEvent`: Called when player starts trade. (You can cancel this event)
```php
public function onTradeStart(\alvin0319\PlayerTrade\event\TradeStartEvent $event) : void{
    $sender = $event->getSender();
    $receiver = $event->getReceiver();
    if(some condition...){
        $event->setCancelled(true);
    }
}
```

`\alvin0319\PlayerTrade\event\TradeEndEvent`: Called when player ends trade (You cannot cancel this event)
```php
public function onTradeEnd(\alvin0319\PlayerTrade\event\TradeEndEvent $event) : void{
    $sender = $event->getSender();
    $receiver = $event->getReceiver();
    switch($event->getReason()){
        case \alvin0319\PlayerTrade\event\TradeEndEvent::REASON_RECEIVER_CANCEL:
            // do something
            break;
        default:
            // do something
            break;
    }
}
```