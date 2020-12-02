<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserInCircle extends Model
{
    //

    protected $fillable = [
      'user_id',
      'circle_id'
    ];

    protected $casts = [
        'user_id'=>"string",
        'circle_id'=>"string",
    ];

    public function circle(){
        return $this->hasOne(Circle::class, 'id', 'circle_id');
    }
}
