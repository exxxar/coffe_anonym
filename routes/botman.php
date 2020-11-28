<?php

use App\Circle;
use App\Http\Controllers\BotManController;
use App\Mail\FeedbackMail;
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


$botman = resolve('botman');


$botman->hears('/start ([0-9a-zA-Z-]{39})', BotManController::class . '@startWithDataConversation');

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


$botman->hears('/start', function ($bot) {
    Base::initUser($bot);
    Base::start($bot);
})->stopsConversation();

$botman->hears('.*Круги по интересам', function ($bot) {
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
    
Вы уже в начали! А всё остальное лишь уточнения:

\xF0\x9F\x94\xB8Определитесь с какими собеседниками комфортнее общаться \xF0\x9F\x91\xA7 или \xF0\x9F\x91\xA6, а может быть это и вовсе не важно?
\xF0\x9F\x94\xB8Укажите свой регион проживания - ведь проще выпить чашечку \xE2\x98\x95 с людьми поблизости;) 
\xF0\x9F\x94\xB8И сколько встреч в неделю желаете?) \x31\xE2\x83\xA3, \x32\xE2\x83\xA3 или может быть \x33\xE2\x83\xA3?    

Краткая инструкция:
\xF0\x9F\x94\xB8Каждый раз вы будете получать от меня сообщение c контактами нового человека для встречи.
\xF0\x9F\x94\xB8Напишите своему собеседнику в Telegram, чтобы договориться о встрече или звонке. 
\xF0\x9F\x94\xB8Время и место вы выбираете сами.
\xF0\x9F\x94\xB8Не откладывайте, договаривайтесь о встрече сразу.
\xF0\x9F\x94\xB8Собеседник не отвечает? Напишите мне в чате, и я подберу нового собеседника.   
\xF0\x9F\x94\xB8За день до новой встречи я поинтересуюсь, участвуете ли вы, и как прошла ваша предыдущая встреча.
\xF0\x9F\x94\xB8Если нет желания встречаться - напиши /stop.\n

Если есть вопросы или предложения — пишите мне в этом чате (голосовые и изображения тоже принимаются), ваш вопрос должен быть не меньше *10 символов!*.

/settings - настройка комфорта встреч 
/crules - правила кругов интересов

