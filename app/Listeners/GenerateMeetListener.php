<?php

namespace App\Listeners;

use App\Classes\Utilits;
use App\Events\GenerateMeetEvent;
use App\IgnoreList;
use App\Meet;
use App\User;
use Carbon\Carbon;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Telegram\Bot\Laravel\Facades\Telegram;

class GenerateMeetListener
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

    /**
     * Handle the event.
     *
     * @param object $event
     * @return void
     */
    public function handle(GenerateMeetEvent $event)
    {
        if (is_null($event))
            return;

        $users = User::with(["circles", "circles.users"])
            ->where('need_meeting', true)
            ->where("meet_in_week", $event->current_week_iteration)
            ->where("updated_at", "<=", Carbon::now()->subDays(30)->toDateTimeString())
            ->get();


        foreach ($users as $index => $user) {

            $tmp_users_array_ids = []; //собираем сюда айдишники пользователей из кругов пользователя
            //Log::info($user->name . " " . $user->id . " " . $user->circles()->get()->count());

            $circles = $user->circles()->get();

            foreach ($circles as $circle) {
                $users_in_circle = $circle->users()->get();

                // Log::info("count=>".$users_in_circle->count());

                foreach ($users_in_circle as $uic)
                    if (!in_array($uic->id, $tmp_users_array_ids) && $uic->id != $user->id)
                        array_push($tmp_users_array_ids, $uic->id);
            }

            $tmp_users_array_ids_in_meets = []; //собирает сюда айдишники тех, кто уже побывал на встречах с этим пользователем

            $meets1 = Meet::where("user1_id", $user->id)
                ->where("created_at", "<=", Carbon::now()->subDays(30)->toDateTimeString())
                ->get();

            $meets2 = Meet::where("user2_id", $user->id)
                ->where("created_at", "<=", Carbon::now()->subDays(30)->toDateTimeString())
                ->get();

            foreach ($meets1 as $meet)
                if (!in_array($meet->user1_id, $tmp_users_array_ids_in_meets))
                    array_push($tmp_users_array_ids_in_meets, $meet->user1_id);

            foreach ($meets2 as $meet)
                if (!in_array($meet->user2_id, $tmp_users_array_ids_in_meets))
                    array_push($tmp_users_array_ids_in_meets, $meet->user2_id);

            $ignored_list = IgnoreList::where("ignored_user_id", $user->id)
                ->first();

            $tmp_ignored_users_array_id = [];

            foreach ($ignored_list as $il)
                if (!in_array($il->main_user_id, $tmp_ignored_users_array_id))
                    array_push($tmp_ignored_users_array_id, $il->main_user_id);


            $tmp_users_array_ids = array_diff($tmp_users_array_ids, $tmp_ignored_users_array_id);
            $tmp_users_array_ids = array_diff($tmp_users_array_ids, $tmp_users_array_ids_in_meets);

            shuffle($tmp_users_array_ids);

            $tmp_users_array_ids = array_rand($tmp_users_array_ids, min(count($tmp_users_array_ids, 2)));


            $tmp_meet_users = User::whereIn('id', $tmp_users_array_ids)
                ->get();

            $is_complete = false;
            $tmp_tmu = null;
            foreach ($tmp_meet_users as $tmu) {
                if ($tmu->sex === $user->prefer_meet_in_week) {
                    $tmp_tmu = $tmu;
                    $is_complete = true;
                }
            }

            if (!$is_complete)
                $tmp_tmu = $tmp_meet_users->random(1)->first();


            Meet::create([
                'id' => (string)Str::uuid(),
                'user1_id' => $user->id,
                'user2_id' => $tmp_tmu->id,
            ]);

            $code_1 = "007" . $tmp_tmu->id;
            $code_2 = "007" . $user->id;
            $this->sendMessageToTelegramChannel(
                $user->telegram_chat_id,
                "Добрый день! Собеседник хочет пригласить Вас на чашечку кофе! Свяжитесь с ним и назначте ему встречу:)",
                [
                    [
                        ["text" => "Ответить собеседнику!", "url" => "https://t.me/" . env("APP_BOT_NAME") . "?start=$code_1"]
                    ]
                ]
            );

            $this->sendMessageToTelegramChannel(
                $tmp_tmu->telegram_chat_id,
                "Добрый день! Собеседник хочет пригласить Вас на чашечку кофе! Свяжитесь с ним и назначте ему встречу:)",
                [
                    [
                        ["text" => "Ответить собеседнику!", "url" => "https://t.me/" . env("APP_BOT_NAME") . "?start=$code_2"]
                    ]
                ]
            );


        }
        //
    }
}
