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


$botman = resolve('botman');


$botman->hears('/start ([0-9a-zA-Z-]{39})', BotManController::class . '@startWithDataConversation');

$botman->hears('.*Главное меню|.*Передумал создавать|/start', function ($bot) {
    Base::initUser($bot);
    Base::start($bot);
})->stopsConversation();

$botman->hears('.*Круги по интересам', function ($bot) {
    Base::profileMenu($bot, "Ваш личный уголок\xF0\x9F\x8F\xA1\n*Правила кругов интересов* можно прочитать тут /crules\nА для *настройки комфорта* встречь - /settings \xF0\x9F\x98\x89");
})->stopsConversation();

$botman->hears('.*Мои круги интересов|/my_circles ([0-9+])|/my_circles', function ($bot, $page = 0) {

    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $user = (User::with(["circles"])->where("telegram_chat_id", $id)->first());
    $circles = $user->circles()
        ->take(env("CIRCLES_PER_PAGE"))
        ->skip(env("CIRCLES_PER_PAGE") * $page)
        ->paginate(env("CIRCLES_PER_PAGE"));

    if (count($circles) == 0) {

        $bot->reply("Эх, а вы еще не состоите ни в одном кругу интересов... Может быть вы создадите его? /create");
        return;
    }
    foreach ($circles as $circle) {

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
                "chat_id" => "$id",
                "text" => $message,
                "parse_mode" => "Markdown",
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
                        $keyboard
                ])
            ]);


})->stopsConversation();

$botman->hears('.*Как пользоваться?|/rules', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $message = "
Как начать:
✔️ Определись с какими собеседниками тебе комфортнее общаться \xF0\x9F\x91\xA7 или \xF0\x9F\x91\xA6, а может быть это и вовсе не важно?
✔️ Укажи свой регион проживания - ведь проще выпить чашечку \xE2\x98\x95 с людьми поблизости;) 
✔    И сколько встреч в неделю ты осилишь?) \x31\xE2\x83\xA3, \x32\xE2\x83\xA3 или может быть \x33\xE2\x83\xA3?    

Краткая инструкция:
✔️ Каждый раз ты будешь получать от меня сообщение c контактами нового человека для встречи.
✔️ Напиши своему собеседнику в Telegram, чтобы договориться о встрече или звонке. 
✔️ Время и место вы выбираете сами.
✔️ Не откладывай, договаривайся о встрече сразу.
✔️ Собеседник не отвечает? Напиши мне в чате, и я подберу тебе новую пару.   
✔️ За день до новой встречи я поинтересуюсь, участвуешь ли ты, и как прошла твоя предыдущая встреча.
✔️ Если захочешь отказаться от участия совсем, напиши мне в ответ.\n

Если у тебя есть вопросы или предложения — пиши мне в этом чате.

/settings - настройка комфорта встреч 
/crules - правила кругов интересов
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
\xF0\x9F\x94\xB8 _круг интересов можно создать_
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

    $message = sprintf("Да, хорошо что вы определились! Так будет проще подбирать собеседников;)");

    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => $message,
            "parse_mode" => "Markdown",
        ]);

})->stopsConversation();

$botman->hears('/prefer_([a-zA-Z]+)', function ($bot, $type) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $user = User::where("telegram_chat_id", $id)->first();

    $counts = ["one" => 1, "two" => 2, "three" => 3];
    $prefers = ["man" => 1, "woman" => 2, "any" => 3];

    $user->meet_in_week = $counts[$type] ?? $user->meet_in_week;
    $user->prefer_meet_in_week = $prefers[$type] ?? $user->prefer_meet_in_week;

    $user->save();

    $message = sprintf("Да, хорошо что вы определились! Так будет проще подбирать собеседников;)");

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
/prefer_man - предпочтительно мужчины (парни) ".($user->prefer_meet_in_week==1?"\xE2\x9C\x85":"")."
/prefer_woman - предпочтительно женщины (девушки) ".($user->prefer_meet_in_week==2?"\xE2\x9C\x85":"")."
/prefer_any - любой собеседник ".($user->prefer_meet_in_week==3?"\xE2\x9C\x85":"")."

Также рекомендуем определиться с числом встречь в неделю!

/prefer_one - максимум одна встреча в неделю ".($user->meet_in_week==1?"\xE2\x9C\x85":"")."
/prefer_two - одна или две встречи в неделю ".($user->meet_in_week==2?"\xE2\x9C\x85":"")."
/prefer_three - от одной до трёх встреч ".($user->meet_in_week==3?"\xE2\x9C\x85":"")."

Или же, вы можете отдохнуть от встреч

/stop - больше нет желания с кем-либо встречаться (в течении недели)

Если вдруг вы ошибочного выбрали свой собственный пол, то его тоже легко можно поменять:
/i_am_man - собседники будут принимать вас за мужчину (парня) ".($user->sex==0?"\xE2\x9C\x85":"")."
/i_am_woman - собседники будут принимать вас за женщину (девушку) ".($user->sex==1?"\xE2\x9C\x85":"")."
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

    if (!isAdmin($bot)) {
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

$botman->fallback(function (\BotMan\BotMan\BotMan $bot) {
    $messages = [
        "Спасибо что пишите мне!",
        "Я обязательно вскоре отвечу!",
        "Мне очень приятно оказаться полезным для Вас!",
        "Такие сообщения радуют меня!",
        "Каждое сообщение будет услышано!",
    ];

    $json = json_decode($bot->getMessage()->getPayload());

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

        $bot->reply("Заявка успешно принята! Мы свяжемся с вами в течение 10 минут!");

        $user = User::where("telegram_chat_id", $id)->first();
        $user->location = json_encode([
            "latitude" => $location->latitude,
            "longitude" => $location->longitude,
            "city" => $city ?? null
        ]);
        $user->save();
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
