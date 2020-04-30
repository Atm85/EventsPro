<?php
declare(strict_types=1);

namespace Atom\eventsPro;

use Atom\eventsPro\commands\EventCommand;
use Atom\eventsPro\listeners\PluginEventListener;
use Atom\eventsPro\provider\JsonProvider;
use Atom\eventsPro\provider\Provider;
use Atom\eventsPro\provider\YamlProvider;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as c;

class EventsPro extends PluginBase {

    /** @var Provider */
    public $provider;

    /** @var FloatingTextParticle */
    public $ftps = [];

    public function onLoad() : void {
        $map = $this->getServer()->getCommandMap();
        $map->register("EventsPro", new EventCommand("event", $this));
    }

    public function onEnable() : void {
        @mkdir($this->getDataFolder()."data/");
        $this->saveDefaultConfig();
        $this->registerDataProviders();
        $this->getServer()->getPluginManager()->registerEvents(new PluginEventListener($this), $this);

        foreach ($this->provider->getArenas() as $level => $arenaData) {
            $this->getServer()->loadLevel($level);
        }

    }

    private function registerDataProviders() {

        $provider = $this->getConfig()->get("data-provider");

        switch ($provider) {
            case "yaml":
                $this->provider = new YamlProvider($this);
                return;
            case "json":
                $this->provider = new JsonProvider($this);
                return;
            default:
                $this->getLogger()->critical("Unknown data-provider ".$provider);
                $this->getServer()->getPluginManager()->disablePlugin($this);
        }
    }

    public function addTextParticle(Position $pos, Player $player, string $type, array $data) : void {

        $stats = [];
        foreach ($data as $name => $datum) {
            $stats[$name] = $datum["wins"];
        }

        arsort($stats, SORT_NUMERIC);

        $rankings = "";
        $index = 1;
        foreach ($stats as $name => $score) {
            $rankings .= c::RED."$index".c::GRAY." - ".c::YELLOW.$name.c::GRAY.": ".c::AQUA.$score."\n";
            if ($index > 10) {
                return;
            }

            $index++;
        }

        $particle = new FloatingTextParticle($pos, $rankings, c::GREEN."Most $type wins!\n");
        $player->getLevel()->addParticle($particle, [$player]);
        $this->ftps[$type][$pos->getLevel()->getFolderName()] = $particle;

    }

}
