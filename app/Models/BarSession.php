<?php

namespace App\Models;

use Database\Factories\BarSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class BarSession extends Model
{
    /** @use HasFactory<BarSessionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['bar_id', 'started_at', 'ended_at'];

    protected $casts = [
        'bar_id'     => 'integer',
        'started_at' => 'immutable_datetime',
        'ended_at'   => 'immutable_datetime',
    ];
}
