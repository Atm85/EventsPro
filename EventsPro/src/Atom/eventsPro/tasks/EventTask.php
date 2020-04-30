<?php
declare(strict_types=1);

namespace Atom\eventsPro\tasks;

use Atom\eventsPro\events\Event;
use Atom\eventsPro\EventsPro;
use Atom\eventsPro\traits\EventTrait;
use Atom\eventsPro\traits\GappleTrait;
use Atom\eventsPro\traits\PlayerSelectorTrait;
use Atom\eventsPro\traits\SumoTrait;
use pocketmine\block\StillLava;
use pocketmine\block\StillWater;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat as c;

class EventTask extends Task {

    use EventTrait;
    use SumoTrait;
    use GappleTrait;
    use PlayerSelectorTrait;

    /** @var EventsPro  */
    private $plugin;

    /** @var Event */
    private $event;

    public function __construct(EventsPro $plugin, Event $event) {
        $this->plugin = $plugin;
        $this->event = $event;
    }

    public function onRun(int $currentTick) {

        // on event startup
        $this->init($this->plugin, $this->event);

        switch ($this->event->getType()) {
            case "sumo1v1":

                // select players to be up next to fight
                $this->select1v1Queue($this->event);

                // if player is next up in queue, send informational tip ;)
                $this->sendTipToNextupQueue($this->event);

                // if no players are in arena, set players in arena from nextUp list
                if (empty($this->event->getPlaying())) {
                    foreach ($this->event->getNextUp()["no-team"] as $playerName) {
                        $player = $this->plugin->getServer()->getPlayerExact($playerName);
                        if ($player != null) {
                            $this->sendToSumoEvent($this->event, $player);
                        } else {
                            $this->event->kickPlayer($playerName);
                        }
                    }
                }

                // if the required amount of players are in arena, start cooldown before fight
                // this is also known as the "grace period"
                $this->initGracePeriod($this->event);

                // when the "grace period" ends, the previous timer which was used will be set to -1
                if ($this->event->startCoolDown == -1) {

                    // listen for when a player is knocked off and lands in water or lava
                    foreach ($this->event->getPlaying() as $playerName) {
                        $player = $this->plugin->getServer()->getPlayerExact($playerName);
                        if ($player != null) {
                            $block = $player->getLevel()->getBlockAt((int)$player->getX(), (int)$player->getY(), (int)$player->getZ());
                            if ($block instanceof StillWater || $block instanceof StillLava) {
                                $this->event->unsetFromPlaying($playerName);
                                $this->event->setEliminable($playerName);
                                $player->removeAllEffects();
                                $player->addEffect(new EffectInstance(Effect::getEffect(Effect::REGENERATION), 60, 10, false));
                                $player->teleport($this->event->getSpectatorPost());
                                foreach ($this->event->getPlayers() as $inGame) {
                                    $plInGame = $this->plugin->getServer()->getPlayerExact($inGame);
                                    if ($plInGame !== null) {
                                        $plInGame->sendMessage(c::YELLOW.$playerName.c::BLUE." Was eliminated!");
                                    }
                                }
                            }
                        } else {
                            $this->event->kickPlayer($playerName);
                        }
                    }

                    // if player has met the conditions in which to loose the match, ^ unset that player first
                    // so here we can set the only remaining player as a victor ;)
                    $this->calculateEndRound($this->plugin, $this->event);

                    // if no players could be selected to be up next, end the game and choose winner
                    $this->calculateEndGame($this->plugin, $this->event, $this);
                }

                break;
            case "gapple1v1":

                // select players to be up next to fight
                $this->select1v1Queue($this->event);

                // if player is next up in queue, send informational tip ;)
                $this->sendTipToNextupQueue($this->event);

                // if no players are in arena, set players in arena from nextUp list
                if (empty($this->event->getPlaying())) {
                    foreach ($this->event->getNextUp()["no-team"] as $playerName) {
                        $player = $this->plugin->getServer()->getPlayerExact($playerName);
                        if ($player != null) {
                            $this->sendToGappleEvent($this->event, $player);
                        } else {
                            $this->event->kickPlayer($playerName);
                        }
                    }
                }

                // if the required amount of players are in arena, start cooldown before fight
                // this is also known as the "grace period"
                $this->initGracePeriod($this->event);

                // when the "grace period" ends, the previous timer which was used will be set to -1
                // THIS SECTION IS HANDLED BY "EntityDamageByEntityEvent" EVENT
                // ------------------------------------------------------------
                if ($this->event->startCoolDown == -1) {

                    // if player has met the conditions in which to loose the match, ^ unset that player first
                    // so here we can set the only remaining player as a victor ;)
                    $this->calculateEndRound($this->plugin, $this->event);

                    // if no players could be selected to be up next, end the game and choose winner
                    $this->calculateEndGame($this->plugin, $this->event, $this);
                }

                break;
        }

    }

}
