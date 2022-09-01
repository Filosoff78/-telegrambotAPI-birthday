<?php
use Longman\TelegramBot\Commands\SystemCommands\CallbackqueryCommand;
use Longman\TelegramBot\Entities\CallbackQuery;

/**
 * Данный файл будет запускать модуль Телеграм бота для регистрации обработчика клика на кнопку
 * отдела в команде /birthday
 * @return void
 */
return function() {
    CallbackqueryCommand::addCallbackHandler(function (CallbackQuery $query) {
        $data = json_decode($query->getData(), true);
        switch ($data['callback']) {
            case "birthday":
                \PGK\Birthday\Telegram\Query\Birthday::execute(
                    $query->getFrom()->getId(),
                    $data['data'],
                    $query,
                );
                break;
        }
    });
};
