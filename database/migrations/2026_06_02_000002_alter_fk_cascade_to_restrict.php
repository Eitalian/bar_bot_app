<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Prevent silent cascade deletion of business history records.
        // Orders, inventory entries, and recipe compositions reference
        // independent entities (users, recipes, ingredients) that must
        // not be deletable while referenced data exists.
        DB::statement('ALTER TABLE orders DROP CONSTRAINT fk_orders_recipe_id,
            ADD CONSTRAINT fk_orders_recipe_id
                FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE RESTRICT');

        DB::statement('ALTER TABLE orders DROP CONSTRAINT fk_orders_user_id,
            ADD CONSTRAINT fk_orders_user_id
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT');

        DB::statement('ALTER TABLE bar_inventory DROP CONSTRAINT fk_bar_inventory_ingredient_id,
            ADD CONSTRAINT fk_bar_inventory_ingredient_id
                FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE RESTRICT');

        DB::statement('ALTER TABLE recipe_ingredients DROP CONSTRAINT fk_recipe_ingredients_ingredient_id,
            ADD CONSTRAINT fk_recipe_ingredients_ingredient_id
                FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE orders DROP CONSTRAINT fk_orders_recipe_id,
            ADD CONSTRAINT fk_orders_recipe_id
                FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE');

        DB::statement('ALTER TABLE orders DROP CONSTRAINT fk_orders_user_id,
            ADD CONSTRAINT fk_orders_user_id
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');

        DB::statement('ALTER TABLE bar_inventory DROP CONSTRAINT fk_bar_inventory_ingredient_id,
            ADD CONSTRAINT fk_bar_inventory_ingredient_id
                FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE');

        DB::statement('ALTER TABLE recipe_ingredients DROP CONSTRAINT fk_recipe_ingredients_ingredient_id,
            ADD CONSTRAINT fk_recipe_ingredients_ingredient_id
                FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE');
    }
};
