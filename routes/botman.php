<?php

use App\Circle;
use App\Http\Controllers\BotManController;
use App\IgnoreList;
use App\Mail\FeedbackMail;
use App\Meet;
use App\MeetEvents;
use App\User;
use App\UserInCircle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use NumberToWords\NumberToWords;

use App\Classes\Base;
use Telegram\Bot\Laravel\Facades\Telegram;
use Wkhooy\ObsceneCensorRus;

$botman = resolve('botman');

$botman->hears('/start', function ($bot) {
    Base::initUser($bot);
    Base::start($bot);
})->stopsConversation();

$botman->hears('/start ([0-9a-zA-Z-]{39})', BotManController::class . '@startWithDataConversation')->stopsConversation();

$botman->hears('/meet_poll_rating ([0-9a-zA-Z-]{38})', function ($bot, $data) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $index = substr($data, 0, 1);
    $meetId = substr($data, 1, 36);
    $ratingIndex = substr($data, 37, 1);

    $meet = Meet::where("id", $meetId)->first();

    if (is_null($meet)) {
        $bot->replay("Хм, не найдена встреча...");
        return;
    }

    if ($index == 1)
        $meet->rating_1 = $ratingIndex;
    else
        $meet->rating_2 = $ratingIndex;
    $meet->save();

    $keyboard = [
        [
            ["text" => "Понедельник" . ($meet->meet_day == 1 ? "\xE2\x9C\x85" : ""), "callback_data" => "/meet_poll_day " . $index . $meetId . "1"],
            ["text" => "Вторник" . ($meet->meet_day == 2 ? "\xE2\x9C\x85" : ""), "callback_data" => "/meet_poll_day " . $index . $meetId . "2"],
            ["text" => "Среда" . ($meet->meet_day == 3 ? "\xE2\x9C\x85" : ""), "callback_data" => "/meet_poll_day " . $index . $meetId . "3"],
        ],
        [
            ["text" => "Четверг" . ($meet->meet_day == 4 ? "\xE2\x9C\x85" : ""), "callback_data" => "/meet_poll_day " . $index . $meetId . "4"],
            ["text" => "Пятница" . ($meet->meet_day == 5 ? "\xE2\x9C\x85" : ""), "callback_data" => "/meet_poll_day " . $index . $meetId . "5"],
            ["text" => "Суббота" . ($meet->meet_day == 6 ? "\xE2\x9C\x85" : ""), "callback_data" => "/meet_poll_day " . $index . $meetId . "6"],
        ],
        [
            ["text" => "Воскресенье" . ($meet->meet_day == 7 ? "\xE2\x9C\x85" : ""), "callback_data" => "/meet_poll_day " . $index . $meetId . "7"],
        ]

    ];

    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => "В какой день прошла ваша последняя встреча?",
            "parse_mode" => "Markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' =>
                    $keyboard
            ])
        ]);

})->stopsConversation();

$botman->hears('/meet_poll_day ([0-9a-zA-Z-]{38})', function ($bot, $data) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $index = substr($data, 0, 1);
    $meetId = substr($data, 1, 36);
    $dayIndex = substr($data, 37, 1);

    $meet = Meet::where("id", $meetId)->first();

    if (is_null($meet)) {
        $bot->replay("Хм, не найдена встреча...");
        return;
    }

    if ($index == 1)
        $meet->meet_day = $dayIndex;
    else
        $meet->meet_day = $dayIndex;
    $meet->save();

    $keyboard = [
        [
            ["text" => "Оставить отзыв", "callback_data" => "/meet_poll_comment " . $index . $meetId],
        ]
    ];

    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => "Оставьте свой отзыв о данной встрече!",
            "parse_mode" => "Markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' =>
                    $keyboard
            ])
        ]);

})->stopsConversation();

$botman->hears('/meet_poll_comment ([0-9a-zA-Z-]{37})', BotManController::class . '@meetPollConversation')->stopsConversation();

