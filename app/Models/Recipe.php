<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name_ru', 'name_en', 'description', 'instructions',
        'glass', 'abv', 'volume', 'icon', 'photo', 'taste_tags',
    ];

    protected $casts = [
        'taste_tags' => 'array',
        'abv' => 'float',
        'volume' => 'integer',
    ];

    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('sort_order');
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'recipe_ingredients')
            ->withPivot('amount', 'unit', 'note', 'sort_order');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(RecipeTag::class);
    }

    // Форматирование для Telegram
    public function toTelegramMessage(): string
    {
        $lines = [];
        $lines[] = "🍹 *{$this->name_ru}* ({$this->name_en})";
        $lines[] = '';

        if ($this->description) {
            $lines[] = $this->description;
            $lines[] = '';
        }

        $lines[] = '📊 *Характеристики:*';
        if ($this->abv) {
            $lines[] = "• Крепость: {$this->abv}%";
        }
        if ($this->volume) {
            $lines[] = "• Объём: {$this->volume} мл";
        }
        if ($this->glass) {
            $lines[] = "• Бокал: {$this->glass}";
        }
        $lines[] = '';

        $lines[] = '🧪 *Ингредиенты:*';
        foreach ($this->recipeIngredients as $ri) {
            $amount = $ri->amount ? "{$ri->amount} {$ri->unit}" : '';
            $note = $ri->note ? " ({$ri->note})" : '';
            $lines[] = "• {$ri->ingredient_id}{$note}: {$amount}";
        }
        $lines[] = '';

        if ($this->instructions) {
            $lines[] = '📝 *Приготовление:*';
            $lines[] = $this->instructions;
        }

        return implode("\n", $lines);
    }
}
