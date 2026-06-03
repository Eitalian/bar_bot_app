<?php

namespace App\Telegram\Responses;

use App\Models\Recipe;
use Illuminate\Support\Collection;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class SearchResultsResponse
{
    /**
     * @param  iterable<int, Recipe>  $recipes
     */
    public function __construct(
        private string $header,
        private iterable $recipes,
        private string $browseKey,
        private bool $showVolume = true,
        private ?string $overflowText = null,
        private Collection $favoritedIds = new Collection(),
        private Collection $avgRatings = new Collection(),
    ) {}

    public function text(): string
    {
        $text = $this->header;

        $text .= "```\n";
        foreach (collect($this->recipes) as $recipe) {
            $fav = $this->favoritedIds->has($recipe->id) ? '❤' : ' ';

            $name = mb_substr($recipe->name_ru, 0, 20);
            $name = mb_str_pad($name, 20);

            $rateVal = $this->avgRatings->get($recipe->id);
            $rate = $rateVal ? '⭐' . $rateVal : '    ';

            $abvVal = $recipe->abv ? $recipe->abv . '%' : '   ';
            $abv = mb_str_pad($abvVal, 3);

            if ($this->showVolume && $recipe->volume) {
                $volStr = $recipe->volume . 'мл';
                $vol = mb_str_pad($volStr, 5);
            } else {
                $vol = '     ';
            }

            $text .= "{$fav} {$name} {$rate} {$abv} {$vol}\n";
        }
        $text .= "```";

        if ($this->overflowText !== null) {
            $text .= "\n{$this->overflowText}";
        }

        return $text;
    }

    public function keyboard(): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($this->recipes as $pos => $recipe) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    "🍹 {$recipe->name_ru}",
                    callback_data: "recipe:browse:{$this->browseKey}:{$pos}",
                ),
            );
        }

        return $keyboard;
    }
}
