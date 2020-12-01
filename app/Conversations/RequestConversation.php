<?php

namespace App\Conversations;

use App\Mail\FeedbackMail;
use App\User;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Wkhooy\ObsceneCensorRus;

class RequestConversation extends Conversation
{

    protected $bot;
    protected $current_user_id;
    protected $userId;

    public function __construct(BotMan $bot, $userId)
    {
        $this->bot = $bot;
        $this->userId = $userId;

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

        $this->askMessageUser();
    }


    public function askMessageUser()
    {
        $question = Question::create("Ваше сообщение собеседнику (не менее 5 символов):")
            ->fallback('Спасибо что пообщались со мной:)!');

        $this->ask($question, function (Answer $answer)  {

            $message = $answer->getText();

            if (mb_strlen($message) <= 5) {
                $this->bot->reply("Слишком короткое сообщение");
                $this->askMessageUser();
                return;
            }

            if (!ObsceneCensorRus::isAllowed($message)) {
                $this->bot->reply("Подобная лексика не может быть использована в культурном сообществе! Подберите другие слова!");
                $this->askMessageUser();
                return;
            }

            $user = User::where("id", $this->userId)->first();


            try {

                $this->bot->sendRequest("sendMessage",
                    [
                        "chat_id" => $user->telegram_chat_id,
                        "parse_mode" => "markdown",
                        "text" => "*Собеседник:* " . $message,
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ["text" => "Ответить!", "callback_data" => "/send_message $user->id"],
                                    ["text" => "Игнориовать", "callback_data" => "/ignore ".$user->id]
                                ]
                            ]
                        ])
                    ]);

                $this->bot->reply("Ответ доставлен вашему собеседнику;)");

            } catch (\Exception $e) {
                $this->bot->reply("Ответ не доставлен пользователю:(" );
            }

        });
    }
}
