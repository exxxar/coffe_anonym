<?php

namespace App\Conversations;

use App\Classes\Base;
use App\User;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Conversations\Conversation;

use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use Illuminate\Support\Facades\Log;


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
                $this->askMessage($user->telegram_chat_id);
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
                break;
        }

        if (!$flag)
            Base::start($this->bot);


    }

    public function askMessage($user_chat_id)
    {
        $question = Question::create("Введите ваш ответ пользователю (не менее 5 символов):")
            ->fallback('Спасибо что пообщались со мной:)!');

        $this->ask($question, function (Answer $answer) use ($user_chat_id) {

            $message = $answer->getText();

            if (mb_strlen($message) <= 5) {
                $this->bot->reply("Слишком короткое сообщение");
                $this->askMessage($user_chat_id);
                return;
            }

            $this->bot->sendRequest("sendMessage",
                [
                    "chat_id" => $user_chat_id,
                    "parse_mode" => "markdown",
                    "text" => "*Ответ от администратора:*\n".$message,
                ]);

        });
    }
}
