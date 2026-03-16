<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeTag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportRecipes extends Command
{
    protected $signature = 'bar:import {--file=data/recipes_final.json : путь к JSON файлу}';

    protected $description = 'Импорт рецептов из JSON файла MyBar';

    public function handle(): int
    {
        $file = base_path($this->option('file'));

        if (! file_exists($file)) {
            $this->error("Файл не найден: {$file}");

            return self::FAILURE;
        }

        $recipes = json_decode(file_get_contents($file), true);
        $this->info('Найдено рецептов: ' . count($recipes));

        $skippedTags = 0;
        $importedIngredients = 0;

        DB::transaction(function () use ($recipes, &$skippedTags, &$importedIngredients) {
            foreach ($recipes as $data) {
                // Пропускаем рецепты без обязательных полей
                if (empty($data['id']) || empty($data['name_ru'])) {
                    $this->warn('Пропущен рецепт без id или name_ru');

                    continue;
                }

                // Создаём/обновляем рецепт
                $recipe = Recipe::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'name_ru' => $data['name_ru'],
                        'name_en' => $data['name_en'] ?? null,
                        'description' => $data['description'] ?? null,
                        'instructions' => $data['instructions'] ?? null,
                        'glass' => $data['glass'] ?? null,
                        'abv' => $data['abv'] ?? null,
                        'volume' => $data['volume'] ?? null,
                        'icon' => $data['icon'] ?? null,
                        'photo' => $data['photo'] ?? null,
                    ],
                );

                // Теги — пропускаем UUID
                RecipeTag::where('recipe_id', $recipe->id)->delete();
                if (! empty($data['tags'])) {
                    foreach (explode(',', $data['tags']) as $tag) {
                        $tag = trim($tag);
                        // Пропускаем UUID и числа
                        if (empty($tag) || preg_match('/^[0-9a-f-]{36}$/i', $tag) || is_numeric($tag)) {
                            $skippedTags++;

                            continue;
                        }
                        RecipeTag::create(['recipe_id' => $recipe->id, 'tag' => $tag]);
                    }
                }

                // Ингредиенты
                RecipeIngredient::where('recipe_id', $recipe->id)->delete();
                foreach ($data['ingredients'] ?? [] as $i => $ing) {
                    $ingId = $ing['ingredient_id'];

                    // Пропускаем UUID ингредиенты
                    if (preg_match('/^[0-9a-f-]{36}$/i', $ingId)) {
                        continue;
                    }

                    // Создаём ингредиент если нет
                    if (! Ingredient::find($ingId)) {
                        Ingredient::create([
                            'id' => $ingId,
                            'name_en' => str_replace('_', ' ', $ingId),
                        ]);
                        $importedIngredients++;
                    }

                    RecipeIngredient::create([
                        'recipe_id' => $recipe->id,
                        'ingredient_id' => $ingId,
                        'amount' => $ing['amount'] ?? null,
                        'unit' => $ing['unit'] ?? null,
                        'note' => $ing['note'] ?? null,
                        'sort_order' => $i,
                    ]);
                }
            }
        });

        $this->info('✅ Импорт завершён!');
        $this->info('   Рецептов: ' . Recipe::count());
        $this->info('   Ингредиентов: ' . Ingredient::count());
        $this->info("   UUID тегов пропущено: {$skippedTags}");

        return self::SUCCESS;
    }
}
