<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $telegram_id
 * @property string $first_name
 * @property string|null $username
 * @property \Illuminate\Support\Carbon $created_at
 */
class User extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'telegram_id',
        'first_name',
        'username',
    ];

    protected $casts = [
        'telegram_id' => 'integer',
        'created_at' => 'datetime',
    ];

    /** @return HasMany<Inventory, $this> */
    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}
