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

        'rating_1',
        'rating_2',
        'is_online',
        'is_success',
        "short_comment_1",
        "short_comment_2",
        "meet_day",
        "updated_at",
        "created_at",
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
        return $this->hasOne(MeetEvents::class, 'id', 'event_id');
    }

}