$botman->hears('.*Главное меню|.*Передумал создавать', function ($bot) {

    $messages = [
        "Спасибо что пишите мне!",
        "Я вижу ваш интерес;)",
        "Вы точно в теме!)",
        "Такие сообщения радуют меня!",
        "Каждое сообщение будет услышано!",
    ];

    Base::initUser($bot);
    Base::start($bot, $messages[rand(0, count($messages) - 1)]);
})->stopsConversation();

$botman->hears('/send_message ([0-9a-zA-Z-]{36})', BotManController::class . '@sendMessageConversation');

$botman->hears('.*Круги по интересам', function ($bot) {
    if (!Base::isAdmin($bot)) {
        $bot->reply("Увы, данная возможность доступна только администратору");
        return;
    }
    Base::profileMenu($bot, "Ваш личный уголок\xF0\x9F\x8F\xA1\n/crules - *правила кругов интересов*\n/settings - *настройки комфорта* встречь");
})->stopsConversation();

$botman->hears('.*Мои круги интересов|/my_circles ([0-9+])|/my_circles', function ($bot, $page = 0) {

    Base::myInterestCircles($bot, $page);

})->stopsConversation();

$botman->hears('.*Как пользоваться?|/rules', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $bot->userStorage()->delete();

    $message = "
<b>Вы уже начали! А всё остальное лишь уточнения</b>:

\xF0\x9F\x94\xB8Укажите свой город пребывания для поиска встреч \xE2\x98\x95 с людьми поблизости;) 

\xF0\x9F\x94\xB8И сколько встреч в неделю?) \x31\xE2\x83\xA3, \x32\xE2\x83\xA3 или может быть \x33\xE2\x83\xA3?

Краткая инструкция: 

\xF0\x9F\x94\xB8Каждый раз вы будете получать от меня сообщение c контактами нового человека для встречи. 
\xF0\x9F\x94\xB8Напишите своему собеседнику в Telegram, чтобы договориться о встрече или звонке. 
\xF0\x9F\x94\xB8Время и место вы выбираете сами. 
\xF0\x9F\x94\xB8Договаривайтесь о встрече сразу. 
\xF0\x9F\x94\xB8Собеседник не отвечает? Напишите мне в чате, и я подберу нового собеседника. 
\xF0\x9F\x94\xB8За день до новой встречи я поинтересуюсь, участвуете ли вы, и как прошла ваша предыдущая встреча. 
\xF0\x9F\x94\xB8Если нет желания встречаться - напиши /stop.

Если есть вопросы или предложения — пишите мне в этом чате (голосовые и изображения тоже принимаются), ваш вопрос должен быть не меньше <b>10 символов!</b>. 

/settings - настройка комфорта встреч 

Желаете найти собеседника здесь и сейчас? - тогда отправляйте свою локацию \xF0\x9F\x93\x8D или транслируйте её \xE2\x8F\xB3 - пока вас видят - вы видите других\xF0\x9F\x98\x89";


    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => $message,
            "parse_mode" => "HTML",
        ]);

    Base::checkSex($bot);

})->stopsConversation();

$botman->hears('/crules', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $bot->userStorage()->delete();

    $message = "
    Некоторые особенности *кругов по интересам:*
\xF0\x9F\x94\xB8 _круг интересов можно создать => /create_
\xF0\x9F\x94\xB8 _круг интересов нельзя найти в поиске_
\xF0\x9F\x94\xB8 _круг интересов нельзя удалить после его создания, можно только покинуть_
\xF0\x9F\x94\xB8 _круг интересов всегда уникален даже если названия схожие_
\xF0\x9F\x94\xB8 _к кругу интересов можно присоединиться только по ссылке_
\xF0\x9F\x94\xB8 _пользователь может состоять в не ограниченном числе кругов_
\xF0\x9F\x94\xB8 _все люди в кругу интересов могут пригласить в него любого другого человека_
\xF0\x9F\x94\xB8 _круг интересов всегда анонимный и пользователи могут только: посмотреть название, описание и число людей в данном кругу_
\xF0\x9F\x94\xB8 _подбор собесдеников идет из тех, с кем еще не было встреч и тех, кто находится в ваших кругах (по умолчанию все люди без приглашения находятся в кругу \"Песочница\", пока не покинут круг)_
    ";


    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => $message,
            "parse_mode" => "Markdown",
        ]);

})->stopsConversation();

