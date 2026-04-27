<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}
