<?php

use App\Data\Inventory\RemoveInventoryData;
use App\Handlers\Inventory\RemoveInventoryHandler;
use App\Models\Inventory;
use App\Models\User;

it('deletes the inventory item belonging to the user', function () {
    $user = User::factory()->create();
    $item = Inventory::factory()->create(['user_id' => $user->id]);

    $data = new RemoveInventoryData(user_id: $user->id, inventory_id: $item->id);
    $result = (new RemoveInventoryHandler)->handle($data);

    expect($result)->toBeTrue();
    $this->assertDatabaseMissing('bar_inventory', ['id' => $item->id]);
});

it('does not delete item belonging to another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $item = Inventory::factory()->create(['user_id' => $owner->id]);

    $data = new RemoveInventoryData(user_id: $other->id, inventory_id: $item->id);
    $result = (new RemoveInventoryHandler)->handle($data);

    expect($result)->toBeFalse();
    $this->assertDatabaseHas('bar_inventory', ['id' => $item->id]);
});
