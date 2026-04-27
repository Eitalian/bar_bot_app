<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'bar_inventory';

    protected $fillable = [
        'ingredient_id',
        'quantity',
        'unit',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'quantity' => 'float',
    ];

    /** @return BelongsTo<Ingredient, self> */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
