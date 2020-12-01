<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/req', function () {

    /*$data = YaGeo::setQuery('53.082592, 56.424311')->load();

    $data = $data->getResponse()->getLocality();

    dd($data);*/

    //event(new \App\Events\GenerateMeetEvent());
    event(new \App\Events\RequestMeetEvent());

});

Route::match(['get', 'post'], '/botman', 'BotManController@handle');
Route::get('/botman/tinker', 'BotManController@tinker');
