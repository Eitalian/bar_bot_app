<?php

namespace App\Telegram\Conversations;

use App\Data\Search\SearchRecipesData;
use App\Handlers\Search\SearchRecipesHandler;
use App\Services\BrowseContext;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class FilterConversation extends Conversation
{
    protected ?float $abvMin = null;

    protected ?float $abvMax = null;

    protected ?int $volMin = null;

    protected ?int $volMax = null;

    protected ?string $glass = null;

    protected ?string $tag = null;

    public function start(Nutgram $bot): void
    {
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('💪 По крепости', callback_data: 'filter:start:abv'),
                InlineKeyboardButton::make('📏 По объёму', callback_data: 'filter:start:volume'),
            )
            ->addRow(
                InlineKeyboardButton::make('🥃 По бокалу', callback_data: 'filter:start:glass'),
                InlineKeyboardButton::make('🏷 По тегу', callback_data: 'filter:start:tag'),
            );

        $bot->sendMessage(
            text: "🎛 *Фильтры*\n\nВыберите тип фильтра:",
            parse_mode: 'Markdown',
            reply_markup: $keyboard,
        );

        $this->next('handleFilterType');
    }

    public function handleFilterType(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data ?? '';

        match (true) {
            str_contains($data, 'filter:start:abv') => $this->askAbv($bot),
            str_contains($data, 'filter:start:volume') => $this->askVolume($bot),
            str_contains($data, 'filter:start:glass') => $this->showGlasses($bot),
            str_contains($data, 'filter:start:tag') => $this->showTags($bot),
            default => $bot->sendMessage('Выберите фильтр из кнопок выше.'),
        };
    }

    // --- ABV ---

    private function askAbv(Nutgram $bot): void
    {
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('0–10% (слабые)', callback_data: 'filter:abv:0:10'),
                InlineKeyboardButton::make('10–20% (лёгкие)', callback_data: 'filter:abv:10:20'),
            )
            ->addRow(
                InlineKeyboardButton::make('20–30% (средние)', callback_data: 'filter:abv:20:30'),
                InlineKeyboardButton::make('30%+ (крепкие)', callback_data: 'filter:abv:30:99'),
            )
            ->addRow(
                InlineKeyboardButton::make('Без алкоголя 0%', callback_data: 'filter:abv:0:0'),
            );

        $bot->sendMessage(
            text: '💪 Выберите крепость:',
            reply_markup: $keyboard,
        );
        $this->next('handleAbv');
    }

    public function handleAbv(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data ?? '';
        if (preg_match('/filter:abv:(\d+):(\d+)/', $data, $m)) {
            $this->abvMin = (float) $m[1];
            $this->abvMax = (float) $m[2];
            $this->showResults($bot);
            $this->end();
        }
        $bot->answerCallbackQuery();
    }

    // --- Volume ---

    private function askVolume(Nutgram $bot): void
    {
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('до 60мл (шоты)', callback_data: 'filter:vol:0:60'),
                InlineKeyboardButton::make('60–120мл', callback_data: 'filter:vol:60:120'),
            )
            ->addRow(
                InlineKeyboardButton::make('120–200мл', callback_data: 'filter:vol:120:200'),
                InlineKeyboardButton::make('200мл+ (лонги)', callback_data: 'filter:vol:200:500'),
            );

        $bot->sendMessage(text: '📏 Выберите объём:', reply_markup: $keyboard);
        $this->next('handleVolume');
    }

    public function handleVolume(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data ?? '';
        if (preg_match('/filter:vol:(\d+):(\d+)/', $data, $m)) {
            $this->volMin = (int) $m[1];
            $this->volMax = (int) $m[2];
            $this->showResults($bot);
            $this->end();
        }
        $bot->answerCallbackQuery();
    }

    // --- Glass ---

    private function showGlasses(Nutgram $bot): void
    {
        $glasses = [
            'rocks' => '🥃 Rocks', 'highball' => '🥤 Highball', 'collins' => '🧊 Collins',
            'cocktail' => '🍸 Cocktail', 'coupe' => '🥂 Coupe', 'champagne_flute' => '🍾 Flute',
            'shot' => '🥃 Shot', 'hurricane' => '🌀 Hurricane', 'margarita' => '🍹 Margarita',
        ];

        $keyboard = InlineKeyboardMarkup::make();
        $row = [];
        foreach ($glasses as $id => $label) {
            $row[] = InlineKeyboardButton::make($label, callback_data: "filter:glass:{$id}");
            if (count($row) === 2) {
                $keyboard->addRow(...$row);
                $row = [];
            }
        }
        if ($row) {
            $keyboard->addRow(...$row);
        }

        $bot->sendMessage(text: '🥃 Выберите тип бокала:', reply_markup: $keyboard);
        $this->next('handleGlass');
    }

    public function handleGlass(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data ?? '';
        if (preg_match('/filter:glass:(.+)/', $data, $m)) {
            $this->glass = $m[1];
            $this->showResults($bot);
            $this->end();
        }
        $bot->answerCallbackQuery();
    }

    // --- Tags ---

    private function showTags(Nutgram $bot): void
    {
        $tags = [
            'mild' => '😌 Мягкие', 'long' => '🕐 Лонги', 'strong_recipe' => '💥 Крепкие',
            'soft_recipe' => '🌸 Лёгкие', 'nonalcohol' => '🥤 Безалк', 'official' => '📖 Официальные',
            'shooter' => '🥃 Шутеры', 'new' => '✨ Новые', 'custom' => '🎨 Авторские',
        ];

        $keyboard = InlineKeyboardMarkup::make();
        $row = [];
        foreach ($tags as $id => $label) {
            $row[] = InlineKeyboardButton::make($label, callback_data: "filter:tag:{$id}");
            if (count($row) === 2) {
                $keyboard->addRow(...$row);
                $row = [];
            }
        }
        if ($row) {
            $keyboard->addRow(...$row);
        }

        $bot->sendMessage(text: '🏷 Выберите тег:', reply_markup: $keyboard);
        $this->next('handleTag');
    }

    public function handleTag(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data ?? '';
        if (preg_match('/filter:tag:(.+)/', $data, $m)) {
            $this->tag = $m[1];
            $this->showResults($bot);
            $this->end();
        }
        $bot->answerCallbackQuery();
    }

    // --- Результаты ---

    private function showResults(Nutgram $bot): void
    {
        $data = new SearchRecipesData(
            glass: $this->glass,
            abvMin: $this->abvMin,
            abvMax: $this->abvMax,
            volMin: $this->volMin,
            volMax: $this->volMax,
            tag: $this->tag,
            perPage: config('bar.search.per_page'),
        );

        $results = app(SearchRecipesHandler::class)->handle($data);

        if ($results->isEmpty()) {
            $bot->sendMessage('😔 По выбранным фильтрам ничего не найдено. Попробуйте другие параметры.');

            return;
        }

        $browseKey = app(BrowseContext::class)->store($results->pluck('id')->all(), $bot->userId());

        $text = "🎛 *Результаты фильтрации:*\nНайдено: {$results->total()} рецептов\n\n";
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($results->values() as $pos => $recipe) {
            $abv = $recipe->abv ? " {$recipe->abv}%" : '';
            $vol = $recipe->volume ? " {$recipe->volume}мл" : '';
            $text .= "• {$recipe->name_ru}{$abv}{$vol}\n";
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    "🍹 {$recipe->name_ru}",
                    callback_data: "recipe:browse:{$browseKey}:{$pos}",
                ),
            );
        }

        $bot->sendMessage(text: $text, parse_mode: 'Markdown', reply_markup: $keyboard);
    }
}