$botman->hears('/leave_circle ([0-9a-zA-Z-]{36})', function ($bot, $circleId) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $user = (User::with(["circles"])->where("telegram_chat_id", $id)->first());
    $user->circles()->detach([$circleId]);
    $user->save();

    $circle = Circle::where("id", $circleId)->first();

    $message = sprintf("Ваш круг интересов поменялся и это хорошо! Вы покинули круг интересов *$circle->title* \xE2\x98\x95");

    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => $message,
            "parse_mode" => "Markdown",
        ]);

})->stopsConversation();

$botman->hears('/create|.*Новый круг интересов', BotManController::class . '@startCircleConversation');

$botman->hears('/new_event|.*Добавить событие', BotManController::class . '@startNewEventConversation');

$botman->hears('/i_am_([a-zA-Z]+)', function ($bot, $type) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $user = User::where("telegram_chat_id", $id)->first();

    $user->sex = $type == "man" ? 1 : 0;
    $user->save();

    $message = sprintf("Да, хорошо что вы определились! Так будет проще подбирать собеседников\xF0\x9F\x98\x89 А если появится желание что-то опять изменить то /settings");

    Base::editOrSend($bot, json_encode([
        "on_edit" => Base::prepareSettingsText($user),
        "on_send" => $message
    ]));

})->stopsConversation();

$botman->hears('/restart', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $user = User::where("telegram_chat_id", $id)->first();

    $user->need_meeting = true;

    $user->save();

    $message = sprintf("Ура, новые встречи ждут!\xF0\x9F\x98\x84");

    Base::editOrSend($bot, json_encode([
        "on_edit" => Base::prepareSettingsText($user),
        "on_send" => $message
    ]));

})->stopsConversation();

$botman->hears('/stop', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $user = User::where("telegram_chat_id", $id)->first();

    $user->need_meeting = false;

    $user->save();

    $message = sprintf("На этой неделе вы не будете получать предложений о встрече, но вот со следующей недели... будь готов к новым и интересным знакомствам\xF0\x9F\x98\x84");

    Base::editOrSend($bot, json_encode([
        "on_edit" => Base::prepareSettingsText($user),
        "on_send" => $message

    ]));

})->stopsConversation();

$botman->hears('/prefer_([a-zA-Z]+)', function (\BotMan\BotMan\BotMan $bot, $type) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $user = User::where("telegram_chat_id", $id)->first();

    $counts = ["one" => 1, "two" => 2, "three" => 3];
    $prefers = ["man" => 1, "woman" => 0, "any" => 2];

    $user->meet_in_week = $counts[$type] ?? $user->meet_in_week;
    $user->prefer_meet_in_week = $prefers[$type] ?? $user->prefer_meet_in_week;

    $user->save();

    Base::editOrSend($bot, json_encode([
        "on_edit" => Base::prepareSettingsText($user),
        "on_send" => sprintf("Да, хорошо что вы определились! Так будет проще подбирать собеседников\xF0\x9F\x98\x89 А если появится желание что-то опять изменить то /settings")
    ]));


})->stopsConversation();

$botman->hears('.*Настройки?|/settings', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $user = User::where("telegram_chat_id", $id)->first();

    $res = Base::editOrSend($bot, json_encode([
        "on_send" => Base::prepareSettingsText($user)
    ]));

    $bot->userStorage()->save([
        'message_id' => \GuzzleHttp\json_decode($res->getContent())->result->message_id
    ]);

})->stopsConversation();

$botman->hears('/addition_settings', function (\BotMan\BotMan\BotMan $bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $user = User::where("telegram_chat_id", $id)->first();


    $res = Base::editOrSend($bot, json_encode([
        "on_send" => Base::prepareAdditionalText($user),
    ]));

    $bot->userStorage()->save([
        'message_id' => \GuzzleHttp\json_decode($res->getContent())->result->message_id
    ]);

})->stopsConversation();

