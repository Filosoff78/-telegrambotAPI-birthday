<?php

namespace PGK\Birthday\Telegram\Query;

use Bitrix\Iblock\ORM\Query;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Request;
use PGK\TelegramBot\TelegramTable;
use Longman\TelegramBot\Entities\ServerResponse;

class Birthday
{
    const PAGE_SIZE = 20;

    public static function execute(int $telegramId, array $data = [], CallbackQuery $query = null)
    {
        if (!$data) return self::showMenu($telegramId);
        switch ($data[0]) {
            /*
             * $data[1] - ид департамента
             * $data[2] - ид сообщения в ТГ
             * $data[3] - номер странички
             */
            case 'showDep':
                return self::showDep(
                    $telegramId,
                    $data[1],
                    $query->getMessage()->getMessageId()
                );
            case 'showMenu':
                return self::showMenu(
                    $telegramId,
                    $data[1],
                    $data[2],
                    $data[3] ?? 1,
                );
            case 'chooseDep':
                return self::chooseDep(
                    $telegramId,
                    $data[1],
                    $data[2],
                );
        }
        return false;
    }

    public static function showMenu(int $telegramId, int $depId = null, int $messageId = null, int $page = 1): ServerResponse
    {
        $pageSize = ($page === 1) ? self::PAGE_SIZE + 1 : self::PAGE_SIZE;

        if (!$depId) {
            $userInfo = (new \PGK\TelegramBot\Cache())->getInfo($telegramId);
            $depId = \CIntranetUtils::GetUserDepartments($userInfo['id'])[0];
            $parentDepId = \Bitrix\Iblock\SectionTable::query()
                ->where('ID', $depId)
                ->setSelect(['IBLOCK_SECTION_ID'])
                ->fetch()['IBLOCK_SECTION_ID'];
        } else $parentDepId = $depId;

        $getChildDep = self::getChildDep($page, $parentDepId);
        $childDep = $getChildDep['deps'];
        $childDepCount = $getChildDep['count'];

        $keyboardDeps = [];
        foreach ($childDep as $dep) {
            $keyboardDeps[] =
                [
                    [
                        'text' => $dep['NAME'],
                        'callback_data' => json_encode(['callback' => 'birthday', 'data' => [
                            'showDep', (int)$dep['ID']
                        ]])
                    ]
                ];
        }

        if ($childDepCount > $pageSize) {
            $keyboardDeps = self::pagination($childDepCount, $parentDepId, $messageId, $page, $keyboardDeps);
        }

        $inline_keyboard = new InlineKeyboard(...$keyboardDeps);

        $messageText = [
            'chat_id' => $telegramId,
            'text' => 'Департаменты',
            'reply_markup' => $inline_keyboard,
            'message_id' => $messageId,
            'parse_mode' => 'markdown'
        ];

        if (!$messageId) return Request::sendMessage($messageText);
        else return Request::editMessageText($messageText);
    }

    public static function pagination($count, $depId, $messageId, $pageCurrent, $keyboard): array
    {
        $keyboardPagination = [];
        $page = 1;
        for ($i = $count; $i > 0; $i -= self::PAGE_SIZE) {
            $keyboardPagination[0][] = [
                'text' => $page !== $pageCurrent ? $page : '•' . $page . '•',
                'callback_data' => json_encode(['callback' => 'birthday', 'data' => [
                    'showMenu', $depId, $messageId, $page
                ]])
            ];
            $page++;
        }
        return array_merge($keyboard, $keyboardPagination);
    }

    public static function showDep(int $telegramId, int $depId, int $messageId)
    {
        $depInfo = \Bitrix\Iblock\SectionTable::query()
            ->where('ID', $depId)
            ->setSelect(['NAME', 'IBLOCK_SECTION_ID'])
            ->fetch();

        $countChild = \Bitrix\Iblock\SectionTable::query()
            ->where('IBLOCK_SECTION_ID', $depId)
            ->queryCountTotal();

        $keyboard[] =
            [
                [
                    'text' => 'Выбрать',
                    'callback_data' => json_encode(['callback' => 'birthday', 'data' => [
                        'chooseDep', $depId, $messageId
                    ]])
                ],
            ];

        if ($depInfo['IBLOCK_SECTION_ID']) {
            $keyboard[] = [
                [
                    'text' => 'Перейти к родительскому отделу',
                    'callback_data' => json_encode(['callback' => 'birthday', 'data' => [
                        'showMenu', (int)$depInfo['IBLOCK_SECTION_ID'], $messageId
                    ]])
                ]
            ];
        }

        if ($countChild) {
            $keyboard[] = [
                [
                    'text' => 'Перейти к дочерним отделам',
                    'callback_data' => json_encode(['callback' => 'birthday', 'data' => [
                        'showMenu', $depId, $messageId
                    ]])
                ]
            ];
        }

        $inline_keyboard = new InlineKeyboard(...$keyboard);

        return Request::editMessageText([
            'chat_id' => $telegramId,
            'message_id' => $messageId,
            'text' => $depInfo['NAME'],
            'reply_markup' => $inline_keyboard
        ]);
    }

    public static function chooseDep(int $telegramId, int $depId, int $messageId)
    {
        TelegramTable::update(
            $telegramId,
            ['BIRTHDAY_DEP' => $depId]
        );
        $birthdayDepName = \Bitrix\Iblock\SectionTable::query()
            ->where('ID', $depId)
            ->setSelect(['NAME'])
            ->fetch()['NAME'];
        Request::deleteMessage([
            'chat_id' => $telegramId,
            'message_id' => $messageId,
        ]);
        return Request::sendMessage([
            'chat_id' => $telegramId,
            'text' => 'Вы подписались на уведомления отдела ' . $birthdayDepName,
        ]);
    }

    public static function getChildDep(int $page, int $parentDepId) : array
    {
        $pageSize = ($page === 1) ? self::PAGE_SIZE + 1 : self::PAGE_SIZE;

        $queryChildDep = \Bitrix\Iblock\SectionTable::query()
            ->setSelect(['NAME', 'ID'])
            ->addOrder('ID');

        if ($page === 1) {
            $queryChildDep->where(Query::filter()
                ->logic('or')
                ->where([
                    ['IBLOCK_SECTION_ID', $parentDepId],
                    ['ID', $parentDepId]
                ]));
        } else {
            $queryChildDep->where('IBLOCK_SECTION_ID', $parentDepId);
        }

        $childDepCount = $queryChildDep->queryCountTotal();

        $limit = [
            'nPageSize' => $pageSize,
            'iNumPage' => $page,
        ];

        if ($childDepCount > $pageSize) {
            $queryChildDep->setLimit($limit['nPageSize']);
            $queryChildDep->setOffset(($limit['iNumPage'] - 1) * $limit['nPageSize']);
        }

        $deps = $queryChildDep->fetchAll();

        foreach ($deps as $key => &$dep) {
            if ((int) $dep['ID'] === $parentDepId) {
                array_unshift($deps, $dep);
                unset($deps[$key+1]);
                break;
            }
        }

        return [
            'deps' => $deps,
            'count' => $childDepCount,
        ];
    }
}
