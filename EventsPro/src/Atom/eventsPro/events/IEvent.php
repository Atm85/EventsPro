<?php
declare(strict_types=1);

namespace Atom\eventsPro\events;

interface IEvent {

    public function getType() : string;

    public function getId() : string;

    public function getStartingTime() : int;

    public function getMaxPlayers() : int;
}