$botman->hears('/ignore ([0-9a-zA-Z-]{36})', function ($bot, $userId) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $main_user = User::where("telegram_chat_id", $id)->first();
    $ignored_user = User::where("id", $userId)->first();
    \App\IgnoreList::create([
        'main_user_id' => $main_user->id,
        'ignored_user_id' => $ignored_user->id
    ]);

    $bot->reply("Данный собеседник не потревожит вас!");


})->stopsConversation();

$botman->hears('/in_range_([0-9]+)', function (\BotMan\BotMan\BotMan $bot, $range) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $range_array = [500, 1000, 2000, 3000];

    if (!in_array($range, $range_array)) {
        $bot->reply("Упс... мне кажется такой дистанции нет");
        return;
    }
    $user = User::where("telegram_chat_id", $id)->first();

    $settings = json_decode(is_null($user->settings) ?
        json_encode([
            "range" => 500,
            "time" => 30,
            "city" => 0
        ]) : $user->settings);


    $settings->range = $range;
    $user->settings = json_encode($settings);
    $user->save();

    Base::editOrSend($bot, json_encode([
        "on_edit" => Base::prepareAdditionalText($user),
        "on_send" => sprintf("Да, хорошо что вы определились! Так будет проще подбирать собеседников\xF0\x9F\x98\x89 А если появится желание что-то опять изменить то /addition_settings")

    ]));


})->stopsConversation();

$botman->hears('/in_time_([0-9]+)', function (\BotMan\BotMan\BotMan $bot, $time) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $time_array = [30, 60, 90, 120, 240];

    if (!in_array($time, $time_array)) {
        $bot->reply("Упс... мне кажется такого времени нет");
        return;
    }
    $user = User::where("telegram_chat_id", $id)->first();

    $settings = json_decode(is_null($user->settings) ?
        json_encode([
            "range" => 500,
            "time" => 30,
            "city" => 0
        ]) : $user->settings);


    $settings->time = $time;
    $user->settings = json_encode($settings);
    $user->save();

    $message = sprintf("Да, хорошо что вы определились! Так будет проще подбирать собеседников\xF0\x9F\x98\x89 А если появится желание что-то опять изменить то /addition_settings");
    Base::editOrSend($bot, json_encode([
        "on_edit" => Base::prepareAdditionalText($user),
        "on_send" => $message
    ]));
})->stopsConversation();

$botman->hears('/from_(city|any)', function (\BotMan\BotMan\BotMan $bot, $from) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();


    $user = User::where("telegram_chat_id", $id)->first();

    $settings = json_decode(is_null($user->settings) ?
        json_encode([
            "range" => 500,
            "time" => 30,
            "city" => 0
        ]) : $user->settings);


    $settings->city = $from == "city" ? 1 : 0;
    $user->settings = json_encode($settings);
    $user->save();

    $message = sprintf("Да, хорошо что вы определились! Так будет проще подбирать собеседников\xF0\x9F\x98\x89 А если появится желание что-то опять изменить то /addition_settings");
    Base::editOrSend($bot, json_encode([
        "on_edit" => Base::prepareAdditionalText($user),
        "on_send" => $message
    ]));
})->stopsConversation();


