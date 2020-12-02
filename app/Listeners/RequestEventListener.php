<?php

namespace App\Listeners;

use App\Classes\Utilits;
use App\Meet;
use App\User;
use Carbon\Carbon;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class RequestEventListener
{

    use Utilits;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    protected function prepareKeyboard($index, $meetId)
    {
        Log::info($index." ".$meetId);
        return [
            [
                ["text" => "1\xE2\x98\x95", "callback_data" => "/meet_poll_rating ".$index.$meetId."1"],
                ["text" => "2\xE2\x98\x95", "callback_data" => "/meet_poll_rating ".$index.$meetId."2"],
                ["text" => "3\xE2\x98\x95", "callback_data" => "/meet_poll_rating ".$index.$meetId."3"],
                ["text" => "4\xE2\x98\x95", "callback_data" => "/meet_poll_rating ".$index.$meetId."4"],
                ["text" => "5\xE2\x98\x95", "callback_data" => "/meet_poll_rating ".$index.$meetId."5"],
            ],


        ];
    }

    /**
     * Handle the event.
     *
     * @param object $event
     * @return void
     */
    public function handle($event)
    {
        //

        if (is_null($event))
            return;

        $test_users = [];

        $meets = Meet::whereNull("meet_day")
            ->where('updated_at', '>=', Carbon::now()->subDays(30)->toDateTimeString())
            ->get();

        foreach ($meets as $meet) {

            Log::info("MEEET=".$meet->id);
            $user1 = User::where("id", $meet->user1_id)->first();
            $user2 = User::where("id", $meet->user2_id)->first();

/*
            if (!in_array($user1->id, $test_users)) {
                array_push($test_users, $user1->id);
                $this->sendMessageToTelegramChannel(
                    $user1->telegram_chat_id,
                    "Добрый день! Недавно вы провели встречу, оцените результат этой встречи:) *Во сколько баллов вы бы оценили встречу?*",
                    $this->prepareKeyboard(1, $meet->id)
                );

            }


            if (!in_array($user2->id, $test_users)) {
                array_push($test_users, $user2->id);
                $this->sendMessageToTelegramChannel(
                    $user2->telegram_chat_id,
                    "Добрый день! Недавно вы провели встречу, оцените результат этой встречи:) *Во сколько баллов вы бы оценили встречу?*",
                    $this->prepareKeyboard(2, $meet->id)
                );
            }*/
        }
    }
}
