<?php

namespace App\Telegram\Responses;

use App\Models\Recipe;
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
    ) {}

    public function text(): string
    {
        $text = $this->header;

        foreach ($this->recipes as $recipe) {
            $abv = $recipe->abv ? " {$recipe->abv}%" : '';
            $vol = $this->showVolume && $recipe->volume ? " {$recipe->volume}мл" : '';
            $text .= "• {$recipe->name_ru}{$abv}{$vol}\n";
        }

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
