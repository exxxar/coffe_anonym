<?php

use App\Circle;
use App\Http\Controllers\BotManController;
use App\Mail\FeedbackMail;
use App\User;
use App\UserInCircle;
use Carbon\Carbon;
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

    $message = "
Как начать:
\xF0\x9F\x94\xB8Определитесь с какими собеседниками комфортнее общаться \xF0\x9F\x91\xA7 или \xF0\x9F\x91\xA6, а может быть это и вовсе не важно?
\xF0\x9F\x94\xB8Укажите свой регион проживания - ведь проще выпить чашечку \xE2\x98\x95 с людьми поблизости;) 
\xF0\x9F\x94\xB8И сколько встреч в неделю желаете?) \x31\xE2\x83\xA3, \x32\xE2\x83\xA3 или может быть \x33\xE2\x83\xA3?    

Краткая инструкция:
\xF0\x9F\x94\xB8Каждый раз вы будете получать от меня сообщение c контактами нового человека для встречи.
\xF0\x9F\x94\xB8Напишите своему собеседнику в Telegram, чтобы договориться о встрече или звонке. 
\xF0\x9F\x94\xB8Время и место вы выбираете сами.
\xF0\x9F\x94\xB8е откладывайте, договаривайтесь о встрече сразу.
\xF0\x9F\x94\xB8Собеседник не отвечает? Напишите мне в чате, и я подберу нового собеседника.   
\xF0\x9F\x94\xB8За день до новой встречи я поинтересуюсь, участвуете ли вы, и как прошла ваша предыдущая встреча.
\xF0\x9F\x94\xB8Если нет желания встречаться - напиши /stop.\n

Если есть вопросы или предложения — пишите мне в этом чате (голосовые и изображения тоже принимаются).

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

    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => $message,
            "parse_mode" => "Markdown",
        ]);

})->stopsConversation();

$botman->hears('/restart', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $user = User::where("telegram_chat_id", $id)->first();

    $user->need_meeting = true;

    $user->save();

    $message = sprintf("Ура, новые встречи ждут!\xF0\x9F\x98\x84");

    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => $message,
            "parse_mode" => "Markdown",
        ]);

})->stopsConversation();
$botman->hears('/stop', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $user = User::where("telegram_chat_id", $id)->first();

    $user->need_meeting = false;

    $user->save();

    $message = sprintf("На этой неделе вы не будете получать предложений о встрече, но вот со следующей недели... будь готов к новым и интересным знакомствам\xF0\x9F\x98\x84");

    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => $message,
            "parse_mode" => "Markdown",
        ]);

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

    $message = sprintf("Да, хорошо что вы определились! Так будет проще подбирать собеседников\xF0\x9F\x98\x89 А если появится желание что-то опять изменить то /settings");

    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => $message,
            "parse_mode" => "Markdown",
        ]);

})->stopsConversation();

$botman->hears('.*Настройки?|/settings', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $user = User::where("telegram_chat_id", $id)->first();

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

    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => $message,
        ]);

})->stopsConversation();

$botman->hears('/addition_settings', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $user = User::where("telegram_chat_id", $id)->first();

    $message = "
Предлагаем вам дополнительно настроить параметры гео-локации:

Настраиваем радиус поиска ближайшего собеседника
/in_range_500 - до 500 метров " . (true ? "\xE2\x9C\x85" : "") . "
/in_range_1000 - до 1 км " . (false ? "\xE2\x9C\x85" : "") . "
/in_range_2000 - до 2х км " . (false ? "\xE2\x9C\x85" : "") . "
/in_range_3000 - до 3х км " . (false ? "\xE2\x9C\x85" : "") . "

Настравиваем время ожидания подбора собеседника

/in_time_5 - до 5 минут " . (true ? "\xE2\x9C\x85" : "") . "
/in_time_10 - до 10 минут " . (false ? "\xE2\x9C\x85" : "") . "
/in_time_15 - до 15 минут " . (false ? "\xE2\x9C\x85" : "") . "

Каких собеседников подбираем?

/city_my - только из моего города " . (false ? "\xE2\x9C\x85" : "") . "
/city_all - со всех городов " . (true ? "\xE2\x9C\x85" : "") . "
    ";

    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => $message,
        ]);

})->stopsConversation();


