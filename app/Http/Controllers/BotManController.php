<?php

namespace App\Http\Controllers;

use App\Conversations\CalcConversation;
use App\Conversations\CircleConversation;
use App\Conversations\MeetPollConversation;
use App\Conversations\MessagesConversation;
use App\Conversations\RequestConversation;
use App\Conversations\StartNewEventConversation;
use App\Conversations\StartWithDataConversation;
use BotMan\BotMan\BotMan;
use http\Message;
use Illuminate\Http\Request;
use App\Conversations\ExampleConversation;

class BotManController extends Controller
{
    /**
     * Place your BotMan logic here.
     */
    public function handle()
    {
        $botman = app('botman');

        $botman->listen();
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function tinker()
    {
        return view('tinker');
    }

    public function startCircleConversation(BotMan $bot)
    {
        $bot->startConversation(new CircleConversation($bot));
    }

    public function startWithDataConversation(BotMan $bot, $command)
    {
        $bot->startConversation(new StartWithDataConversation($bot, $command));
    }

    public function startNewEventConversation(BotMan $bot)
    {
        $bot->startConversation(new StartNewEventConversation($bot));
    }

    public function sendMessageConversation(BotMan $bot,$userId)
    {
        $bot->startConversation(new RequestConversation($bot,$userId));
    }

    public function meetPollConversation(BotMan $bot,$data)
    {
        $bot->startConversation(new MeetPollConversation($bot,$data));
    }





}
