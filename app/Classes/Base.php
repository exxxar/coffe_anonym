<?php


namespace App\Classes;


use App\Circle;
use App\MeetEvents;
use App\User;
use App\UserInCircle;
use App\UserOnEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NumberToWords\NumberToWords;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Laravel\Facades\Telegram;

class Base
{
    public static function updateStatus($bot){
        if (!Base::isValid($bot))
            return;

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();

        $user = User::where("telegram_chat_id",$id)->first();

        if (!is_null($user)){
            $user->updated_at = Carbon::now("+3");
            $user->save();

            return;
        }

        Base::initUser($bot);


    }

    public static function initUser($bot, $circleId = null)
    {
        if (!Base::isValid($bot))
            return;

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();

        $username = $telegramUser->getUsername() ?? '';
        $lastName = $telegramUser->getLastName() ?? '';
        $firstName = $telegramUser->getFirstName() ?? '';

        $is_first = false;
        $user = User::where("telegram_chat_id", $id)->first();
        if (is_null($user)) {
            $userId = (string)Str::uuid();
            User::create([
                'id' => $userId,
                'name' => $username ?? $userId,
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

    public static function inviteToCircle($bot, $circleId)
    {
        if (!Base::isValid($bot))
            return;

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();


        $user = User::where("telegram_chat_id", $id)->first();

        $circle = Circle::where("id", env("FIRST_CIRCLE_ID"))->first();

        if (is_null($circle)) {
            $bot->reply("Хм, я почему-то не могу найти этот круг по интересам, но вы можете его создать => /create");
            return;
        }

        $userInCircle = UserInCircle::where("circle_id", $circleId)
                ->where("user_id", $user->id)
                ->first() != null;

        if ($userInCircle) {
            $bot->reply("Вы и так в этом кругу, но вы всегда можете создать что-то своё => /create");
            return;
        }


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

        $message = sprintf("[%s](https://t.me/%s?start=%s) - теперь это и ваш круг по интересам! Делитесь этим сообщением и расширяте круг людей\xF0\x9F\x98\x89",
            Str::ucfirst($circle->title),
            env("APP_BOT_NAME"),
            $code
        );
        $bot->sendRequest("sendMessage",
            [
                "chat_id" => $id,
                "parse_mode" => "markdown",
                "text" => $message,
                "disable_web_page_preview" => true,
                'reply_markup' => json_encode([
                    'inline_keyboard' =>
                        $keyboard
                ])
            ]);


    }

    public static function isAdmin($bot)
    {
        if (!Base::isValid($bot))
            return;

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();
        $user = User::where("telegram_chat_id", $id)->first();
        return $user->is_admin ? true : false;
    }

    public static function sendToAdminChannel($bot, $message, $need_keyboard = true)
    {
        if (!Base::isValid($bot))
            return;

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();

        $user = User::where("telegram_chat_id", $id)->first();


        $keyboard = [

        ];

        if ($need_keyboard)
            array_push($keyboard, [
                ["text" => "\xE2\x9C\x8FНаписать пользователю", "url" => "https://t.me/" . env("APP_BOT_NAME") . "?start=" . "001" . $user->id],
                ["text" => "\xE2\x98\x95Организовать встречу", "url" => "https://t.me/" . env("APP_BOT_NAME") . "?start=" . "002" . $user->id]
            ]);

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
        if (!Base::isValid($bot))
            return;

        Base::updateStatus($bot);

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();


        $keyboard = [];

        $now = date('Y-m-d');
        $events = \App\MeetEvents::where('date_end', '>', $now)
            ->get();

        if (count($events) > 0)
            array_push($keyboard, ["\xE2\xAD\x90Встречи в рамках событий"]);

        if (User::all()->count() > 1000)
            array_push($keyboard, [
                ["text" => "\xF0\x9F\x93\x8DМоментальная встреча",
                    "request_location" => true]
            ]);

        array_push($keyboard, ["\xF0\x9F\x92\xABКруги по интересам"]);
        array_push($keyboard, ["\xE2\x98\x9DКак пользоваться?"]);

        if (Base::isAdmin($bot))
            array_push($keyboard, ["\xF0\x9F\x93\x8AРаздел администратора"]);

        $bot->sendRequest("sendMessage",
            [
                "chat_id" => "$id",
                "text" => $message,
                "parse_mode" => "HTML",
                'reply_markup' => json_encode([
                    'keyboard' => $keyboard,
                    'one_time_keyboard' => false,
                    'resize_keyboard' => true
                ])
            ]);

    }

    public static function adminMenu($bot, $message)
    {
        if (!Base::isValid($bot))
            return;

        Base::updateStatus($bot);

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();


        if (!Base::isAdmin($bot)) {
            $bot->reply("Раздел недоступен");
            return;
        }

        $keyboard = [];

        array_push($keyboard, ["\xF0\x9F\x92\xABСтатистика"]);
        array_push($keyboard, ["\xF0\x9F\x93\x8BСписок событий", "\xF0\x9F\x93\x86Добавить событие"]);
        /*array_push($keyboard, ["\xF0\x9F\x92\xACРассылка всем"]);*/
        array_push($keyboard, ["\xF0\x9F\x91\x88Главное меню"]);

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

        if (!Base::isValid($bot))
            return;

        Base::updateStatus($bot);

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
        if (!Base::isValid($bot))
            return;

        Base::updateStatus($bot);

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();


        Base::myInterestCircles($bot);

        $keyboard = [
            // ["\xF0\x9F\x92\xABМои круги интересов"],
            ["\xF0\x9F\x8E\x88Новый круг интересов"],
            /*[
                ["text" => "\xF0\x9F\x93\x8DОтправить свой город",
                    "request_location" => true]
            ],*/
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
        if (!Base::isValid($bot))
            return;

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


    public static function myInterestCircles($bot, $page = 0)
    {
        if (!Base::isValid($bot))
            return;

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();

        $user = (User::with(["circles"])->where("telegram_chat_id", $id)->first());
        $circles = $user->circles()
            ->take(env("CIRCLES_PER_PAGE"))
            ->skip(env("CIRCLES_PER_PAGE") * $page)
            ->get();

        if (count($circles) == 0) {

            $bot->reply("Эх, а вы еще не состоите ни в одном кругу интересов... Может быть вы создадите его? /create");
            return;
        }
        foreach ($circles as $circle) {

            $code = "003" . $circle->id;

            $keyboard = [
                [
                    ["text" => "\xE2\x9D\x8EВыйти из круга", "callback_data" => "/leave_circle " . $circle->id],
                ],
            ];

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
                    "chat_id" => "$id",
                    "text" => $message,
                    "parse_mode" => "Markdown",
                    "disable_web_page_preview" => true,
                    'reply_markup' => json_encode([
                        'inline_keyboard' =>
                            $keyboard
                    ])
                ]);

        }


        $inline_keyboard = [];

        if ($page == 0 && count($circles) == env("CIRCLES_PER_PAGE"))
            array_push($inline_keyboard, ['text' => "Следующая страница", 'callback_data' => "/my_circles " . ($page + 1)]);
        if ($page > 0) {
            if (count($circles) == 0) {
                array_push($inline_keyboard, ['text' => "Предидущая страница", 'callback_data' => "/my_circles " . ($page - 1)]);
            }
            if (count($circles) == env("CIRCLES_PER_PAGE")) {
                array_push($inline_keyboard, ['text' => "Предидущая страница", 'callback_data' => "/my_circles " . ($page - 1)]);
                array_push($inline_keyboard, ['text' => "Следующая страница", 'callback_data' => "/my_circles " . ($page + 1)]);
            }
            if (count($circles) > 0 && count($circles) < env("CIRCLES_PER_PAGE")) {
                array_push($inline_keyboard, ['text' => "Предидущая страница", 'callback_data' => "/my_circles " . ($page - 1)]);
            }
        }

        if (count($inline_keyboard) > 0)
            $bot->sendRequest("sendMessage",
                [
                    "chat_id" => "$id",
                    "text" => "Ну что, может посмотрим что-то еще?",
                    "parse_mode" => "Markdown",
                    'reply_markup' => json_encode([
                        'inline_keyboard' =>
                            $inline_keyboard
                    ])
                ]);

    }

    public static function meetEventsList($bot, $page = 0, $is_admin = false)
    {
        if (!Base::isValid($bot))
            return;

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();

        $user = User::where("telegram_chat_id", $id)->first();

        $events = MeetEvents::where("date_end", ">", Carbon::now("+3"))
            ->take(env("EVENTS_PER_PAGE"))
            ->skip(env("EVENTS_PER_PAGE") * $page)
            ->get();

        if (count($events) == 0) {
            $bot->reply("Эх, глобальных событий еще нет...");
            return;
        }
        foreach ($events as $event) {

            $on_event = UserOnEvent::where("event_id", $event->id)
                    ->where("user_id", $user->id)
                    ->first() != null;

            $keyboard = [
            ];

            if ($on_event)
                array_push($keyboard, [["text" => "	\xE2\x9D\x8EНе участвовать", "callback_data" => "/exit_event " . $event->id]]);
            else
                array_push($keyboard, [["text" => "\xE2\x9C\x85Участовать", "callback_data" => "/enter_event " . $event->id]]);

            if ($is_admin) {
                array_push($keyboard, [
                    ["text" => "\xE2\x9D\x8EУдалить событие", "callback_data" => "/remove_event " . $event->id],
                ]);
            }

            $people_in_event = UserOnEvent::select(DB::raw('id, event_id ,count(*) as count'))
                ->where("event_id", $event->id)
                ->orderBy('count', 'desc')
                ->groupBy('event_id')
                ->first();

            $message = sprintf("*%s*\n_%s_\nУчаствует в событии: *%s*\nНачало: *%s*\nКонец: %s",
                $event->title,
                $event->description,
                ($people_in_event->count ?? 0),
                $event->date_start,
                $event->date_end
            );


            Telegram::sendPhoto([
                'chat_id' => $id,
                "caption" => "$message",
                'parse_mode' => 'Markdown',
                'photo' => \Telegram\Bot\FileUpload\InputFile::create($event->image_url ?? "https://sun9-27.userapi.com/impg/1CcReZ74SVCCfAwMFGYM0QdsdxC7DnQ4cJzHZA/fn56wSggReQ.jpg?size=844x834&quality=96&proxy=1&sign=4eaa549b2320c609885c4b89b2d3ba56"),
                'reply_markup' => json_encode([
                    'inline_keyboard' =>
                        $keyboard
                ])
            ]);


        }


        $inline_keyboard = [];

        if ($page == 0 && count($events) == env("EVENTS_PER_PAGE"))
            array_push($inline_keyboard, ['text' => "Следующая страница", 'callback_data' => "/meet_events " . ($page + 1)]);
        if ($page > 0) {
            if (count($events) == 0) {
                array_push($inline_keyboard, ['text' => "Предидущая страница", 'callback_data' => "/meet_events " . ($page - 1)]);
            }
            if (count($events) == env("EVENTS_PER_PAGE")) {
                array_push($inline_keyboard, ['text' => "Предидущая страница", 'callback_data' => "/meet_events " . ($page - 1)]);
                array_push($inline_keyboard, ['text' => "Следующая страница", 'callback_data' => "/meet_events " . ($page + 1)]);
            }
            if (count($events) > 0 && count($events) < env("EVENTS_PER_PAGE")) {
                array_push($inline_keyboard, ['text' => "Предидущая страница", 'callback_data' => "/meet_events " . ($page - 1)]);
            }
        }

        if (count($inline_keyboard) > 0)
            $bot->sendRequest("sendMessage",
                [
                    "chat_id" => "$id",
                    "text" => "Ну что, может посмотрим что-то еще?",
                    "parse_mode" => "Markdown",
                    'reply_markup' => json_encode([
                        'inline_keyboard' =>
                            $inline_keyboard
                    ])
                ]);

    }

    public static function start($bot, $message = null)
    {
        if (!Base::isValid($bot))
            return;

        $message = is_null($message) ? sprintf("
    Привет!
Это Кофе с Анонимом!\xE2\x98\x95

Уникальный проект, который соединяет двух участников сообщества, которые не против расширить свой кругозор и пообщаться с новым человеком на предстоящей неделе.

Вы уже в деле! А всё остальное можно узнать походу дела или из правил\xF0\x9F\x98\x89 

Наши правила вы сможете прочитать тут /rules
А сделать встречи более комфортными можно тут /settings
") : $message;

        Base::mainMenu($bot, $message);

        Base::checkSex($bot);
    }


    public static function prepareAdditionalText($user)
    {


        $settings = json_decode(is_null($user->settings) ?
            json_encode([
                "range" => 500,
                "time" => 5,
                "city" => 0
            ]) : $user->settings);

        $message = "
Предлагаем вам дополнительно настроить параметры гео-локации:

Настраиваем радиус поиска ближайшего собеседника
/in_range_500 - до 500 метров " . ($settings->range == 500 ? "\xE2\x9C\x85" : "") . "
/in_range_1000 - до 1 км " . ($settings->range == 1000 ? "\xE2\x9C\x85" : "") . "
/in_range_2000 - до 2х км " . ($settings->range == 2000 ? "\xE2\x9C\x85" : "") . "
/in_range_3000 - до 3х км " . ($settings->range == 3000 ? "\xE2\x9C\x85" : "") . "

Настравиваем время ожидания подбора собеседника

/in_time_5 - до 5 минут " . ($settings->time == 5 ? "\xE2\x9C\x85" : "") . "
/in_time_10 - до 10 минут " . ($settings->time == 10 ? "\xE2\x9C\x85" : "") . "
/in_time_15 - до 15 минут " . ($settings->time == 15 ? "\xE2\x9C\x85" : "") . "

/settings - все оставшиеся настройки
    ";

        return $message;
    }

    public static function prepareSettingsText($user)
    {

        $message = "
Мы не сводим половинки, но мы помогаем Вам провести время в компании интересного собеседника!

Предлагаем Вам выбрать предпочтительного собеседника:
/prefer_man - предпочтительно мужчины (парни) " . ($user->prefer_meet_in_week == 1 ? "\xE2\x9C\x85" : "") . "
/prefer_woman - предпочтительно женщины (девушки) " . ($user->prefer_meet_in_week == 2 ? "\xE2\x9C\x85" : "") . "
/prefer_any - любой собеседник " . ($user->prefer_meet_in_week == 3 ? "\xE2\x9C\x85" : "") . "

Также рекомендуем определиться с числом встречь в неделю!

/prefer_one - максимум одна встреча в неделю " . ($user->meet_in_week == 1 ? "\xE2\x9C\x85" : "") . "
/prefer_two - одна или две встречи в неделю " . ($user->meet_in_week == 2 ? "\xE2\x9C\x85" : "") . "
/prefer_three - от одной до трёх встреч " . ($user->meet_in_week == 3 ? "\xE2\x9C\x85" : "") . "

А так же, вы всегда можете отдохнуть от встреч (или возобновить встречи)

" . (!$user->need_meeting ?
                "/restart - появилось желание с кем-либо встретиться!" :
                "/stop - больше нет желания с кем-либо встречаться (в течении недели)"
            ) . "

Если вдруг вы ошибочного выбрали свой собственный пол, то его тоже легко можно поменять:
/i_am_man - собседники будут воспринимать вас как мужчину (парня) " . ($user->sex == 1 ? "\xE2\x9C\x85" : "") . "
/i_am_woman - собседники будут воспринимать вас как женщину (девушку) " . ($user->sex == 0 ? "\xE2\x9C\x85" : "") . "

/addition_settings - дополнительные настройки
    ";

        return $message;
    }

    public static function editOrSend($bot, string $message)
    {
        if (!Base::isValid($bot))
            return;

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();

        $message = json_decode($message);

        $message_id = $bot->userStorage()->get('message_id') ?? null;

        if (!is_null($message_id) && isset($message->on_edit)) {
            return $bot->sendRequest("editMessageText",
                [
                    "message_id" => "$message_id",
                    "text" => $message->on_edit ?? '',
                    "parse_mode" => "HTML",
                ]);
        }

        return $bot->sendRequest("sendMessage",
            [
                "chat_id" => "$id",
                "text" => $message->on_send ?? '',
                "parse_mode" => "HTML",
            ]);

    }

    public static function isValid($bot)
    {
        return (!isset($bot->getMessage()->getPayload()["sender_chat"]));

    }
}
