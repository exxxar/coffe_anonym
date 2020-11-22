<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Circle extends Model
{
    protected $fillable = [
        'id',
        'title',
        'description',
        'creator_id',
    ];

    protected $casts = [
        'id'=>"string",
    ];

    public function creator()
    {
        return $this->hasOne(User::class, 'id', 'creator_id');
    }
}
