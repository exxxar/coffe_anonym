<?php


namespace App\Classes;


use App\Circle;
use App\User;
use App\UserInCircle;
use Illuminate\Support\Str;
use NumberToWords\NumberToWords;

class Base
{
    public static function initUser($bot, $circleId = null)
    {
        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();

        $username = $telegramUser->getUsername() ?? '';
        $lastName = $telegramUser->getLastName() ?? '';
        $firstName = $telegramUser->getFirstName() ?? '';

        $is_first = false;
        $user = User::where("telegram_chat_id", $id)->first();
        if ($user == null) {
            $userId = (string)Str::uuid();
            User::create([
                'id' => $userId,
                'name' => $username,
                'email' => "$id@t.me",
                'password' => bcrypt($id),
                'fio_from_telegram' => "$firstName $lastName",
                'telegram_chat_id' => $id,
                'is_admin' => false,
                'need_meeting' => true,
                'location' => json_encode([
                    "latitude" => null,
                    "longitude" => null,
                    "city" => null
                ]),
                'meet_in_week' => 1,
                'prefer_meet_in_week' => 0,
            ]);

            $user = User::where("id", $userId)->first();


            $circleId = $circleId ?? (Circle::where("id", env("FIRST_CIRCLE_ID"))->first())->id;

            $user->circles()->attach($circleId);

            $noName = is_null($lastName) || is_null($firstName);

            Base::sendToAdminChannel($bot, "*К нам на \xE2\x98\x95 пришел новый собеседник* [" .
                (!$noName ? ($lastName ?? '') . " " . ($firstName ?? '') : $username)
                . "](tg://user?id=" . $id . ")!"
            );

            $is_first = true;
        }

        return (object)[
            "user" => $user,
            "is_first" => $is_first
        ];
    }

    public static function inviteToCircle($bot,$circleId){
        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();

        $user = User::where("telegram_chat_id", $id)->first();

        $circle = Circle::where("id", env("FIRST_CIRCLE_ID"))->first();

        if (is_null($circle)){
            $bot->reply("Хм, я почему-то не могу найти этот круг по интересам, но вы можете его создать => /create");
            return;
        }

        $userInCircle = UserInCircle::where("circle_id",$circleId)
            ->where("user_id",$user->id)
            ->first() != null;

        if ($userInCircle){
            $bot->reply("Вы и так в этом кругу, но вы всегда можете создать что-то своё => /create");
            return;
        }

        if (!$userInCircle) {
            $user->circles()->attach($circleId);

            $keyboard = [

                [
                    ["text" => "\xE2\x9D\x8EВыйти из круга", "callback_data" => "/leave_circle " . $circle->id],
                ],
            ];

            $code = "003" . $circle->id;

            $peopleCount = UserInCircle::where("circle_id", $circle->id)->count();

            $numberToWords = new NumberToWords();
            $numberTransformer = $numberToWords->getNumberTransformer('ru');

            $peopleCountText = $numberTransformer->toWords($peopleCount);

            $message = sprintf("[%s](https://t.me/%s?start=%s) - это ваш круг по интересам! Делитесь этим сообщением и расширяте круг людей\xF0\x9F\x98\x89\n\n_%s _\n\nВсего участников в кругу *%s (%s)*",
                Str::ucfirst($circle->title),
                env("APP_BOT_NAME"),
                $code,
                Str::ucfirst($circle->description),
                $peopleCount,
                $peopleCountText
            );
            $bot->sendRequest("sendMessage",
                [
                    "chat_id" => env("TELEGRAM_ADMIN_CHANNEL"),
                    "parse_mode" => "markdown",
                    "text" => $message,
                    'reply_markup' => json_encode([
                        'inline_keyboard' =>
                            $keyboard
                    ])
                ]);
            }


    }

    public static function isAdmin($bot)
    {
        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();
        $user = User::where("telegram_chat_id", $id)->first();
        return $user->is_admin ? true : false;
    }

