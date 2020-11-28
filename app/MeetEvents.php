<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeetEvents extends Model
{
    //
    use SoftDeletes;

    protected $fillable = [
        'id',
        'title',
        'description',
        'date_start',
        'date_end',
        'image_url',
    ];
}
