<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'name', 'email', 'password',
        'telegram_chat_id',
        'fio_from_telegram',
        'phone',
        'is_admin',
        'age',
        'sex',
        'need_meeting',
        'location',
        'meet_in_week',
        'prefer_meet_in_week',
    ];

    protected $casts = [
        "location" => "array",
        'id'=>"string",
    ];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public function circles()
    {
        return $this->belongsToMany(Circle::class, 'user_in_circles', 'user_id', 'circle_id')
/*            ->withPivot([
                'inviter_id',
            ])*/
            ->withTimestamps();
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'user_in_events', 'user_id', 'event_id')
            ->withTimestamps();
    }


    private function meets1()
    {
        return $this->hasMany(Meet::class, 'user1_id', 'id');
    }

    private function meets2()
    {
        return $this->hasMany(Meet::class, 'user2_id', 'id');
    }


}
