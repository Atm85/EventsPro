<?php

namespace Atom\eventsPro\traits;

use Atom\eventsPro\events\Event;
use Atom\eventsPro\EventsPro;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\level\Location;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\TextFormat as c;

trait EventTrait {

    /**
     * initializes the event
     * @param EventsPro $plugin
     * @param Event $event
     */
    public function init(EventsPro $plugin, Event $event) {

        // if a player becomes non-existent, simply remove their name to prevent future errors in the task
        foreach ($event->getPlayers() as $player) {
            if ($plugin->getServer()->getPlayerExact($player) == null) {
                $event->kickPlayer($player);
            }
        }

        // when new event starts, send all players to spectator area
        if (!$event->isActive()) {
            if (empty($event->getSpectating())) {
                foreach ($event->getPlayers() as $playerName) {
                    $event->setSpectating($playerName);
                }
            }
            $event->setActive(true);
            $event->setJoinable(false);
        }

    }

    /**
     * sends a tip to every player in the Nextup Queue
     * @param Event $event
     */
    public function sendTipToNextupQueue(Event $event) {
        foreach ($event->getNextUp()["no-team"] as $playerName) {
            $player = Server::getInstance()->getPlayerExact($playerName);
            if ($player != null) {
                $player->sendTip(c::BOLD.c::YELLOW."You are up next!");
            } else {
                $event->kickPlayer($playerName);
            }
        }
    }

    public function initGracePeriod(Event $event) {

        // if the required amount of players are in arena, start cooldown before fight
        // this is also known as the "grace period"
        if ($this->event->startCoolDown >= 0) {
            if (count($event->getPlaying()) == 2) {
                foreach ($event->getPlaying() as $playerName) {
                    $player = Server::getInstance()->getPlayerExact($playerName);
                    if ($player != null) {
                        if ($this->event->startCoolDown > 0) {
                            $player->addTitle(c::WHITE." - ".$this->event->startCoolDown." - ");
                        } else {
                            $player->addTitle(c::WHITE." - ".c::RED."FIGHT".c::WHITE." - ", "", 5, 2, 2);
                        }
                    } else {
                        $event->kickPlayer($playerName);
                    }
                }
                $this->event->startCoolDown--;
            } else {
                $this->event->startCoolDown = 5;
            }
        }
    }

    public function calculateEndRound(EventsPro $plugin, Event $event) {
        // if player has met the conditions in which to loose the match, ^ unset that player first
        // so here we can set the only remaining player as a victor ;)
        if (count($event->getPlaying()) == 1) {
            $reIndexed = array_values($event->getPlaying());
            $player = $plugin->getServer()->getPlayerExact($reIndexed[0]);
            if ($player != null) {
                $event->unsetFromPlaying($player->getName());
                $event->setSpectating($player->getName());
                $event->addToTally($player->getName());
                $player->setMaxHealth($player->getMaxHealth());
                $player->setFood($player->getMaxFood());
                $player->getArmorInventory()->clearAll();
                $player->getInventory()->clearAll();
                $player->removeAllEffects();
                $player->addEffect(new EffectInstance(Effect::getEffect(Effect::REGENERATION), 60, 10, false));
                $player->teleport($event->getSpectatorPost());
            } else {
                $event->kickPlayer($reIndexed[0]);
            }

            // reset cooldown
            $this->event->startCoolDown = 5;
        }
    }

    public function calculateEndGame(EventsPro $plugin, Event $event, Task $task) {
        // if no players could be selected to be up next, end the game and choose winner
        if (count($event->getSpectating()) <= 1 && empty($event->getPlaying())) {
            $victor = array_search(max($event->getTally()), $event->getTally());
            Server::getInstance()->getPlayerExact($victor)->addTitle(c::YELLOW.c::OBFUSCATED."iii".c::RESET.c::GREEN." Victory ".c::YELLOW.c::OBFUSCATED."iii");
            Server::getInstance()->broadcastMessage(c::BOLD.c::YELLOW."$victor".c::BLUE." Won a ".c::GREEN.$event->getType().c::BLUE." event!");
            $this->plugin->provider->saveScore($event->getType(), $event->getTally(), $victor);
            $this->plugin->provider->unregisterEvent($event->getId());
            foreach ($event->getPlayers() as $playerName) {
                $player = Server::getInstance()->getPlayerExact($playerName);
                if ($player != null) {
                    $this->plugin->provider->restoreInventory($playerName);
                    $player->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
                    $player->removeAllEffects();
                }
            }
            $plugin->getScheduler()->cancelTask($task->getTaskId());
            return;
        }
    }
}
