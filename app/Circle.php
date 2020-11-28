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

    public function creator()
    {
        return $this->hasOne(User::class, 'id', 'creator_id');
    }
}
