<?php
declare(strict_types=1);

namespace Atom\eventsPro\provider;

use Atom\eventsPro\events\Event;
use Atom\eventsPro\events\GappleEvent;
use Atom\eventsPro\events\SumoEvent;
use Atom\eventsPro\EventsPro;
use pocketmine\level\Position;
use pocketmine\utils\Config;

abstract class Provider implements IProvider {

    /** @var EventsPro */
    protected $plugin;

    /** @var Config */
    protected $arenas;

    /** @var Config */
    protected $leaders;

    /** @var Config */
    protected $s_leaders;

    /** @var Config */
    protected $g_leaders;

    /** @var Config */
    protected $leaderBoards;

    /** @var array */
    protected $activeEvents = [];

    /** @var array */
    protected $inventory = [];

    /** @var array */
    protected $armorInv = [];

    public function __construct(EventsPro $plugin) {
        $this->plugin = $plugin;
    }

    // MANAGE ARENAS
    public function arenaExists(string $name) : bool {
        return isset($this->getArenas()[$name]) ? true : false;
    }

    public function getArenas() : array {
        return $this->arenas->getAll();
    }

    public function getArenaPosition(string $name) : array {
        $data = $this->arenas->getNested($name)["arena"];
        return [$data["xx"], $data["yy"], $data["zz"]];
    }

    public function getArenaSpectatorPost(string $name) : array {
        $data = $this->arenas->getNested($name)["spectatorPost"];
        return [$data["xx"], $data["yy"], $data["zz"]];
    }

    public function getArenaType(string $name) : string {
        return $this->arenas->getNested($name)["type"];
    }

    public function getArenaByType(string $type) : string {
        return array_search($type, $this->getArenas());
    }

    public function setArena(Position $pos, string $type) : void {
        $data = ["type"=>$type, "arena"=>["xx"=>$pos->getX(), "yy"=>$pos->getY(), "zz"=>$pos->getZ()]];
        $this->arenas->setNested($pos->getLevel()->getFolderName(), $data);
        $this->arenas->save();
    }

    public function setSpectatorRoom(Position $pos, string $name) : void {
        $type = $this->getArenaType($name);
        $data = [
            "type"=>$type,
            "arena"=>["xx"=>$this->getArenaPosition($name)[0], "yy"=>$this->getArenaPosition($name)[1], "zz"=>$this->getArenaPosition($name)[2]],
            "spectatorPost"=>["xx"=>$pos->getX(), "yy"=>$pos->getY(), "zz"=>$pos->getZ()]];
        $this->arenas->setNested($pos->getLevel()->getFolderName(), $data);
        $this->arenas->save();
    }

    public function deleteArena(string $name) : void {
        $locations = $this->getArenas();
        if (isset($locations[$name])) {
            unset($locations[$name]);
            $this->arenas->setAll($locations);
            $this->arenas->save();
        }
    }

    // MANAGE EVENTS
    public function eventExists(string $eventId) : bool {
        return isset($this->activeEvents[$eventId]) ? true : false;
    }

    /**
     * @return Event[]
     */
    public function getActiveEvents() : array {
        return $this->activeEvents;
    }

    /**
     * Returns Null if no event could be registered
     * @param string $eventId
     * @param string $type
     * @param Position $position
     * @param int $startingTime
     * @param int $maxPlayers
     * @return Event|null
     */
    public function registerEvent(string $eventId, string $type, Position $position, int $startingTime = 5, int $maxPlayers = 12) : ?Event {
        $event = null;
        switch ($type) {
            case "sumo1v1":
                $event = new SumoEvent($this->plugin, $eventId, $position, $type, $startingTime, $maxPlayers);
                $this->activeEvents[$eventId] = $event;
                break;
            case "gapple1v1":
                $event = new GappleEvent($this->plugin, $eventId, $position, $type, $startingTime, $maxPlayers);
                $this->activeEvents[$eventId] = $event;
                break;

        }
        return $event;
    }

    /**
     * Unregisters an event by id
     * @param string $eventId
     */
    public function unregisterEvent(string $eventId) : void {
        if ($this->eventExists($eventId)) {
            unset($this->activeEvents[$eventId]);
        }
    }

    /**
     * Gets an event by its unique id
     * @param string $eventId
     * @return Event|null
     */
    public function getEventById (string $eventId) : ?Event {
        $event = null;
        if ($this->eventExists($eventId)) {
            $event = $this->activeEvents[$eventId];
        }
        return $event;
    }

