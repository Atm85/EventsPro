<?php
declare(strict_types=1);

namespace Atom\eventsPro\commands;

use Atom\eventsPro\EventsPro;
use atom\gui\GUI;
use atom\gui\type\CustomGui;
use atom\gui\type\SimpleGui;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\utils\TextFormat as c;

class EventCommand extends PluginCommand {

    /** @var EventsPro */
    private $plugin;

    public function __construct(string $name, EventsPro $plugin) {
        parent::__construct($name, $plugin);
        $this->setDescription("join or create events");
        $this->setPermission("events.create");
        $this->setAliases(["ev"]);
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void {

        if (!$sender instanceof Player) {
            $sender->sendMessage(c::RED."You can only run commands in-game");
            return;
        }

        if (!isset($args[0])) {
            $sender->sendMessage(c::GOLD."Usage: /ev create - opens GUI");
            $sender->sendMessage(c::GOLD."Usage: /ev join [event] - join an avalible event");
            return;
        }

        switch ($args[0]) {
            case "addtext":
                if (!$sender->isOp()) {
                    $sender->sendMessage(c::RED."You do not have permission to use this sub-command");
                    return;
                }

                $types = ["sumo1v1", "gapple1v1"];
                if(!isset($args[1])) {
                    $sender->sendMessage(c::GOLD."Usage: /ev $args[0] [type]");
                    $sender->sendMessage(c::GOLD."Available types: ".implode(", ", $types));
                    return;
                }

                if (!in_array($args[1], $types)) {
                    $sender->sendMessage(c::GOLD."Usage: /ev $args[0] [type]");
                    $sender->sendMessage(c::GOLD."Available types: ".implode(", ", $types));
                    return;
                }

                if (isset($this->plugin->ftps[$args[1]][$sender->getLevel()->getFolderName()])) {
                    $sender->sendMessage(c::GOLD."$args[1] is already set, delete it first before setting again.");
                    return;
                }

                $level = $sender->getLevel()->getFolderName();
                $x = round($sender->getX(), 1);
                $y = round($sender->getY(), 1) + 1.7;
                $z = round($sender->getZ(), 1);
                $position = new Position($x, $y, $z, $sender->getLevel());
                $this->plugin->provider->saveTexts($args[1], "$level:$x:$y:$z");
                switch ($args[1]) {
                    case "sumo1v1":
                        $this->plugin->addTextParticle($position, $sender, $args[1], $this->plugin->provider->getSumoLeaders());
                        break;
                    case "gapple1v1":
                        $this->plugin->addTextParticle($position, $sender, $args[1], $this->plugin->provider->getGappleLeaders());
                }
                $sender->sendMessage(c::RED.$args[1].c::YELLOW." Leaderboard created!");
                return;
            case "setarena":
            case "addarena":
                if (!$sender->hasPermission("events.setarena") || !$sender->isOp()) {
                    $sender->sendMessage(c::RED."You do not have permission to use this sub-command");
                    return;
                }

                $types = ["sumo1v1", "gapple1v1"];
                if(!isset($args[1])) {
                    $sender->sendMessage(c::GOLD."Usage: /ev $args[0] [type]");
                    $sender->sendMessage(c::GOLD."Available types: ".implode(", ", $types));
                    return;
                }

                if (!in_array($args[1], $types)) {
                    $sender->sendMessage(c::GOLD."Available types: ".implode(", ", $types));
                    return;
                }

                $this->plugin->provider->setArena($sender->getPosition(), $args[1]);
                $sender->sendMessage(c::GREEN."You can now use level ".c::WHITE.$sender->getLevel()->getFolderName().c::GREEN." for $args[1] events!");
                return;
            case "setspectator":
            case "setsp":
                $arenas = [];
                foreach ($this->plugin->provider->getArenas() as $arena => $location) {
                    array_push($arenas, $arena);
                }

                if (!$sender->hasPermission("events.setarena") || !$sender->isOp()) {
                    $sender->sendMessage(c::RED."You do not have permission to use this sub-command");
                    return;
                }

                $level = $sender->getLevel()->getFolderName();
                if (!$this->plugin->provider->arenaExists($level)) {
                    $sender->sendMessage(c::GOLD."You are not in an arena, try creating one first");
                    $sender->sendMessage(c::GOLD."Available arenas: ".implode(", ", $arenas));
                    return;
                }

                $this->plugin->provider->setSpectatorRoom($sender->getPosition(), $level);
                $sender->sendMessage(c::GREEN."You set the spectator room for ".c::WHITE.$this->plugin->provider->getArenaType($level).c::GREEN." arena");
                return;
            case "delarena":
                if (!$sender->hasPermission("events.delarena") || !$sender->isOp()) {
                    $sender->sendMessage(c::RED."You do not have permission to use this sub-command");
                    return;
                }

                $delGUI = new SimpleGui();
                $delGUI->setTitle("Delete arena?");
                foreach ($this->plugin->provider->getArenas() as $arena => $location) {
                    $delGUI->addButton($arena);
                }

                $delGUI->setAction(function (Player $player, $data) {
                    $this->plugin->provider->deleteArena($data);
                    $player->sendMessage(c::GREEN."You deleted ".c::WHITE.$data.c::GREEN." from arenas!");
                });

                GUI::register("delEventGUI", $delGUI);
                GUI::send($sender, "delEventGUI");
                return;
            case "create":
                if (!$sender->hasPermission("events.create") || !$sender->isOp()) {
                    $sender->sendMessage(c::RED."You do not have permission to use this sub-command");
                    return;
                }

                if ($this->plugin->provider->getEventByPlayer($sender->getName()) != null) {
                    $sender->sendMessage(c::GOLD."You must leave your current event before joining another one");
                    return;
                }

                $startingTime = [1, 5, 10];
                $maxPlayerCount = [2, 4, 6, 8, 10, 12, 24];
                $arenas = ["random"];
                foreach ($this->plugin->provider->getArenas() as $arena => $position) {
                    array_push($arenas, $arena);
                }

                $eventGUI = new CustomGui();
                $eventGUI->setTitle("Create event");
                $eventGUI->addDropdown("Select arena", $arenas);
                $eventGUI->addStepSlider("Select max player count", $maxPlayerCount);
                $eventGUI->addStepSlider("Select starting time - in minutes", $startingTime);
                $eventGUI->setAction(function (Player $player, $data) {
                    /**
                     * $data[0] = arena
                     * $data[1] = player count
                     * $data[2] = starting time
                     */
                    $arena = $data[0];
                    $playerCount = $data[1];
                    $startingTime = $data[2];
                    if ($arena == "random") {
                        $arena = array_rand($this->plugin->provider->getArenas());
                    }

                    $this->plugin->provider->saveInventory($player->getName(), $player->getInventory()->getContents(true), $player->getArmorInventory()->getContents(true));
                    $player->getInventory()->clearAll();
                    $player->getArmorInventory()->clearAll();
                    $type = $this->plugin->provider->getArenaType($arena);
                    $this->plugin->getServer()->loadLevel($arena);
                    $level = $this->plugin->getServer()->getLevelByName($arena);
                    $x = $this->plugin->provider->getArenaSpectatorPost($arena)[0];
                    $y = $this->plugin->provider->getArenaSpectatorPost($arena)[1];
                    $z = $this->plugin->provider->getArenaSpectatorPost($arena)[2];
                    $position = new Position($x, $y, $z, $level);
                    $player->teleport($position);
                    $event = $this->plugin->provider->registerEvent($player->getName(), $type, $position, $startingTime, $playerCount);
                    $event->addPlayer($player->getName());
                    $this->plugin->getServer()->broadcastMessage(c::BOLD.c::WHITE.$player->getName().c::BLUE." started a " .c::GREEN.$type.c::BLUE." event, type".c::WHITE." /ev join ".$player->getName().c::BLUE." to join queue");
                });

                GUI::register("createEventGUI", $eventGUI);
                GUI::send($sender, "createEventGUI");
                return;
            case "join":

                $types = ["sumo1v1", "gapple1v1"];
                if ($this->plugin->provider->getEventByPlayer($sender->getName()) != null) {
                    $sender->sendMessage(c::GOLD."You must leave your current event before joining another one");
                    return;
                }

                if (!isset($args[1])) {
                    if (empty($this->plugin->provider->getActiveEvents())) {
                        if (!$sender->hasPermission("events.create") || !$sender->isOp()) {
                            $sender->sendMessage(c::GOLD."There are no active events");
                            return;
                        }

                        $this->plugin->provider->saveInventory($sender->getName(), $sender->getInventory()->getContents(true), $sender->getArmorInventory()->getContents(true));
                        $sender->getInventory()->clearAll();
                        $sender->getArmorInventory()->clearAll();
                        $world = array_rand($this->plugin->provider->getArenas());
                        $type = $this->plugin->provider->getArenaType($world);
                        $this->plugin->getServer()->loadLevel($world);
                        $level = $this->plugin->getServer()->getLevelByName($world);
                        $x1 = $this->plugin->provider->getArenaSpectatorPost($world)[0];
                        $y2 = $this->plugin->provider->getArenaSpectatorPost($world)[1];
                        $z2 = $this->plugin->provider->getArenaSpectatorPost($world)[2];
                        $pos = new Position($x1, $y2, $z2, $level);
                        $event = $this->plugin->provider->registerEvent(uniqid("event_"), $type, $pos);
                        $sender->teleport($event->getSpectatorPost());
                        $event->addPlayer($sender->getName());
                        $this->plugin->getServer()->broadcastMessage(c::BOLD.c::BLUE."A " .c::GREEN.$type.c::BLUE." event has started, type".c::WHITE." /ev join ".c::BLUE."to join queue");

                    } else {
                        $event = $this->plugin->provider->getEventByPlayerCount();
                        $this->plugin->provider->saveInventory($sender->getName(), $sender->getInventory()->getContents(true), $sender->getArmorInventory()->getContents(true));
                        $sender->getInventory()->clearAll();
                        $sender->getArmorInventory()->clearAll();
                        $sender->teleport($event->getSpectatorPost());
                        $event->addPlayer($sender->getName());
                        foreach ($event->getPlayers() as $playerName) {
                            $this->plugin->getServer()->getPlayerExact($playerName)->sendMessage(c::BOLD.c::YELLOW.$sender->getName().c::WHITE." joined the event - ".c::BLUE.count($event->getPlayers()).c::GREEN."/".c::BLUE.$event->getMaxPlayers());
                        }
                    }
                    return;
                }

                $event = null;
                if (in_array($args[1], $types)) {
                    $event = $this->plugin->provider->getEventByType($args[1]);
                } else {
                    $event = $this->plugin->provider->getEventByPlayer($args[1]);
                }

                if ($event == null) {
                    $sender->sendMessage(c::GOLD."Could not find an active event with that type or player");
                    return;
                }

                $this->plugin->provider->saveInventory($sender->getName(), $sender->getInventory()->getContents(true), $sender->getArmorInventory()->getContents(true));
                $sender->getInventory()->clearAll();
                $sender->getArmorInventory()->clearAll();
                $sender->teleport($event->getSpectatorPost());
                $event->addPlayer($sender->getName());
                foreach ($event->getPlayers() as $playerName) {
                    $this->plugin->getServer()->getPlayerExact($playerName)->sendMessage(c::BOLD.c::YELLOW.$sender->getName().c::WHITE." joined the event - ".c::BLUE.count($event->getPlayers()).c::GREEN."/".c::BLUE.$event->getMaxPlayers());
                }

                return;
            case "leave":
                $event = $this->plugin->provider->getEventByPlayer($sender->getName());
                if ($event == null) {
                    $sender->sendMessage(c::GOLD."You are not in any events");
                    return;
                }

                $this->plugin->provider->restoreInventory($sender->getName());
                $sender->removeAllEffects();
                $event->kickPlayer($sender->getName());
                $sender->sendMessage(c::BOLD.c::GREEN."You left the event");
                $sender->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
                return;
            case "start":
                $event = $this->plugin->provider->getEventByPlayer($sender->getName());
                if ($event == null) {
                    return;
                }

                $event->forceStart();
                return;
        }

    }
}
