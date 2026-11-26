<?php
namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Marine Supply: the six marine categories shown as "Shop by Category" (owner brief 26 Nov 2026). Products stay in the mother catalog with their own SKU;
 * each marine product is tied to one marine category (and, for Technical Stores, a subcategory).
 * Access: Bonded Stores is listed and priced only for a verified account that has the separate bonded approval. That rule is applied in the product list, search, single product and requisition lines.
 */
class MarineCats
{
  const CATS = [
    'provisions' => ['name' => 'Provisions', 'text' => 'Food, water and beverages for the vessel.', 'subs' => []],
    'cabin'      => ['name' => 'Cabin & Galley Stores', 'text' => 'Cabin, galley, toiletries and cleaning supplies.', 'subs' => []],
    'technical'  => ['name' => 'Technical Stores', 'text' => 'Deck, engine, electrical stores and spare parts.', 'subs' => ['deck' => 'Deck', 'engine' => 'Engine', 'electrical' => 'Electrical', 'spares' => 'Spare Parts']],
    'safety'     => ['name' => 'Safety & PPE', 'text' => 'Helmets, gloves, safety shoes and protective equipment.', 'subs' => []],
    'bonded'     => ['name' => 'Bonded Stores', 'text' => 'Bonded goods for approved accounts.', 'subs' => []],
    'crew'       => ['name' => 'Crew Shop', 'text' => 'Personal shopping for crew: phones, watches, sunglasses, chargers and travel accessories.', 'subs' => []],
  ];
  public static function ready(): bool { static $r = null; if ($r === null) { try { $r = Schema::hasColumn('marine_products', 'mcat') && Schema::hasColumn('marine_accounts', 'bonded_ok'); } catch (\Throwable $e) { $r = false; } } return $r; }
  public static function ok(?string $k): bool { return $k !== null && isset(self::CATS[$k]); }
  public static function sub(?string $cat, ?string $sub): ?string { return ($cat && $sub && isset(self::CATS[$cat]['subs'][$sub])) ? $sub : null; }
  public static function label(?string $cat, ?string $sub = null): string { if (!self::ok($cat)) return 'Not mapped'; $s = self::sub($cat, $sub); return self::CATS[$cat]['name'] . ($s ? ' / ' . self::CATS[$cat]['subs'][$s] : ''); }

  /** First placement of a mother-catalog product: [category, subcategory]. Bonded mother categories always go to Bonded Stores. Staff can change any result by hand. */
  public static function guess(string $name, string $motherCat, bool $bonded): array {
    if ($bonded) return ['bonded', null];
    $n = ' ' . mb_strtolower($name) . ' '; $c = mb_strtolower($motherCat); $has = fn(string $re, string $in) => preg_match('/(?<![a-z])(?:' . $re . ')(?![a-z])/u', $in) === 1;
    // The store category decides first: consumer categories never land in Technical Stores or Safety & PPE because of a word in the product name
    // (first run, 26 Nov 2026: "AM4 Socket processor", "Lifebuoy soap" and deodorants were placed there by keywords alone).
    if ($has('watch(es)?|mobiles?|phones?|gadgets?|electronics?|fashion|sunglass(es)?|perfumes?|atar|attar|lighters?|accessor(y|ies)|bags?|travel|computers?|laptops?', $c)) return ['crew', null];
    if ($has('beauty|skin|hair|cosmetics?|personal care|toiletr(y|ies)', $c)) return ['cabin', null];
    if ($has('helmets?|safety (shoes?|boots?|goggles?|glasses|harness|vest|gloves?)|ppe|coveralls?|boiler ?suits?|life ?jackets?|life ?buoys?|ear ?(plugs?|muffs?)|respirators?|dust masks?|fire extinguishers?|first aid|work gloves?|hi-?vis', $n)) return ['safety', null];
    if ($has('mobile|phone|smartphone|iphone|galaxy|redmi|watch(es)?|smart ?watch|sunglass(es)?|chargers?|power ?banks?|earbuds?|ear ?phones?|headphones?|speakers?|travel|luggage|backpacks?|wallets?|perfumes?|eau de (parfum|toilette)|attar|atar|cologne', $n . ' ' . $c)) return ['crew', null];
    if ($has('engine oil|lub(e|ricant)s?|grease|gaskets?|bearings?|filters?|v-?belts?|o-?rings?|valves?|impellers?', $n)) return ['technical', 'engine'];
    if ($has('cables?|bulbs?|lamps?|led (light|tube)s?|fuses?|batter(y|ies)|switch(es)?|sockets?|torch(es)?|flashlights?|multimeters?|breakers?|extension cords?', $n)) return ['technical', 'electrical'];
    if ($has('ropes?|shackles?|paint brush(es)?|rollers?|marine paints?|thinner|wire brush(es)?|chipping hammers?|scrapers?|tarpaulins?|mooring|rat guards?|spanners?|wrench(es)?|pliers|screwdrivers?|hammers?|hacksaws?|drill bits?', $n)) return ['technical', 'deck'];
    if ($has('spare parts?|spares', $n)) return ['technical', 'spares'];
    if ($has('soap|shampoo|conditioner|tooth ?(paste|brush(es)?)|mouth ?wash|deodorants?|body ?(wash|lotion|spray)|lotions?|(face|body|hand|hair|shaving|night|day|beauty) creams?|razors?|shav(e|ing)|tissues?|toilet (paper|rolls?)|napkins?|towels?|detergents?|dish ?wash(ing)?|wash(ing)? (powder|liquid)|clean(ers?|ing|sers?)|bleach|disinfectants?|air fresheners?|mops?|brooms?|sponges?|scrubbers?|garbage bags?|bin bags?|foil|cling film|plates?|cups?|mugs?|glasses|cutlery|spoons?|forks?|knives|kniv?es?|pans?|pots?|kettles?|bed ?sheets?|pillows?|blankets?|mattress(es)?|hangers?|laundry|insect|mosquito|sanitizers?|hand ?wash|face ?wash|serum|moisturi[sz]ers?|sunscreen|hair (oil|gel|colou?r|cream)|skin|beauty|cosmetics?|make ?up|lipsticks?', $n . ' ' . $c)) return ['cabin', null];
    if ($has('rice|flour|sugar|salt|oils?|ghee|butter|cheese|milk|yogh?urt|eggs?|chicken|beef|mutton|fish|tuna|sardines?|prawns?|shrimps?|meat|sausages?|noodles?|pasta|spaghetti|macaroni|bread|biscuits?|cookies?|crackers?|cakes?|chocolates?|cand(y|ies)|chips|snacks?|nuts?|almonds?|cashews?|dates|honey|jam|jelly|sauces?|ketchup|mayonnaise|vinegar|spices?|masala|pepper|turmeric|chilli|cereals?|oats|corn ?flakes|muesli|coffee|tea|teas|nescafe|juices?|drinks?|water|cola|soda|beverages?|syrups?|soups?|beans|lentils?|dal|vegetables?|fruits?|potato(es)?|onions?|garlic|ginger|tomato(es)?|pickles?|olives?|ice ?cream|milk powder|creamer|horlicks|ovaltine|milo|energy drink|gum|food|grocery', $n)) return ['provisions', null];
    if ($has('daily needs|grocery|food|beverages?', $c)) return ['provisions', null];
    return ['crew', null];
  }
}
