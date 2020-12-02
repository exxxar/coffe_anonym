<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class IgnoreList extends Model
{
    //

   protected $fillable = [
        'main_user_id',
        'ignored_user_id'
    ];

    protected $casts = [
        'main_user_id'=>"string",
        'ignored_user_id'=>"string",
    ];
}
