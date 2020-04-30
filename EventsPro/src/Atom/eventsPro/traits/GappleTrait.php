<?php

namespace Atom\eventsPro\traits;

use Atom\eventsPro\events\Event;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\Player;

trait GappleTrait {

    public function sendToGappleEvent(Event $event, Player $player) {
        $event->setPlaying($player->getName());
        $event->unsetSpectating($player->getName());
        $event->unsetFromNextUp($player->getName());
        $player->teleport($event->getArena());
        $inv = $player->getInventory();
        $aInv = $player->getArmorInventory();
        $helmet = Item::get(Item::DIAMOND_HELMET);
        $chestplate = Item::get(Item::DIAMOND_CHESTPLATE);
        $leggings = Item::get(Item::DIAMOND_LEGGINGS);
        $boots = Item::get(Item::DIAMOND_BOOTS);
        $sword = Item::get(Item::DIAMOND_SWORD);
        $gapples = Item::get(Item::GOLDEN_APPLE, 0, 6);
        $armor = [$helmet, $chestplate, $leggings, $boots];
        $prot = new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION), 2);
        $inv->setItem(0, $sword);
        $inv->setItem(1, $gapples);
        $inv->setHeldItemIndex(0);
        $player->addEffect(new EffectInstance(Effect::getEffect(Effect::REGENERATION), 60, 10, false));
        for ($i = 0; $i < count($armor); $i++) {
            $item = $armor[$i];
            $item->addEnchantment($prot);
            $aInv->setItem($i, $item);
        }

    }

}
