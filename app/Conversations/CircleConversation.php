<?php

namespace App\Conversations;

use App\Circle;
use App\Classes\Base;
use App\User;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use Illuminate\Support\Str;

class CircleConversation extends Conversation
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
        Base::dialogMenu($this->bot, sprintf("Кратко, правила создания кругов:
\xF0\x9F\x94\xB8 все круги по интересам уникальны, даже если называются одинаково
\xF0\x9F\x94\xB8 в ваш круг смогут попасть только те, с кем вы поделитесь ссылкой
Остальные правила тут - */crules*
        "));
        $this->askTitle();
    }

    public function askTitle()
    {
        $question = Question::create("\xF0\x9F\x91\x89Дайте название своему кругу интересов (не меньше 5 символов \xE2\x9C\x85):")
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
        $question = Question::create("\xF0\x9F\x91\x89Опишие основую идею круга (не меньше 3 слов \xE2\x9C\x85):")
            ->fallback('Спасибо что пообщались со мной:)!');

        $this->ask($question, function (Answer $answer) use ($title) {

            $description = $answer->getText();

            if (str_word_count( iconv("UTF-8", "windows-1251",$description)) <= 3) {
                $this->bot->reply("Слишком короткое описание для такого грандиозного замысла;)");
                $this->askDescription($title);
                return;
            }

            if (mb_strlen($description)>=255){
                $len = mb_strlen($description);
                $this->bot->reply("Краткость - сестра таланта! Вмести описание в 255 символов, ибо сейчас аж... $len символов");
                $this->askDescription($title);
                return;
            }

            $title = mb_strtolower($title);
            $description = mb_strtolower($description);

            $circleId = (string)Str::uuid();

            $user = User::with(["circles"])
                ->where("telegram_chat_id", $this->current_user_id)
                ->first();


            Circle::create([
                'id' => $circleId,
                'title' => Str::ucfirst($title),
                'description' => Str::ucfirst($description),
                'creator_id' => $user->id
            ]);


            $user->circles()->attach($circleId);

            $this->bot->reply("Вы успешно создали свой круг по интересам! Теперь вы сможете им поделиться - /my_circles ;)");

        });
    }
}