$botman->hears('/statistic|.*Статистика', function ($bot) {
    if (!Base::isAdmin($bot)) {
        $bot->reply("Раздел недоступен");
        return;
    }

    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $users_in_bd = User::all()->count();
    $circle_in_bd = Circle::all()->count();
    $events_in_bd = MeetEvents::all()->count();
    $active_events_in_bd = MeetEvents::where("date_end", ">", Carbon::now("+3"))->count();
    $spent_events_in_bd = MeetEvents::where("date_end", "<=", Carbon::now("+3"))->count();

    $last_added_events = MeetEvents::where("date_end", ">", Carbon::now("+3"))
        ->orderBy('id', 'desc')
        ->skip(0)
        ->take(20)
        ->get();

    $most_popular_circles = UserInCircle::with(["circle"])
        ->select(DB::raw('id, circle_id ,count(*) as count'))
        ->orderBy('count', 'desc')
        ->groupBy('circle_id')
        ->skip(0)
        ->take(20)
        ->get();

    $most_popular_circles_text = count($most_popular_circles) == 0 ? "Кругов нет" : "";
    $numberToWords = new NumberToWords();
    $numberTransformer = $numberToWords->getNumberTransformer('ru');

    foreach ($most_popular_circles as $index => $item)
        $most_popular_circles_text .= sprintf("%s) %s %s (%s) человек\n",
            $index + 1,
            $item->circle->title,
            $item->count,
            $numberTransformer->toWords($item->count)
        );


    $last_added_circles = Circle::orderBy('created_at', 'desc')
        ->take(20)
        ->skip(0)
        ->get();

    $last_added_circles_text = count($last_added_circles) == 0 ? "Кругов нет" : "";
    foreach ($last_added_circles as $index => $item)
        $last_added_circles_text .= sprintf("%s) %s _%s_\n",
            $index + 1,
            $item->title,
            $item->create_at
        );

    $last_added_events_text = count($last_added_events) == 0 ? "Событий нет" : "";
    foreach ($last_added_events as $index => $item)
        $last_added_events_text .= sprintf("%s) %s от %s до %s\n",
            $index + 1,
            $item->title,
            $item->date_start,
            $item->date_end
        );


    $users_in_bd_day = User::whereDate('created_at', Carbon::today())
        ->orderBy("id", "DESC")
        ->get()
        ->count();

    $message = sprintf("Всего пользователей в бд: %s
Пользователей за день: %s
Всего кругов интересов: %s
Всего событий: %s
Всего завершенных событий: %s
Всего активных событий: %s

20 самых популряных кругов:
_%s_
20 последних добавленных кругов:
_%s_
20 последних добавленных событий:
_%s_
    ",
        $users_in_bd,
        $users_in_bd_day,
        $circle_in_bd,
        $events_in_bd,
        $spent_events_in_bd,
        $active_events_in_bd,
        $most_popular_circles_text,
        $last_added_circles_text,
        $last_added_events_text
    );


    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => $message,
            "parse_mode" => "Markdown",

        ]);
})->stopsConversation();

$botman->hears('.*Список событий|.*Встречи в рамках событий|/meet_events ([0-9]+)', function ($bot, $page = 0) {
    Base::meetEventsList($bot, $page, Base::isAdmin($bot));
})->stopsConversation();

$botman->hears('/remove_event ([0-9]+)', function ($bot, $id) {
    if (!Base::isAdmin($bot)) {
        $bot->reply("Раздел недоступен");
        return;
    }

    $event = MeetEvents::where("id", $id)->first();

    if (is_null($event)) {
        $bot->reply("Хм, событие не найдено!");
        return;
    }
    $event->delete();

    $bot->reply("Событие успешно завершено!");

})->stopsConversation();

$botman->hears('/enter_event ([0-9]+)', function ($bot, $eventId) {

    $event = MeetEvents::find($eventId);

    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    if (is_null($event)) {
        $bot->reply("Хм, событие не найдено...");
        return;
    }

    $user = User::with(["events"])->where("telegram_chat_id", $id)->first();

    $on_event = \App\UserOnEvent::where("user_id", $user->id)
            ->where("event_id", $event->id)
            ->first() != null;

    if ($on_event) {
        $bot->reply("Хм, вы уже участвуете в событии!");
        return;
    }

    $user->events()->attach([$eventId]);
    $bot->reply("Отлично! Мы рады что вы участвуете в событии!");

})->stopsConversation();

