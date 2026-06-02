<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            -- FK-индексы: PostgreSQL не индексирует referencing-колонку FK автоматически.
            -- Покрываем FK-колонки на таблицах, растущих с данными.

            -- orders: recipe_id не покрыт (session_id и user_id добавлены миграцией 2026_05_26)
            CREATE INDEX idx_orders_recipe_id
                ON orders (recipe_id);

            -- favorites: PK ведёт по user_id; поиск по recipe_id (список поклонников) не покрыт
            CREATE INDEX idx_favorites_recipe_id
                ON favorites (recipe_id);

            -- ratings: аналогично favorites
            CREATE INDEX idx_ratings_recipe_id
                ON ratings (recipe_id);

            -- bar_inventory: UNIQUE ведёт по bar_id; поиск по ingredient_id не покрыт
            CREATE INDEX idx_bar_inventory_ingredient_id
                ON bar_inventory (ingredient_id);

            -- recipe_ingredients: основная junction, оба направления join нужны
            CREATE INDEX idx_recipe_ingredients_recipe_id
                ON recipe_ingredients (recipe_id);
            CREATE INDEX idx_recipe_ingredients_ingredient_id
                ON recipe_ingredients (ingredient_id);

            -- recipe_tags / recipe_photos: все запросы идут по recipe_id
            CREATE INDEX idx_recipe_tags_recipe_id
                ON recipe_tags (recipe_id);
            CREATE INDEX idx_recipe_photos_recipe_id
                ON recipe_photos (recipe_id);

            -- CHECK-ограничения: закрепляем инварианты из дизайн-документов фаз 3.1 и 4.

            -- orders.quantity: NULL пока бармен не принял; принятое значение 1–5 (Phase 3.1 design)
            ALTER TABLE orders
                ADD CONSTRAINT chk_orders_quantity
                    CHECK (quantity IS NULL OR (quantity BETWEEN 1 AND 5));

            -- ratings.score: шкала 1–5 (Phase 4 design, chk_ratings_score)
            ALTER TABLE ratings
                ADD CONSTRAINT chk_ratings_score
                    CHECK (score BETWEEN 1 AND 5);
        ");
    }

    public function down(): void
    {
        DB::unprepared(/** @lang PostgreSQL */ "
            ALTER TABLE ratings  DROP CONSTRAINT IF EXISTS chk_ratings_score;
            ALTER TABLE orders   DROP CONSTRAINT IF EXISTS chk_orders_quantity;

            DROP INDEX IF EXISTS idx_recipe_photos_recipe_id;
            DROP INDEX IF EXISTS idx_recipe_tags_recipe_id;
            DROP INDEX IF EXISTS idx_recipe_ingredients_ingredient_id;
            DROP INDEX IF EXISTS idx_recipe_ingredients_recipe_id;
            DROP INDEX IF EXISTS idx_bar_inventory_ingredient_id;
            DROP INDEX IF EXISTS idx_ratings_recipe_id;
            DROP INDEX IF EXISTS idx_favorites_recipe_id;
            DROP INDEX IF EXISTS idx_orders_recipe_id;
        ");
    }
};
