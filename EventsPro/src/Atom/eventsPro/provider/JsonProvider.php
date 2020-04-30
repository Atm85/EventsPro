<?php
declare(strict_types=1);

namespace Atom\eventsPro\provider;

use Atom\eventsPro\EventsPro;
use pocketmine\utils\Config;

class JsonProvider extends Provider {

    public function __construct(EventsPro $plugin) {
        parent::__construct($plugin);
        $this->arenas = new Config($plugin->getDataFolder()."data/arenas.json", Config::JSON);
        $this->s_leaders = new Config($this->plugin->getDataFolder()."data/s_leaders.json", Config::JSON);
        $this->g_leaders = new Config($this->plugin->getDataFolder()."data/g_leaders.json", Config::JSON);
        $this->leaderBoards = new Config($this->plugin->getDataFolder()."data/texts.json", Config::JSON);
    }

}