$botman->hears('/exit_event ([0-9]+)', function ($bot, $eventId) {

    $event = MeetEvents::find($eventId);

    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    if (is_null($event)) {
        $bot->reply("Хм, событие не найдено...");
        return;
    }

    $user = User::with(["events"])->where("telegram_chat_id", $id)->first();
    Log::info("Test 1");
    $on_event = \App\UserOnEvent::where("user_id", $user->id)
            ->where("event_id", $event->id)
            ->first() != null;

    if (!$on_event) {
        $bot->reply("Хм, вы и так не участвуете в событии!");
        return;
    }
    Log::info("Test 2");
    $user->events()->detach([$eventId]);
    $bot->reply("Жаль, конечно, но это ваш выбор!)");

})->stopsConversation();

$botman->hears('.*Рассылка всем|/send_to_all', function ($bot) {
    $bot->reply("Массовая рассылка");
})->stopsConversation();

$botman->hears('.*Раздел администратора|/admin', function ($bot) {
    Base::adminMenu($bot, "Добро пожаловать в раздел Администратора");
})->stopsConversation();

$botman->receivesImages(function (\BotMan\BotMan\BotMan $bot, $images) {

    if (isset($bot->getMessage()->getPayload()["sender_chat"]))
        return;

    $bot->reply("Спасибо!) Ваше изображение отпавлено администратору;)");
    foreach ($images as $image) {

        $url = $image->getUrl(); // The direct url
        $title = $image->getTitle() ?? ''; // The title, if available
        $payload = $image->getPayload(); // The original payload

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();

        $user = User::where("telegram_chat_id", $id)->first();

        $bot->userStorage()->save([
            'image_url' => $url
        ]);

        $keyboard = [
            [
                ["text" => "\xE2\x9C\x8FНаписать пользователю", "url" => "https://t.me/" . env("APP_BOT_NAME") . "?start=" . "001" . $user->id],
            ],
        ];

        Telegram::sendPhoto([
            'chat_id' => env("TELEGRAM_ADMIN_CHANNEL"),
            "caption" => "$title\nОт пользователя: @" . $user->name,
            'parse_mode' => 'Markdown',
            'photo' => \Telegram\Bot\FileUpload\InputFile::create($url),
            'reply_markup' => json_encode([
                'inline_keyboard' =>
                    $keyboard
            ])
        ]);
    }
});

$botman->receivesAudio(function ($bot, $audios) {

    if (isset($bot->getMessage()->getPayload()["sender_chat"]))
        return;

    $bot->reply("Спасибо! Голосовое сообщение отправлено нашим администраторам;)");
    foreach ($audios as $audio) {

        $url = $audio->getUrl(); // The direct url
        $payload = $audio->getPayload(); // The original payload

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();

        $user = User::where("telegram_chat_id", $id)->first();


        $keyboard = [
            [
                ["text" => "\xE2\x9C\x8FНаписать пользователю", "url" => "https://t.me/" . env("APP_BOT_NAME") . "?start=" . "001" . $user->id],
            ],
        ];

        Telegram::sendAudio([
            'chat_id' => env("TELEGRAM_ADMIN_CHANNEL"),
            "caption" => "От пользователя: @" . $user->name,
            'parse_mode' => 'Markdown',
            'audio' => \Telegram\Bot\FileUpload\InputFile::create($url),
            'reply_markup' => json_encode([
                'inline_keyboard' =>
                    $keyboard
            ])
        ]);


    }
});