$botman->hears('.*Раздел администратора|/admin', function ($bot) {

    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    if (!Base::isAdmin($bot)) {
        $bot->reply("Раздел недоступен");
        return;
    }

    $users_in_bd = User::all()->count();
    $with_phone_number = User::whereNotNull("phone")->get()->count();
    $without_phone_number = User::whereNull("phone")->get()->count();

    $users_in_bd_day = User::whereDate('created_at', Carbon::today())
        ->orderBy("id", "DESC")
        ->get()
        ->count();

    $message = sprintf("Всего пользователей в бд: %s\nВсего оставили номер телефона:%s\nКол-во не оставивших телефон:%s \nПользователей за день:%s",
        $users_in_bd,
        $with_phone_number,
        $without_phone_number,
        $users_in_bd_day
    );

    $keyboard = [

        [
            ["text" => "Рассылка всем", "callback_data" => "/send_to_all"]
        ],
        [
            ["text" => "Список пользователей (с телефонами)", "callback_data" => "/users_list_1"]
        ],
        [
            ["text" => "Список пользователей (без телефонов)", "callback_data" => "/users_list_2"]
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

$botman->hears('.*Тех. поддержка|.*заявка.*', BotManController::class . '@startRequestWithMessage')->stopsConversation();

$botman->receivesImages(function ($bot, $images) {

    if (is_null($bot->getUser()))
        return;

    $bot->reply("Спасибо!) Ваше изображение отпавлено администратору;)");
    foreach ($images as $image) {

        $url = $image->getUrl(); // The direct url
        $title = $image->getTitle(); // The title, if available
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
            "caption" => "От пользователя: @" . $user->name,
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

    if (is_null($bot->getUser()))
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
    $messages = [
        "Спасибо что пишите мне!",
        "Я обязательно вскоре отвечу!",
        "Мне очень приятно оказаться полезным для Вас!",
        "Такие сообщения радуют меня!",
        "Каждое сообщение будет услышано!",
    ];

    Base::initUser($bot);

    $json = json_decode($bot->getMessage()->getPayload());

    Log::info(print_r($json, true));

    $find = false;
    if (isset($json->contact)) {
        $phone = $json->contact->phone_number;

        $tmp_phone = str_replace(["(", ")", "-", " "], "", $phone);

        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();

        $bot->reply("Заявка успешно принята! Мы свяжемся с вами в течение 10 минут!");

        $user = User::where("telegram_chat_id", $id)->first();
        $phones = json_decode($user->phone) ?? [];
        if (!in_array($tmp_phone, $phones)) {
            array_push($phones, $tmp_phone);
            $user->phone = json_encode($phones);
            $user->save();
        }


        $find = true;
        $toEmail = env('MAIL_ADMIN');
        Mail::to($toEmail)->send(new FeedbackMail([
            "name" => ($telegramUser->getLastName() . " " . $telegramUser->getFirstName() ?? $telegramUser->getUsername() ?? $telegramUser->getId()),
            "phone" => $tmp_phone,
            "date" => (Carbon::now("+3"))
        ]));
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
                ]);

        } else {

            $nearest_user = $nearest->random(1)->first();
            $message_1 = "Поблизости есть достойный собеседник, который тоже ищет встречи\xF0\x9F\x98\x8B\nНапишите ему @" . $user->name;
            $message_2 = "Поблизости есть достойный собеседник, который тоже ищет встречи\xF0\x9F\x98\x8B\nНапишите ему @" . $nearest_user->name;

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

            $bot->sendRequest("sendMessage",
                [
                    "chat_id" => $id,
                    "text" => $message_2,
                    "parse_mode" => "Markdown",
                ]);
        }


        ///todo: отправлять аудио и фото сообщение


        $find = true;
    }

    if (!$find) {
        $telegramUser = $bot->getUser();
        $id = $telegramUser->getId();

        $username = $telegramUser->getUsername() ?? '';
        $lastName = $telegramUser->getLastName() ?? null;
        $firstName = $telegramUser->getFirstName() ?? null;

        $noName = is_null($lastName) || is_null($firstName);

        Base::sendToAdminChannel($bot, "*Сообщение от* [" .
            (!$noName ? ($lastName ?? '') . " " . ($firstName ?? '') : $username)
            . "](tg://user?id=" . $id . ") :\n_" . $bot->getMessage()->getText() . "_"
        );

        $bot->reply($messages[rand(0, count($messages) - 1)]);
    }
});
