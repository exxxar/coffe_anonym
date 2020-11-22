<?php

use App\Circle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // $this->call(UsersTableSeeder::class);

        \App\Circle::truncate();

        Circle::create([
            'id' => "6d121939-a783-413a-a2f9-ecab0e5e6f62",
            'title'=>"Песочница",
            'description'=>"Ваш первый круг интересов! Это повод найти разнообразных собеседников с широкими интересами;)",
        ]);
    }
}