    public static function sendToAdminChannel($bot, $message)
    {

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();

        $user = User::where("telegram_chat_id", $id)->first();

        $tmp_id = (string)$user->id;
        while (strlen($tmp_id) < 10)
            $tmp_id = "0" . $tmp_id;

        $keyboard = [
            [
                ["text" => "\xE2\x9C\x8FНаписать пользователю", "url" => "https://t.me/" . env("APP_BOT_NAME") . "?start=" . base64_encode("002" . $tmp_id . "0000000000")],
                ["text" => "\xE2\x98\x95Организовать встречу", "url" => "https://t.me/" . env("APP_BOT_NAME") . "?start=" . base64_encode("003" . $tmp_id . "0000000000")]
            ],
        ];

        $bot->sendRequest("sendMessage",
            [
                "chat_id" => env("TELEGRAM_ADMIN_CHANNEL"),
                "parse_mode" => "markdown",
                "text" => $message,
                'reply_markup' => json_encode([
                    'inline_keyboard' =>
                        $keyboard
                ])
            ]);
    }

    public static function mainMenu($bot, $message)
    {

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();


        $keyboard = [
            ["\xF0\x9F\x92\xABКруги по интересам", "\xE2\x98\x9DКак пользоваться?"],
        ];

        if (Base::isAdmin($bot))
            array_push($keyboard, ["\xF0\x9F\x93\x8AРаздел администратора"]);

        $bot->sendRequest("sendMessage",
            [
                "chat_id" => "$id",
                "text" => $message,
                "parse_mode" => "Markdown",
                'reply_markup' => json_encode([
                    'keyboard' => $keyboard,
                    'one_time_keyboard' => false,
                    'resize_keyboard' => true
                ])
            ]);

    }

    public static function dialogMenu($bot, $message)
    {

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();


        $keyboard = [
            ["	\xF0\x9F\x91\x88Передумал создавать"],
        ];

        $bot->sendRequest("sendMessage",
            [
                "chat_id" => "$id",
                "text" => $message,
                "parse_mode" => "Markdown",
                'reply_markup' => json_encode([
                    'keyboard' => $keyboard,
                    'one_time_keyboard' => false,
                    'resize_keyboard' => true
                ])
            ]);

    }

    public static function profileMenu($bot, $message)
    {

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();


        $keyboard = [
            ["\xF0\x9F\x92\xABМои круги интересов", "\xF0\x9F\x8E\x88Новый круг интересов"],
            [
                ["text" => "\xF0\x9F\x93\x8DОтправить свой город",
                    "request_location" => true]
            ],
            ["	\xF0\x9F\x91\x88Главное меню"],
        ];


        $bot->sendRequest("sendMessage",
            [
                "chat_id" => "$id",
                "text" => $message,
                "parse_mode" => "Markdown",
                'reply_markup' => json_encode([
                    'keyboard' => $keyboard,
                    'one_time_keyboard' => false,
                    'resize_keyboard' => true
                ])
            ]);

    }

    public static function checkSex($bot)
    {

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();

        $user = User::where("telegram_chat_id", $id)->first();

        if (is_null($user->sex)) {

            $keyboard = [

                [
                    ["text" => "\xF0\x9F\x91\xA6 Мужчина (парень)", "callback_data" => "/i_am_man"],
                    ["text" => "\xF0\x9F\x91\xA7 Женщина (Девушка)", "callback_data" => "/i_am_woman"]
                ],
            ];

            $message = sprintf("А еще, нелохо было бы сказать свой пол?)");

            $bot->sendRequest("sendMessage",
                [
                    "chat_id" => "$id",
                    "text" => $message,
                    "parse_mode" => "Markdown",
                    'reply_markup' => json_encode([
                        'inline_keyboard' =>
                            $keyboard
                    ])
                ]);
        }
    }


    public static function start($bot)
    {


        $message = sprintf("
    Привет!
Это Кофе с Анонимом!\xE2\x98\x95

Уникальный проект, который соединяет двух участников сообщества, которые не против расширить свой кругозор и пообщаться с новым человеком на предстоящей неделе.

Вы уже в деле! А всё остальное можно узнать походу дела или из правил\xF0\x9F\x98\x89 

Наши правила вы сможете прочитать тут /rules
А сделать встречи более комфортными можно тут /settings
");
        Base::mainMenu($bot, $message);

        Base::checkSex($bot);
    }

}
