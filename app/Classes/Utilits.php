<?php


namespace App\Classes;


use Telegram\Bot\Laravel\Facades\Telegram;

trait Utilits
{
    protected function sendMessageToTelegramChannel($id, $message, $keyboard = [])
    {
        try {
            Telegram::sendMessage([
                'chat_id' => $id,
                'parse_mode' => 'Markdown',
                'text' => $message,
                'reply_markup' => json_encode([
                    'inline_keyboard' => $keyboard
                ])
            ]);
        } catch (\Exception $e) {
            Log::error(sprintf("%s:%s %s",
                $e->getLine(),
                $e->getFile(),
                $e->getMessage()
            ));
        }

    }
}
