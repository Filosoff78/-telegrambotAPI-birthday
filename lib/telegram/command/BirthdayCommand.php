<?php

/**
 * This file is part of the PHP Telegram Bot example-bot package.
 * https://github.com/php-telegram-bot/example-bot/
 *
 * (c) PHP Telegram Bot Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * User "/echo" command
 *
 * Simply echo the input back to the user.
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;

class BirthdayCommand extends UserCommand
{

    protected $name = 'birthday';
    protected $description = 'Выбор отдела';
    protected $version = '1.0.0';

    public function execute(): ServerResponse
    {
        $telegramId = $this->getMessage()->getFrom()->getId();

        if(!\PGK\TelegramBot\User::checkRegistration($telegramId)) return Request::emptyResponse();

        $birthdayDepName = \PGK\TelegramBot\TelegramTable::query()
            ->where('TELEGRAM_ID', $telegramId)
            ->registerRuntimeField(new \Bitrix\Main\Entity\ReferenceField(
                    'DEP_REF',
                    '\Bitrix\Iblock\SectionTable',
                    \Bitrix\Main\ORM\Query\Join::on('this.BIRTHDAY_DEP', 'ref.ID')
                )
            )
            ->setSelect(['DEP_NAME' => 'DEP_REF.NAME'])
            ->fetch()['DEP_NAME'];

        if ($birthdayDepName) Request::sendMessage([
            'chat_id' => $telegramId,
            'text'    => 'Сейчас вы подписаны на уведомления: '.$birthdayDepName,
        ]);

        \PGK\Birthday\Telegram\Query\Birthday::execute(
            $this->getMessage()->getFrom()->getId()
        );
        return Request::emptyResponse();
    }
}
