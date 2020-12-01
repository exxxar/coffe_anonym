<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Circle extends Model
{
    protected $fillable = [
        'id',
        'title',
        'settings',
        'description',
        'creator_id',
        'create_at',
    ];

    protected $casts = [
        'id'=>"string",
    ];

    protected $appends = [
      'users_count'
    ];

    public function creator()
    {
        return $this->hasOne(User::class, 'id', 'creator_id');
    }

    public function users(){
       return $this->belongsToMany(User::class, 'user_in_circles', 'circle_id', 'user_id')
            ->withTimestamps();
    }

    public function getUsersCountAttribute(){
        return $this->users()->count();
    }
}