$botman->fallback(function (\BotMan\BotMan\BotMan $bot) {

    if (isset($bot->getMessage()->getPayload()["sender_chat"]))
        return;


    $messages = [
        "Спасибо что пишите мне!",
        "Я обязательно вскоре отвечу!",
        "Мне очень приятно оказаться полезным для Вас!",
        "Такие сообщения радуют меня!",
        "Каждое сообщение будет услышано!",
    ];

    Base::initUser($bot);

    $json = json_decode($bot->getMessage()->getPayload() ?? '');


    $find = false;
    if (isset($json->contact)) {
        $phone = $json->contact->phone_number;

        $bot->reply("Хм, а нам зачем он?)");
        $bot->sendRequest("sendMessage",
            [
                "chat_id" => env("TELEGRAM_ADMIN_CHANNEL"),
                "text" => "Хм, нам зачем-то отправили номер телефона: $phone",
                "parse_mode" => "HTML"
            ]);

    }

    if (isset($json->location)) {
        $location = $json->location;

        $data = YaGeo::setQuery($location->longitude . ',' . $location->latitude)->load();
        $city = $data->getResponse()->getLocality();

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();

        $user = User::where("telegram_chat_id", $id)->first();

        $settings = json_decode(is_null($user->settings) ?
            json_encode([
                "range" => 500,
                "time" => 30,
                "city" => 0
            ]) : $user->settings);

        $user->latitude = $location->latitude;
        $user->longitude = $location->longitude;
        $user->last_search = Carbon::now("+3");
        $user->city = $city ?? null;

        $user->save();

        $nearest = User::getNearestUsers(
            $user->id,
            $location->latitude,
            $location->longitude,
            $settings->range,
            $settings->time
        );

        if (count($nearest) === 0) {
            $message = "Увы, в данную минуту никого поблизости (в радиусе <b>$settings->range метров</b>) нет\xF0\x9F\x98\xA2, если в течении <b>$settings->time минут</b> кто-то объявится, мы дадим вам знать;)\n\n/addition_settings - настройка подбора";

            $bot->sendRequest("sendMessage",
                [
                    "chat_id" => "$id",
                    "text" => $message,
                    "parse_mode" => "HTML",
                    "disable_notification" => true
                ]);

            return;

        }

        $nearest_user = $nearest->random(1)->first();


        $message = "Добрый день! Собеседник хочет пригласить Вас на чашечку кофе! Свяжитесь с ним и назначте ему встречу:)";

        $nearest_user->last_search = null;
        $user->last_search = null;
        $nearest_user->save();
        $user->save();


        Meet::create([
            'id' => (string)Str::uuid(),
            'user1_id' => $user->id,
            'user2_id' => $nearest_user->id,
        ]);

        $code_1 = "007" . $user->id;
        $code_2 = "007" . $nearest_user->id;

        $bot->sendRequest("sendMessage",
            [
                "chat_id" => $nearest_user->telegram_chat_id,
                "text" => $message,
                "parse_mode" => "Markdown",
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ["text" => "Ответить собеседнику!", "callback_data" => "/send_message $user->id"]
                        ]
                    ]
                ])
            ]);


        $bot->sendRequest("sendMessage",
            [
                "chat_id" => $user->telegram_chat_id,
                "text" => $message,
                "parse_mode" => "Markdown",
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ["text" => "Ответить собеседнику!", "callback_data" => "/send_message $nearest_user->id"]
                        ]
                    ]
                ])
            ]);

        try {
            $bot->sendRequest("stopMessageLiveLocation",
                [
                    "chat_id" => $nearest_user->telegram_chat_id,
                ]);
        } catch (Exception $e) {
            $bot->reply("error");
        }

        try {
            $bot->sendRequest("stopMessageLiveLocation",
                [
                    "chat_id" => $id,
                ]);
        } catch (Exception $e) {
            $bot->reply("error");
        }

        $find = true;
    }

    if (!$find) {
        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();

        $username = $telegramUser->getUsername() ?? '';
        $lastName = $telegramUser->getLastName() ?? null;
        $firstName = $telegramUser->getFirstName() ?? null;

        $noName = is_null($lastName) || is_null($firstName);

        $text = $bot->getMessage()->getText();

        if (!ObsceneCensorRus::isAllowed($text)) {
            $bot->reply("Подобная лексика не может быть использована в культурном сообществе! Подберите другие слова!");
            return;
        }

        if (mb_strlen($text) > 10)
            Base::sendToAdminChannel($bot, "*Сообщение от* [" .
                (!$noName ? ($lastName ?? '') . " " . ($firstName ?? '') : $username)
                . "](tg://user?id=" . $id . ") :\n_" . $text . "_"
            );

        $bot->reply($messages[rand(0, count($messages) - 1)]);
    }
});
