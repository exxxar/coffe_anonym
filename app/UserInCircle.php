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

    public function circle(){
        return $this->hasOne(Circle::class, 'id', 'circle_id');
    }
}
