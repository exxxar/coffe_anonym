<?php

namespace App\Conversations;

use App\Classes\Base;
use App\IgnoreList;
use App\User;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Conversations\Conversation;

use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use Illuminate\Support\Facades\Log;
use Wkhooy\ObsceneCensorRus;


class StartWithDataConversation extends Conversation
{

    protected $command;


    protected $bot;
    protected $current_user_id;


    public function __construct(BotMan $bot, $command)
    {
        $this->bot = $bot;

        $this->command = $command;

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
        $this->startWithData();
    }

    public function startWithData()
    {

        $admin = User::where("telegram_chat_id", $this->current_user_id)->first();
        $message = null;
        $arg1 = substr($this->command, 0, 3);
        $arg2 = substr($this->command, 3, 36);


        $flag = false;
        switch ($arg1) {
            case "001":
                $flag = true;
                if (!$admin->is_admin) {
                    $this->bot->reply("Вам не доступна данная операция:(");
                    return;
                }
                $user = User::where("id", $arg2)->first();
                $this->askMessageAdmin($user->telegram_chat_id);
                break;
            case "002":
                $flag = true;
                if (!$admin->is_admin) {
                    $this->bot->reply("Вам не доступна данная операция:(");
                    return;
                }

                break;
            default:
            case "003":
                Base::initUser($this->bot, $arg2);
                Base::inviteToCircle($this->bot, $arg2);
                $message = "Ну, что ж... теперь и вы в теме;)";
                break;
            case "007":
                $flag = true;
                $user = User::where("id", $arg2)->first();
                $self = User::where("telegram_chat_id", $this->current_user_id)->first();

                $in_ignore = !is_null(IgnoreList::where("main_user_id", $user->id)
                    ->where("ignored_user_id", $self->id)
                    ->first());

                if ($in_ignore) {
                    $this->bot->reply("Вас игнорируют...");
                    return;
                }

                $this->askMessageUser($user->telegram_chat_id);
                break;
        }

        if (!$flag)
            Base::start($this->bot, $message);


    }

    public function askMessageAdmin($user_chat_id)
    {
        $question = Question::create("Введите ваш ответ пользователю (не менее 5 символов):")
            ->fallback('Спасибо что пообщались со мной:)!');

        $this->ask($question, function (Answer $answer) use ($user_chat_id) {

            $message = $answer->getText();

            if (mb_strlen($message) <= 5) {
                $this->bot->reply("Слишком короткое сообщение");
                $this->askMessageAdmin($user_chat_id);
                return;
            }

            $user = User::where("telegram_chat_id", $user_chat_id)->first();

            try {

                $this->bot->sendRequest("sendMessage",
                    [
                        "chat_id" => $user_chat_id,
                        "parse_mode" => "markdown",
                        "text" => "*Ответ от администратора:*\n" . $message,
                    ]);

                $this->bot->reply("Ответ успешно отправлен пользователю: " . $user->name);

            } catch (\Exception $e) {
                $this->bot->reply("Ответ не доставлен пользователю: " . $user->name);
            }

        });
    }

    public function askMessageUser($user_chat_id)
    {
        $question = Question::create("Ваше сообщение собеседнику (не менее 5 символов):")
            ->fallback('Спасибо что пообщались со мной:)!');

        $this->ask($question, function (Answer $answer) use ($user_chat_id) {

            $message = $answer->getText();

            if (mb_strlen($message) <= 5) {
                $this->bot->reply("Слишком короткое сообщение");
                $this->askMessageUser($user_chat_id);
                return;
            }

            if (!ObsceneCensorRus::isAllowed($message)) {
                $this->bot->reply("Подобная лексика не может быть использована в культурном сообществе! Подберите другие слова!");
                $this->askMessageUser($user_chat_id);
                return;
            }

            $user = User::where("telegram_chat_id", $this->current_user_id)->first();


            $code = "007" . $user->id;

            try {



                 $this->bot->sendRequest("sendMessage",
                    [
                        "chat_id" => $user_chat_id,
                        "parse_mode" => "markdown",
                        "text" => "*Собеседник:* " . $message,
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    ["text" => "Ответить!", "url" => "https://t.me/" . env("APP_BOT_NAME") . "?start=$code"],
                                    ["text" => "Игнориовать", "callback_data" => "/ignore ".$user->id]
                                ]
                            ]
                        ])
                    ]);

                $this->bot->reply("Ответ доставлен вашему собеседнику;)");

            } catch (\Exception $e) {
                Log::info($e->getTraceAsString());
                $this->bot->reply("Ответ не доставлен пользователю:(" );
            }

        });
    }
}
