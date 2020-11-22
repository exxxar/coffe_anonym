<?php

namespace App\Conversations;

use App\Classes\Base;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Conversations\Conversation;

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


        $arg1 = substr($this->command, 0, 3);
        $arg2 = substr($this->command, 3, 36);

        switch ($arg1) {
            case "001":
                break;
            case "002":
                break;
            default:
            case "003":
                Base::initUser($this->bot, $arg2);
                Base::inviteToCircle($this->bot, $arg2);
                break;
        }

        Base::start($this->bot);
    }

}
