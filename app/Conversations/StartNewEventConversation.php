<?php

namespace App\Conversations;

use App\Circle;
use App\Classes\Base;
use App\MeetEvents;
use App\User;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StartNewEventConversation extends Conversation
{
    protected $bot;
    protected $current_user_id;


    public function __construct(BotMan $bot)
    {
        $this->bot = $bot;

        $telegramUser = $bot->getUser();
        $this->current_user_id = $telegramUser->getId();
    }

    /**
     * Start the conversation.
     *
     * @return mixed
     */
    public function run()
    {
        Base::dialogMenu($this->bot, "Создаем новое событие");
        $this->askTitle();
    }

    public function askTitle()
    {
        $question = Question::create("\xF0\x9F\x91\x89Дайте название данному событию (не меньше 5 символов \xE2\x9C\x85):")
            ->fallback('Спасибо что пообщались со мной:)!');

        $this->ask($question, function (Answer $answer) {


            $title = $answer->getText();

            if (mb_strlen($title) <= 5) {
                $this->bot->reply("Слишком короткое название для такого грандиозного замысла;)");
                $this->askTitle();
                return;
            }

            $this->askDescription($title);

        });
    }

    public function askDescription($title)
    {
        $question = Question::create("\xF0\x9F\x91\x89Опишие основую идею события (не меньше 3 слов \xE2\x9C\x85):")
            ->fallback('Спасибо что пообщались со мной:)!');

        $this->ask($question, function (Answer $answer) use ($title) {


            $description = $answer->getText();

            if (str_word_count(iconv("UTF-8", "windows-1251", $description)) < 3) {
                $this->bot->reply("Слишком короткое описание для такого грандиозного замысла;)");
                $this->askDescription($title);
                return;
            }

            if (mb_strlen($description) >= 255) {
                $len = mb_strlen($description);
                $this->bot->reply("Краткость - сестра таланта! Вмести описание в 255 символов, ибо сейчас аж... $len символов");
                $this->askDescription($title);
                return;
            }

            $this->askDuration($title, $description);

        });
    }


    public function askDuration($title, $description)
    {
        $question = Question::create("Введите длительность в днях (событие начнется на следующий день после создания):")
            ->fallback('Спасибо что пообщались со мной:)!');

        $this->ask($question, function (Answer $answer) use ($title, $description) {

            $days = $answer->getText();

            if (!is_numeric(intval($days))) {
                $this->bot->reply("Нужно вводить число!");
                $this->askDuration($title, $description);
                return;
            }

            if (intval($days) > 365) {
                $this->bot->reply("Слишком длительное событие! Попробуйте его ограничить 1м годом;)");
                $this->askDuration($title, $description);
                return;
            }

            $title = mb_strtolower($title);
            $description = mb_strtolower($description);

            $circleId = (string)Str::uuid();


            Log::info($this->bot->userStorage()->get('image_url') ?? 'test');

            MeetEvents::create([
                'id' => $circleId,
                'title' => Str::ucfirst($title),
                'description' => Str::ucfirst($description),
                'date_start' => Carbon::now("+3")->addDay(1),
                'date_end' => Carbon::now("+3")->addDays($days),
                'image_url' => $this->bot->userStorage()->get('image_url') ?? null
            ]);

            Base::sendToAdminChannel($this->bot, "Создано новое событие *$title*", false);
            Base::adminMenu($this->bot, "Вы успешно создали Новое событие!)");

        });
    }
}
