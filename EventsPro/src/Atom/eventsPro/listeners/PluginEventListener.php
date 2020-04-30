<?php
declare(strict_types=1);

namespace Atom\eventsPro\listeners;

use Atom\eventsPro\EventsPro;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\utils\TextFormat as c;

class PluginEventListener implements Listener {

    /** @var EventsPro */
    private $plugin;

    public function __construct(EventsPro $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Render leaderboard floating texts
     * @param PlayerJoinEvent $event
     */
    public function onJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        foreach ($this->plugin->provider->getTexts() as $type => $levelData) {
            $data = explode(":", $levelData);
            $level = $data[0];
            $x = $data[1];
            $y = $data[2];
            $z = $data[3];
            $this->plugin->getServer()->loadLevel($level);
            $pos = new Position($x, $y, $z, $this->plugin->getServer()->getLevelByName($level));
            $this->plugin->addTextParticle($pos, $player, $type, $this->plugin->provider->getLeaders($type));
            if (!isset($this->plugin->ftps[$type][$player->getLevel()->getFolderName()])) {
                $ftp = $this->plugin->ftps[$type][$level];
                $ftp->setInvisible(true);
                $player->getLevel()->addParticle($ftp, [$player]);
            } else {
                $ftp = $this->plugin->ftps[$type][$level];
                $ftp->setInvisible(false);
                $player->getLevel()->addParticle($ftp, [$player]);
            }
        }
    }

    /**
     * Force player to leave an active event of they logout
     * @param PlayerQuitEvent $event
     */
    public function onQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
        $activeEvent = $this->plugin->provider->getEventByPlayer($player->getName());
        if ($activeEvent == null) {
            return;
        }

        if ($activeEvent->isInEvent($player->getName())) {
            $this->plugin->provider->restoreInventory($player->getName());
            $activeEvent->kickPlayer($player->getName());
        }
    }

    /**
     * Force player to leave an active event of they change worlds while mid-event
     * @param EntityLevelChangeEvent $event
     */
    public function onLevelChange(EntityLevelChangeEvent $event) {
        $entity = $event->getEntity();
        $target = $event->getTarget();
        if ($entity instanceof Player) {
            $player = $entity;

            // Re-render floating texts
            foreach ($this->plugin->provider->getTexts() as $type => $levelData) {
                $data = explode(":", $levelData);
                $level = $data[0];
                if (!isset($this->plugin->ftps[$type][$target->getFolderName()])) {
                    $ftp = $this->plugin->ftps[$type][$level];
                    $ftp->setInvisible(true);
                    $player->getLevel()->addParticle($ftp, [$player]);
                } else {
                    $ftp = $this->plugin->ftps[$type][$level];
                    $ftp->setInvisible(false);
                    $player->getLevel()->addParticle($ftp, [$player]);
                }
            }


            // manage event data
            $activeEvent = $this->plugin->provider->getEventByPlayer($player->getName());
            if ($activeEvent == null) {
                return;
            }

            if ($activeEvent->isInEvent($player->getName())) {
                $this->plugin->getServer()->dispatchCommand($player, "ev leave");
                return;
            }
        }
    }

    /**
     * Prevent players from destroying blocks while in an active event
     * @param BlockBreakEvent $event
     */
    public function onBlockBreak(BlockBreakEvent $event) : void {
        $player = $event->getPlayer();
        $activeEvent = $this->plugin->provider->getEventByPlayer($player->getName());
        if ($activeEvent == null) {
            return;
        }

        if ($activeEvent->isInEvent($player->getName())) {
            $player->sendPopup(c::RED."You cannot break blocks during this event!");
            $event->setCancelled(true);
        }
    }

    /**
     * Prevent players from placing blocks while in an active event
     * @param BlockPlaceEvent $event
     */
    public function onBlockPlace(BlockPlaceEvent $event) : void {
        $player = $event->getPlayer();
        $activeEvent = $this->plugin->provider->getEventByPlayer($player->getName());
        if ($activeEvent == null) {
            return;
        }

        if ($activeEvent->isInEvent($player->getName())) {
            $player->sendPopup(c::RED."You cannot place blocks during this event!");
            $event->setCancelled(true);
        }
    }

    /**
     * Prevent damage during the grace period
     * @param EntityDamageByEntityEvent $event
     */
    public function onEntityDamage(EntityDamageByEntityEvent $event) {
        $entity = $event->getEntity();
        if ($entity instanceof Player) {
            $player = $entity;
            $activeEvent = $this->plugin->provider->getEventByPlayer($player->getName());
            if ($activeEvent == null) {
                return;
            }

            if ($activeEvent->isPlaying($player->getName())) {
                if ($activeEvent->startCoolDown >= 0) {
                    $event->setCancelled(true);
                }
            }

            // listener for gapple1v1 and gapple 2v2 events
            // ---------------------------------------------
            if ($activeEvent->startCoolDown == -1) {

                // when the player has no health left, end round and select winner
                if ($event->getFinalDamage() >= $player->getHealth()) {
                    $event->setCancelled(true);
                    $activeEvent->unsetFromPlaying($player->getName());
                    $activeEvent->setEliminable($player->getName());
                    $player->getArmorInventory()->clearAll();
                    $player->getInventory()->clearAll();
                    $player->removeAllEffects();
                    $player->setMaxHealth($player->getMaxHealth());
                    $player->setFood($player->getMaxFood());
                    $player->addEffect(new EffectInstance(Effect::getEffect(Effect::REGENERATION), 60, 10, false));
                    $player->teleport($activeEvent->getSpectatorPost());
                    foreach ($activeEvent->getPlayers() as $inGame) {
                        $plInGame = $this->plugin->getServer()->getPlayerExact($inGame);
                        if ($plInGame !== null) {
                            $plInGame->sendMessage(c::YELLOW.$player->getName().c::BLUE." Was eliminated!");
                        }
                    }
                }
                // NOW BACK TO THE TASK...
            }
        }
    }
}
