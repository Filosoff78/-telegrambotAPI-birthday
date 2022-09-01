<?php
namespace PGK\Birthday\Agent;

use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\ORM\Fields\ExpressionField;
use Bitrix\Main\Type\DateTime;
use Longman\TelegramBot\Request;

require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");

class SendMessage
{
    static function sendTelegram()
    {
        if (!\Bitrix\Main\Loader::includeModule('pgk.telegrambot') ||
            !\Bitrix\Main\DI\ServiceLocator::getInstance()->get('TelegramObject'))
        {
            return false;
        };
        /*
         * Получаемся список всех пользователей подписанных на уведомление со всеми департаментами включая дочерние
         */
        $notificationUsersQuery = \PGK\TelegramBot\TelegramTable::query()
            ->whereNotNull('BIRTHDAY_DEP')
            ->registerRuntimeField(new ReferenceField(
                'SECTION_REF',
                '\Bitrix\Iblock\SectionTable',
                [
                    '=this.BIRTHDAY_DEP' => 'ref.ID',
                ],
            ))
            ->registerRuntimeField(new ReferenceField(
                'CHILD_DEP_REF',
                '\Bitrix\Iblock\SectionTable',
                [
                    '=this.SECTION_REF.IBLOCK_ID' => 'ref.IBLOCK_ID',
                    '<=this.SECTION_REF.LEFT_MARGIN' => 'ref.LEFT_MARGIN',
                    '>=this.SECTION_REF.RIGHT_MARGIN' => 'ref.RIGHT_MARGIN',
                ],
            ))
            ->setSelect(['DEP_ID' => 'CHILD_DEP_REF.ID', 'TELEGRAM_ID'])
            ->fetchAll();

        /*
         * Создаем массив, где ключ номер отдела, а значение массив пользователей подписанных на него
         */
        $notificationUsers = [];
        foreach ($notificationUsersQuery as $user) {
            $notificationUsers[$user['DEP_ID']][] = $user['TELEGRAM_ID'];
        }

        /*
         * Получаем дни рождения на текущей недели по отделам на которые подписаны пользователи
         */
        $birthdayUserInNowWeek = \Bitrix\Main\UserTable::query()
            ->where('ACTIVE', true)
            ->whereIn('UF_DEPARTMENT', array_keys($notificationUsers))
            ->whereNotNull('PERSONAL_BIRTHDAY')
            ->registerRuntimeField(new ExpressionField(
                'BIRTH_MONTH_DAY',
                'DATE_FORMAT(%s, "%%m-%%d")',
                ['PERSONAL_BIRTHDAY']
            ))
            ->whereBetween(
                'BIRTH_MONTH_DAY',
                DateTime::createFromTimestamp(strtotime('monday this week'))->format('m-d'),
                DateTime::createFromTimestamp(strtotime('sunday this week'))->format('m-d')
            )
            ->registerRuntimeField(new ExpressionField(
                'FULL_NAME',
                'concat_ws(" ", %s, %s)',
                [
                    'LAST_NAME',
                    'NAME',
                ]
            ))
            ->registerRuntimeField(new \Bitrix\Main\Entity\ReferenceField(
                    'DEP_REF',
                    '\Bitrix\Iblock\SectionTable',
                    \Bitrix\Main\ORM\Query\Join::on('this.UF_DEPARTMENT_SINGLE', 'ref.ID')
                )
            )
            ->setSelect(['BIRTH_MONTH_DAY', 'FULL_NAME', 'DEP_NAME' => 'DEP_REF.NAME', 'UF_DEPARTMENT_SINGLE'])
            ->fetchAll();

        /*
         * Создаем массив, где ключ ид пользователя в телеграмме, а значение текст для отправки сообщения
         */
        $notifications = [];
        foreach ($birthdayUserInNowWeek as $birthdayUser) {
            if (is_set($notificationUsers[$birthdayUser['UF_DEPARTMENT_SINGLE']])) {
                foreach ($notificationUsers[$birthdayUser['UF_DEPARTMENT_SINGLE']] as $notificationUser) {
                    $notifications[$notificationUser] .= PHP_EOL."{$birthdayUser['FULL_NAME']} ({$birthdayUser['BIRTH_MONTH_DAY']}) - ".
                        "{$birthdayUser['DEP_NAME']}";
                }
            }
        }

        foreach ($notifications as $key => $notification) {
            Request::sendMessage([
                'chat_id' => $key,
                'text' => 'На этой недели день у рождения:' . $notification,
            ]);
        }
        return true;
    }
}
