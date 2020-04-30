<?php

namespace Atom\eventsPro\traits;

use Atom\eventsPro\events\Event;

trait PlayerSelectorTrait {

    public function select1v1Queue(Event $event) {

        start:
        if (count($event->getNextUp()["no-team"]) != 2 && count($event->getSpectating()) >= 2) {
            $i = array_rand($event->getSpectating());
            $pl = $event->getSpectating()[$i];

            // make sure that the same player isn't selected twice. If so, run again
            if (!in_array($pl, $event->getNextUp()["no-team"])) {
                $event->setNextUp($pl);
            }
            goto start;
        }

        // if there is only one player in queue, select an additional player from a previus round
        if (count($event->getSpectating()) == 1 && count($event->getTally()) >= 1) {
            $pl1 = array_values($event->getSpectating())[0];
            $pl2 = array_search(max($event->getTally()), $event->getTally());
            if ($pl1 !== $pl2) {
                $event->setNextUp($pl1);
                $event->setNextUp($pl2);
            }
        }

    }

    public function select2v2Queue(Event $event) {
        return;
    }

}
