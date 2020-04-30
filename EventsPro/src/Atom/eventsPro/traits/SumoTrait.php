<?php

namespace Atom\eventsPro\traits;

use Atom\eventsPro\events\Event;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\Player;

trait SumoTrait {

    public function sendToSumoEvent(Event $event, Player $player) {
        $event->setPlaying($player->getName());
        $event->unsetSpectating($player->getName());
        $event->unsetFromNextUp($player->getName());
        $player->teleport($event->getArena());
        $regen = new EffectInstance(Effect::getEffect(Effect::REGENERATION), 1000000, 10, false);
        $saturation = new EffectInstance(Effect::getEffect(Effect::SATURATION), 1000000, 10, false);
        $player->addEffect($regen);
        $player->addEffect($saturation);
    }

}
