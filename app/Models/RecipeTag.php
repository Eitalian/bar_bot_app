<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeTag extends Model
{
    public $timestamps = false;

    protected $fillable = ['recipe_id', 'tag'];
}