Желаете найти собеседника здесь и сейчас? - тогда отправляйте свою локацию \xF0\x9F\x93\x8D или транслируйте её \xE2\x8F\xB3 - пока вас видят - вы видите других\xF0\x9F\x98\x89
    ";


    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => $message,
            "parse_mode" => "Markdown",
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
    $prefers = ["man" => 1, "woman" => 2, "any" => 3];

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
            "time" => 5,
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

    $time_array = [5, 10, 15];

    if (!in_array($time, $time_array)) {
        $bot->reply("Упс... мне кажется такого времени нет");
        return;
    }
    $user = User::where("telegram_chat_id", $id)->first();

    $settings = json_decode(is_null($user->settings) ?
        json_encode([
            "range" => 500,
            "time" => 5,
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

$botman->hears('/city_([a-zA-Z]+)', function (\BotMan\BotMan\BotMan $bot, $type) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    Log::info(print_r($bot->getMessage()->getPayload(), true));

    $city_array = ["my" => 0, "all" => 1];

    if (!key_exists($type, $city_array)) {
        $bot->reply("Упс... мне кажется такого варианта нет");
        return;
    }
    $user = User::where("telegram_chat_id", $id)->first();

    $settings = json_decode(is_null($user->settings) ?
        json_encode([
            "range" => 500,
            "time" => 5,
            "city" => 0
        ]) : $user->settings);


    $settings->city = $city_array[$type];
    $user->settings = json_encode($settings);
    $user->save();

    $message = sprintf("Да, хорошо что вы определились! Так будет проще подбирать собеседников\xF0\x9F\x98\x89 А если появится желание что-то опять изменить то /addition_settings");
    Base::editOrSend($bot, json_encode([
        "on_edit" => Base::prepareAdditionalText($user),
        "on_send" => $message
    ]));


})->stopsConversation();

$botman->hears('.*Раздел администратора|/admin', function ($bot) {

    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    if (!Base::isAdmin($bot)) {
        $bot->reply("Раздел недоступен");
        return;
    }

    $users_in_bd = User::all()->count();
    $circle_in_bd = Circle::all()->count();

    $most_popular_circles = Circle::orderBy('count', 'desc')
        ->select(DB::raw('id, title,count(*) as count'))
        ->groupBy('count')
        ->take(20)
        ->skip(0)
        ->get();


    $most_popular_circles_text = "";
    $numberToWords = new NumberToWords();
    $numberTransformer = $numberToWords->getNumberTransformer('ru');

    foreach ($most_popular_circles as $index => $item)
        $most_popular_circles_text .= sprintf("%s) %s %s (%s) человек\n",
            $index + 1,
            $item->title,
            $item->count,
            $numberTransformer->toWords($item->count)
        );


    $last_added_circles = Circle::orderBy('created_at', 'desc')
        ->take(20)
        ->skip(0)
        ->get();

    $last_added_circles_text = "";
    foreach ($last_added_circles as $index => $item)
        $last_added_circles_text .= sprintf("%s) %s _%s_\n",
            $index + 1,
            $item->title,
            $item->create_at
        );

    $users_in_bd_day = User::whereDate('created_at', Carbon::today())
        ->orderBy("id", "DESC")
        ->get()
        ->count();

    $message = sprintf("Всего пользователей в бд: %s
    Пользователей за день:%s
    Всего кругов интересов:%s
    20 самых популряных кругов:
    _%s_
    
    20 последних добавленных кругов:
    _%s_
    ",
        $users_in_bd,
        $users_in_bd_day,
        $circle_in_bd,
        $most_popular_circles_text,
        $last_added_circles_text
    );

    $keyboard = [

        [
            ["text" => "Рассылка всем", "callback_data" => "/send_to_all"]
        ],
        [
            ["text" => "Управление событиями", "callback_data" => "/meetevents"]
        ],

    ];

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

    $json = json_decode($bot->getMessage()->getPayload());


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

        $data = YaGeo::setQuery($location->latitude . ',' . $location->longitude)->load();
        $city = $data->getResponse()->getLocality();

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();

        $user = User::where("telegram_chat_id", $id)->first();
        $user->location = json_encode([
            "latitude" => $location->latitude,
            "longitude" => $location->longitude,
            "city" => $city ?? null,
            "last_seen" => new Carbon("+3")
        ]);

        $nearest = User::getNearestUsers($location->latitude, $location->longitude);


        if (count($nearest) === 0) {
            $message = "Увы, в данную минуту никого поблизости нет\xF0\x9F\x98\xA2, если в течении <b>5 минут</b> кто-то объявится, мы дадим вам знать;)\n\n/addition_settings - настройка подбора";

            $bot->sendRequest("sendMessage",
                [
                    "chat_id" => "$id",
                    "text" => $message,
                    "parse_mode" => "HTML",
                    "disable_notification" => true
                ]);

        } else {

            $nearest_user = $nearest->random(1)->first();
            $message_1 = "Привет! Я ищу себе собеседника на \xF0\x9F\x98\x8B\nНапиши мне @" . $user->name;
            $message_2 = "Привет! Я ищу себе собеседника на \xF0\x9F\x98\x8B\nНапиши мне @" . $nearest_user->name;

            $nu_location = json_decode($nearest_user->location);
            $nu_location->last_seen = null;

            $u_location = json_decode($user->location);
            $u_location->last_seen = null;

            $nearest_user->location = json_encode($nu_location);
            $user->location = json_encode($u_location);
            $nearest_user->save();
            $user->save();

            $bot->sendRequest("sendMessage",
                [
                    "chat_id" => $nearest_user->telegram_chat_id,
                    "text" => $message_1,
                    "parse_mode" => "Markdown",
                ]);

            $bot->sendRequest("stopMessageLiveLocation",
                [
                    "chat_id" => $nearest_user->telegram_chat_id,
                ]);


            $bot->sendRequest("sendMessage",
                [
                    "chat_id" => $id,
                    "text" => $message_2,
                    "parse_mode" => "Markdown",
                ]);

            $bot->sendRequest("stopMessageLiveLocation",
                [
                    "chat_id" => $id,
                ]);


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
        if (mb_strlen($text) > 10)
            Base::sendToAdminChannel($bot, "*Сообщение от* [" .
                (!$noName ? ($lastName ?? '') . " " . ($firstName ?? '') : $username)
                . "](tg://user?id=" . $id . ") :\n_" . $text . "_"
            );

        $bot->reply($messages[rand(0, count($messages) - 1)]);
    }
});
