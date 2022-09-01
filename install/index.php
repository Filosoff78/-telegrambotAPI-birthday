<?

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use PGK\TelegramBot\TelegramTable;

Loc::loadMessages(__FILE__);

/**
 * @property \Bitrix\Main\HttpRequest $request
 * @property \Bitrix\Main\DB\Connection $connection
 */
class pgk_birthday extends CModule
{
    const COLUMN_NAME = 'BIRTHDAY_DEP';

    public function __construct()
    {
        $arModuleVersion = include('./version.php');

        if (is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        }

        $this->MODULE_ID = 'pgk.birthday';
        $this->MODULE_NAME = 'ПГК: Дни рождения (ТГ БОТ)';
        $this->MODULE_DESCRIPTION = 'Модуль для уведомлений о дне рождения через телеграм бот';
        $this->MODULE_GROUP_RIGHTS = 'N';
        $this->PARTNER_NAME = 'ПГК';
        $this->PARTNER_URI = 'https://pgk.ru/';
    }

    public function DoInstall()
    {
        if (!\Bitrix\Main\ModuleManager::isModuleInstalled("pgk.telegrambot")) {
            global $APPLICATION;
            $APPLICATION->ThrowException('Сперва должен быть установлен модуль "pgk.telegrambot"');
            return false;
        }
        Loader::includeModule('pgk.telegrambot');

        ModuleManager::registerModule($this->MODULE_ID);

        $this->InstallDB();
        $this->InstallAgent();
        $this->SetOptions();
    }

    public function DoUninstall()
    {
        Loader::includeModule('pgk.telegrambot');

        global $APPLICATION;

        $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
        $step = (int)$request->get('step');

        if ($step < 2) {
            return $APPLICATION->includeAdminFile(
                'Удаление модуля ' . $this->MODULE_NAME,
                __DIR__ . '/unstep1.php'
            );
        } else if ($step === 2) {
            if ($_REQUEST['save_tables'] !== 'Y') $this->unInstallDB();
        }
        $this->UnInstallAgent();
        $this->UnSetOptions();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    //region db
    function InstallDB()
    {
        /*
         * Добавляет столбец BIRTHDAY_DEP в таблицу телеграм бота
         */
        TelegramTable::addColumn(self::COLUMN_NAME);
    }

    function UnInstallDB()
    {
        /*
         * Удаляем столбец BIRTHDAY_DEP в таблицу телеграм бота
         */
        TelegramTable::deleteColumn(self::COLUMN_NAME);
    }
    //endregion;
    //region agent
    function InstallAgent()
    {
        /*
         * Агент для отправки уведомления о наступление дня рождения, отправляем сообщение в понедельник в 9 утра
         */
        return CAgent::AddAgent(
            "\PGK\Birthday\Agent\SendMessage::sendTelegram();",
            $this->MODULE_ID,
            "Y",
            86400,
            (new DateTime('next Monday + 9 hours'))->format('d.m.Y H:i:s'),
            "Y",
            (new DateTime('next Monday + 9 hours'))->format('d.m.Y H:i:s'),
        );
    }

    function UnInstallAgent(): bool
    {
        CAgent::RemoveAgent(
            "\PGK\Birthday\Agent\SendMessage::sendTelegram();",
            $this->MODULE_ID,
        );
        return true;
    }
    //endregion;
    //region options
    function SetOptions()
    {
        /*
         * Подключаем модуль Дни рождения в модуль телеграм ботам
         */
        $modules = explode(',', Option::get('pgk.telegrambot', 'MODULES'));
        $find = false;
        foreach ($modules as $module) {
            if ($module === $this->MODULE_ID) $find = true;
        }
        if (!$find) {
            $modules[] = $this->MODULE_ID;
            Option::set(
                'pgk.telegrambot',
                'MODULES',
                implode(",", $modules)
            );

        }
    }

    function UnSetOptions()
    {
        $modules = explode(',', Option::get('pgk.telegrambot', 'MODULES'));
        unset($modules[array_search($this->MODULE_ID, $modules)]);
        Option::set(
            'pgk.telegrambot',
            'MODULES',
            implode(",", $modules)
        );
    }
    //endregion;

}
