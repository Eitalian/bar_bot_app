<?php

use App\Data\Inventory\RemoveInventoryData;
use App\Handlers\Inventory\RemoveInventoryHandler;
use App\Models\Inventory;

it('deletes the inventory item by id', function () {
    $item = Inventory::factory()->create();

    $result = (new RemoveInventoryHandler)->handle(new RemoveInventoryData(inventoryId: $item->id));

    expect($result)->toBeTrue();
    $this->assertDatabaseMissing('bar_inventory', ['id' => $item->id]);
});

it('returns false when item does not exist', function () {
    $result = (new RemoveInventoryHandler)->handle(new RemoveInventoryData(inventoryId: 999));

    expect($result)->toBeFalse();
});
