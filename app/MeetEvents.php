<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MeetEvents extends Model
{
    //

    protected $fillable = [
        'id',
        'title',
        'description',
        'date_start',
        'date_end',
    ];
}
