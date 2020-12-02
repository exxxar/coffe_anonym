<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserOnEvent extends Model
{
    //

    protected $fillable = [
        'user_id',
        'event_id'
    ];

    protected $casts = [
        'user_id'=>"string",
    ];
}
