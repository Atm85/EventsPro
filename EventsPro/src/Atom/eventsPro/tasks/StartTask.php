<?php
declare(strict_types=1);

namespace Atom\eventsPro\tasks;

use Atom\eventsPro\events\Event;
use Atom\eventsPro\EventsPro;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat as c;

class StartTask extends Task {

    /** @var Event */
    private $event;

    /** @var EventsPro */
    private $plugin;

    /**
     * StartTask constructor.
     * @param EventsPro $plugin
     * @param Event $event
     */
    public function __construct(EventsPro $plugin, Event $event) {
        $this->event = $event;
        $this->plugin = $plugin;
    }

    public function onRun(int $currentTick) {

        $minutes = $this->calculateTime($this->event->startingTime)[0];
        $seconds = $this->calculateTime($this->event->startingTime)[1];

        if (empty($this->event->getPlayers())) {
            $this->plugin->provider->unregisterEvent($this->event->getId());
            $this->plugin->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        foreach ($this->event->getPlayers() as $playerName) {

            $player = $this->plugin->getServer()->getPlayerExact($playerName);

            if ($player == null) {
                $this->event->kickPlayer($playerName);
            }

            if ($this->event->startingTime < 0 && count($this->event->getPlayers()) <= 1) {
                $player->sendMessage(c::BOLD.c::WHITE."Could not start event, not enough players");
                $player->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
                $this->plugin->getScheduler()->cancelTask($this->getTaskId());
                return;
            }

            if (count($this->event->getPlayers()) < 2) {
                $player->sendTip(c::BOLD.c::YELLOW."Waiting for more players...");
            } else {
                $player->sendTip(c::BOLD.c::GREEN.$this->event->getType().c::BLUE." starting in - ".c::WHITE.$minutes.c::GREEN.":".c::WHITE.$seconds);
            }

        }

        // start event if arena is full
        if ((count($this->event->getPlayers()) == $this->event->getMaxPlayers()) && ($this->event->startingTime > 10)) {
            $this->event->startingTime = 10;
        }

        if ($this->event->startingTime < 0) {
            foreach ($this->event->getPlayers() as $playerName) {
                $player = $this->plugin->getServer()->getPlayerExact($playerName);
                $player->sendMessage(c::BOLD.c::WHITE.$this->event->getType().c::GREEN." has started!");
                $player->teleport($this->event->getSpectatorPost());
            }
            $this->plugin->getScheduler()->scheduleRepeatingTask(new EventTask($this->plugin, $this->event), 20);
            $this->plugin->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        if (count($this->event->getPlayers()) >= 2) {
            $this->event->startingTime--;
        }

    }

    private function calculateTime(int $seconds) : array {
        return [sprintf("%02d", ($seconds / 60) % 60), sprintf("%02d", $seconds % 60)];
    }

}