    /**
     * Gets an event by player who may be currently in an event
     * @param string $name
     * @return Event|null
     */
    public function getEventByPlayer(string $name) : ?Event {
        $event = null;
        foreach ($this->getActiveEvents() as $activeEvent) {
            if ($activeEvent->isInEvent($name)) {
                $event = $activeEvent;
            }
        }
        return $event;
    }

    public function getEventByPlayerCount() : ?Event {
        $events = [];
        foreach ($this->getActiveEvents() as $activeEvent) {
            if ($activeEvent->isJoinable()) $events[$activeEvent->getId()] = count($activeEvent->getPlayers());
        }

        arsort($events, SORT_NUMERIC);
        return $this->getEventById(array_key_first($events));
    }

    public function getEventByType(string $type) : ?Event {
        $events = [];
        foreach ($this->getActiveEvents() as $activeEvent) {
            if ($activeEvent->getType() == $type) {
                if ($activeEvent->isJoinable()) $events[$activeEvent->getId()] = count($activeEvent->getPlayers());
            }
        }

        arsort($events, SORT_NUMERIC);
        return $this->getEventById(array_key_first($events));
    }

    // MANAGES PLAYER DATA SAVING
    public function saveInventory(string $name, array $contents, array $armor) : void {
        $this->inventory[$name] = $contents;
        $this->armorInv[$name] = $armor;
    }

    public function restoreInventory(string $name) : void {
        if (isset($this->inventory[$name]) && isset($this->armorInv[$name])) {
            $this->plugin->getServer()->getPlayerExact($name)->getInventory()->setContents($this->inventory[$name]);
            $this->plugin->getServer()->getPlayerExact($name)->getArmorInventory()->setContents($this->armorInv[$name]);
        }
    }

    public function saveScore(string $type, array $tally, string $victor) : void {
        switch ($type) {
            case "sumo1v1":
                $leaders = $this->s_leaders->getAll();
                foreach ($tally as $player => $score) {

                    if (!isset($leaders[$player])) {
                        $this->s_leaders->setNested($player, ["wins"=>0, "kills"=>0]);
                    }

                    if ($victor == $player) {
                        $winCount = $this->s_leaders->getNested($player.".wins")+1;
                        $killCount = $this->s_leaders->getNested($player.".kills")+1;
                        $this->s_leaders->setNested($player, ["wins"=>$winCount, "kills"=>$killCount]);
                    } else {
                        $winCount = $this->s_leaders->getNested($player.".wins");
                        $killCount = $this->s_leaders->getNested($player.".kills")+1;
                        $this->s_leaders->setNested($player, ["wins"=>$winCount, "kills"=>$killCount]);
                    }

                }
                $this->s_leaders->save();
                break;
            case "gapple1v1":
                $leaders = $this->g_leaders->getAll();
                foreach ($tally as $player => $score) {

                    if (!isset($leaders[$player])) {
                        $this->g_leaders->setNested($player, ["wins"=>0, "kills"=>0]);
                    }

                    if ($victor == $player) {
                        $winCount = $this->g_leaders->getNested($player.".wins")+1;
                        $killCount = $this->g_leaders->getNested($player.".kills")+1;
                        $this->g_leaders->setNested($player, ["wins"=>$winCount, "kills"=>$killCount]);
                    } else {
                        $winCount = $this->g_leaders->getNested($player.".wins");
                        $killCount = $this->g_leaders->getNested($player.".kills")+1;
                        $this->g_leaders->setNested($player, ["wins"=>$winCount, "kills"=>$killCount]);
                    }

                }
                $this->g_leaders->save();
                break;
        }
    }

    public function saveTexts(string $type, string $data) {
        $this->leaderBoards->setNested($type, $data);
        $this->leaderBoards->save();
    }

    public function getTexts() : array {
        return $this->leaderBoards->getAll();
    }

    public function getLeaders(string $type) : array {
        switch ($type) {
            case "sumo1v1";
                return $this->getSumoLeaders();
            case "gapple1v1":
                return $this->getGappleLeaders();
        }
        return [];
    }

    public function getSumoLeaders() : array {
        return $this->s_leaders->getAll();
    }

    public function getGappleLeaders() : array {
        return $this->g_leaders->getAll();
    }

}
