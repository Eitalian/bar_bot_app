<?php

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Recipe;
use Illuminate\Database\QueryException;

it('cannot delete a recipe that has orders', function () {
    $order = Order::factory()->create();

    expect(fn() => $order->recipe->delete())
        ->toThrow(QueryException::class);
});

it('cannot delete a user that has orders', function () {
    $order = Order::factory()->create();

    expect(fn() => $order->user->delete())
        ->toThrow(QueryException::class);
});

it('cannot delete an ingredient that is in bar inventory', function () {
    $inventory = Inventory::factory()->create();

    expect(fn() => $inventory->ingredient->delete())
        ->toThrow(QueryException::class);
});

it('cannot delete an ingredient that is used in recipe ingredients', function () {
    $recipe     = Recipe::factory()->hasIngredients(1)->create();
    $ingredient = $recipe->ingredients()->first();

    expect(fn() => Ingredient::destroy($ingredient->id))
        ->toThrow(QueryException::class);
});
