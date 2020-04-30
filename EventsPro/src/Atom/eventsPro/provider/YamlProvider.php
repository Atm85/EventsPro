<?php
declare(strict_types=1);

namespace Atom\eventsPro\provider;

use Atom\eventsPro\EventsPro;
use pocketmine\utils\Config;

class YamlProvider extends Provider {

    public function __construct(EventsPro $plugin) {
        parent::__construct($plugin);
        $this->arenas = new Config($plugin->getDataFolder()."data/arenas.yml", Config::YAML);
        $this->s_leaders = new Config($this->plugin->getDataFolder()."data/s_leaders.yml", Config::YAML);
        $this->g_leaders = new Config($this->plugin->getDataFolder()."data/g_leaders.yml", Config::YAML);
        $this->leaderBoards = new Config($this->plugin->getDataFolder()."data/texts.yml", Config::YAML);
    }

}
