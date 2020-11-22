<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Meet extends Model
{
    //

    protected $fillable = [
        'id',
        'user1_id',
        'user2_id',
        'event_id',

        'rating',
        'is_online',
        'is_success',
        "short_comment",
        "meet_date",
    ];


    public function user1()
    {
        return $this->hasOne(User::class, 'id', 'user1_id');
    }

    public function user2()
    {
        return $this->hasOne(User::class, 'id', 'user2_id');
    }

    public function event()
    {
        return $this->hasOne(Event::class, 'id', 'event_id');
    }

}
