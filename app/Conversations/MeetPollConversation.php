<?php

namespace App\Conversations;

use App\Circle;
use App\Classes\Base;
use App\Meet;
use App\User;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use Illuminate\Support\Str;
use Wkhooy\ObsceneCensorRus;

class MeetPollConversation extends Conversation
{

    protected $bot;
    protected $current_user_id;
    protected $index;
    protected $meetId;


    public function __construct(BotMan $bot, $data)
    {
        $this->bot = $bot;

        $this->index = substr($data, 0, 1);
        $this->meetId  = substr($data, 1, 36);


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
        Base::dialogMenu($this->bot, sprintf("Дайте характеристику прошедешей встречи"));
        $this->askDescription();
    }

    public function askDescription()
    {
        $question = Question::create("Опишите как прошла ваша встреча в целом?")
            ->fallback('Спасибо что пообщались со мной:)!');

        $this->ask($question, function (Answer $answer) {

            $description = $answer->getText();

            if (mb_strlen($description) >= 255) {
                $len = mb_strlen($description);
                $this->bot->reply("Краткость - сестра таланта! Вместите описание в 255 символов, ибо сейчас аж... $len символов");
                $this->askDescription();
                return;
            }

            if (!ObsceneCensorRus::isAllowed($description)) {
                $this->bot->reply("Подобная лексика не может быть использована в культурном сообществе! Подберите другие слова!");
                $this->askDescription();
                return;
            }


            $meet = Meet::where("id", $this->meetId)->first();

            if (is_null($meet)) {
                $this->bot->reply("Что-то пошло не так...");
                return;
            }

            if ($this->index == 1)
                $meet->short_comment_1 = $description;
            else
                $meet->short_comment_2 = $description;

            $meet->save();

            $this->bot->userStorage()->delete();

            Base::mainMenu($this->bot, "Спасибо за отзывы!)");

        });
    }

}
