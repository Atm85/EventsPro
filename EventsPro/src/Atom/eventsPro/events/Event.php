<?php
declare(strict_types=1);

namespace Atom\eventsPro\events;

use Atom\eventsPro\EventsPro;
use Atom\eventsPro\tasks\StartTask;
use pocketmine\level\Position;

abstract class Event implements IEvent {

    /** @var EventsPro*/
    protected $plugin;

    /** @var string */
    protected $type;

    /** @var string */
    protected $eventId;

    /** @var Position */
    protected $arena;

    /** @var Position */
    protected $spectatorPost;

    /** @var int */
    protected $maxPlayerCount;

    /** @var array */
    protected $players = [];

    /** @var array  */
    protected $playing = [];

    /** @var array  */
    protected $spectating = [];

    /** @var array */
    protected $eliminable = [];

    /** @var array  */
    protected $tally = [];

    /** @var array */
    protected $nextUp = ["no-team"=>[], "blue-team"=>[], "red-team"=>[]];

    /** @var bool  */
    protected $active = false;

    /** @var bool  */
    protected $joinable = true;

    /** @var int  */
    public $startCoolDown = 5;

    /** @var int */
    public $startingTime;

    public function __construct(EventsPro $plugin, string $eventId, Position $position, string $type, int $startingTime, int $maxPlayerCount = 12) {
        $arena = $plugin->provider->getArenaPosition($position->getLevel()->getFolderName());
        $this->plugin = $plugin;
        $this->eventId = $eventId;
        $this->arena = new Position($arena[0], $arena[1], $arena[2], $position->getLevel());
        $this->spectatorPost = $position;
        $this->type = $type;
        $this->startingTime = ($startingTime * 60);
        $this->maxPlayerCount = $maxPlayerCount;
        $this->start();
    }

    protected function start() : void {
        $this->plugin->getScheduler()->scheduleRepeatingTask(new StartTask($this->plugin, $this), 20);
    }

    public function forceStart() : void {
        $this->startingTime = 10;
    }

    public function getType() : string {
        return $this->type;
    }

    public function getId() : string {
        return $this->eventId;
    }

    public function getStartingTime() : int {
        return $this->startingTime;
    }

    public function getMaxPlayers() : int {
        return $this->maxPlayerCount;
    }

    public function getArena() : Position {
        return $this->arena;
    }

    public function getSpectatorPost() : Position {
        return $this->spectatorPost;
    }

    public function getPlayers() : array {
        return $this->players;
    }

    public function getPlaying() : array {
        return $this->playing;
    }

    public function getSpectating() : array {
        return $this->spectating;
    }

    public function getNextUp() : array {
        return $this->nextUp;
    }

    public function getTally() : array {
        return $this->tally;
    }

    public function addPlayer(string $name) : void {
        array_push($this->players, $name);
    }

    public function setSpectating(string $name) : void {
        array_push($this->spectating, $name);
    }

    public function setPlaying(string $name) : void {
        array_push($this->playing, $name);
    }

    public function setEliminable(string $name) : void {
        array_push($this->eliminable, $name);
    }

    public function setNextUp(string $name) : void {
        array_push($this->nextUp["no-team"], $name);
    }

    public function addToTally(string $playerName) : void {
        if (isset($this->tally[$playerName])) {
            $this->tally[$playerName] = $this->tally[$playerName] += 1;
        } else {
            $this->tally[$playerName] = 1;
        }
    }

    public function unsetSpectating(string $name) : void {
        if (in_array($name, $this->spectating)) {
            $key = array_search($name, $this->spectating);
            unset($this->spectating[$key]);
        }
    }

    public function unsetFromNextUp(string $name) : void {
        if (in_array($name, $this->nextUp["no-team"])) {
            $key = array_search($name, $this->nextUp["no-team"]);
            unset($this->nextUp["no-team"][$key]);
        }
    }

    public function unsetFromPlaying(string $name) : void {
        if (in_array($name, $this->playing)) {
            $key = array_search($name, $this->playing);
            unset($this->playing[$key]);
        }
    }

    public function clearNextUp() : void {
        $this->nextUp["no-team"] = [];
    }

    public function kickPlayer(string $name) : void {
        if (in_array($name, $this->players)) {
            $key = array_search($name, $this->players);
            unset($this->players[$key]);
        }
        $this->unsetSpectating($name);
        $this->unsetFromNextUp($name);
        $this->unsetFromPlaying($name);
    }

    public function setActive(bool $state) : void {
        $this->active = $state;
    }

    public function setJoinable(bool $state) : void {
        $this->joinable = $state;
    }

    public function isInEvent(string $name) : bool {
        return in_array($name, $this->players) ? true : false;
    }

    public function isPlaying(string $name) : bool {
        return in_array($name, $this->playing) ? true : false;
    }

    public function isActive() : bool {
        return $this->active;
    }

    public function isJoinable() : bool {
        return $this->joinable;
    }

}
