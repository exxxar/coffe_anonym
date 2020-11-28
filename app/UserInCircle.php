<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserInCircle extends Model
{
    //

    public function circle(){
        return $this->hasOne(Circle::class, 'id', 'circle_id');
    }
}
