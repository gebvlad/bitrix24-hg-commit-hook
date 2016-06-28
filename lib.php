<?php
/**
 * Основной класс приложения
 */
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

/**
 * Добавить комментарий к задачи в портале Битрикс24
 *
 * Class AddMessageToBitrix24Task
 */
class AddMessageToBitrix24Task
{
    /**
     * @var string Путь к настройкам приложения. Файл не должен находится в корне сайта.
     */
    private static $config = __DIR__ . '/../bx24.auth';

    /**
     * @var string Ключ шифрования
     */
    private static $safeKey;

    /**
     * Шифровать переменную
     *
     * @param string $var Переменная для шифрования
     *
     * @return string Шифрованная переменная
     *
     * @throws \Defuse\Crypto\Exception\BadFormatException
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public static function encrypt($var)
    {
        return Crypto::encrypt($var, self::getKey());
    }

    /**
     * Дешифровать переменую
     *
     * @param string $var Переменная для дешифрации
     *
     * @return string Дешифрованная переменная
     *
     * @throws \Defuse\Crypto\Exception\BadFormatException
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public static function decrypt($var)
    {
        return Crypto::decrypt($var, self::getKey());
    }

    /**
     * Получить ключ шифрования
     *
     * @return Key Ключ шифрования
     *
     * @throws \Defuse\Crypto\Exception\BadFormatException
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public static function getKey()
    {
        if (null === self::$safeKey) {
            $params = self::load();

            //Получить ключ шифрования в бинарно-безопасном виде
            if (null === $params || null === $params['PRIVATE_KEY']) {
                self::$safeKey = Key::createNewRandomKey()->saveToAsciiSafeString();
            } else {
                self::$safeKey = $params['PRIVATE_KEY'];
            }
        }

        return Key::loadFromAsciiSafeString(self::$safeKey);
    }

    /**
     * Получить объект для работы с Bitrix24
     *
     * @param array $params Параметры для работы с Битрикс24
     *
     * @return \Bitrix24\Bitrix24 Объект для работы с Битрикс24
     *
     * @throws \Bitrix24\Bitrix24Exception
     * @throws \Defuse\Crypto\Exception\BadFormatException
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public static function getBX24Instance(array $params)
    {
        $bx24 = new \Bitrix24\Bitrix24(false);

        $bx24->setApplicationScope($params['B24_APPLICATION_SCOPE']);
        $bx24->setApplicationId($params['B24_APPLICATION_ID']);
        $bx24->setApplicationSecret($params['B24_APPLICATION_SECRET']);
        $bx24->setRedirectUri($params['B24_REDIRECT_URI']);
        $bx24->setDomain($params['DOMAIN']);
        $bx24->setMemberId($params['MEMBER_ID']);
        $bx24->setAccessToken($params['AUTH_ID']);
        $bx24->setRefreshToken($params['REFRESH_ID']);

        //Если время жизни токенов истекло
        if ($bx24->isAccessTokenExpire()) {
            //ПОлучитть новый токен доступа
            $temp = $bx24->getNewAccessToken();
            //Обновить токены в объекте
            $params['AUTH_ID'] = $temp['access_token'];
            $params['REFRESH_ID'] = $temp['refresh_token'];
            $bx24->setAccessToken($params['AUTH_ID']);
            $bx24->setRefreshToken($params['REFRESH_ID']);
            //Сохранить обновленные токены
            self::save($params);
        }

        return $bx24;
    }

    /**
     * Добавить комментарий к задаче
     *
     * @param \Bitrix24\Bitrix24 $bx24    Объект для работы с Битрикс24
     * @param    int             $task    Идентификатор задачи
     * @param    string          $message Комментарий
     *
     * @return string
     */
    public static function add(\Bitrix24\Bitrix24 $bx24, $task, $message)
    {
        $str = '';

        try {
            //Проверить есть ли такая задача на портале
            $bx24->call(
                'task.item.getdata',
                [
                    'TASKID' => $task
                ]
            );

            $str .= 'Задача #' . $task . ' на портале ' . $bx24->getDomain() . ' найдена' . PHP_EOL;

            //Добавить комментарий к задаче
            $bx24->call(
                'task.commentitem.add',
                [
                    'TASKID' => $task,
                    'FIELDS' => [
                        'POST_MESSAGE' => $message
                    ]
                ]
            );

            $str .= 'Комментарий к задаче успешно добавлен';
        } catch (Exception $e) {
            $str .= 'Ошибка при добавлении комментация к задаче';
        }

        return $str;
    }

    /**
     * Сохранить настройки в конфигурационный файл
     *
     * @param array $params Настройки
     *
     * @return bool
     *
     * @throws \Defuse\Crypto\Exception\BadFormatException
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public static function save(array $params)
    {
        //Ключ для доступа к приложению для добавления комментария
        $params['KEY'] = AddMessageToBitrix24Task::encrypt($params['B24_APPLICATION_ID'] . $params['MEMBER_ID'] . $params['B24_APPLICATION_SECRET']);
        //Ключ шифрования
        $params['PRIVATE_KEY'] = self::$safeKey;
        //Сохраняем данные в файл конфигурации
        $result = json_encode($params, JSON_UNESCAPED_UNICODE);

        return file_put_contents(self::$config, $result) > 0;
    }

    /**
     * Получить настройки из конфигурационного файла
     *
     * @return array Настройки
     */
    public static function load()
    {
        if (!file_exists(self::$config)) {
            return [];
        }

        //Получить настройки приложения
        $params = file_get_contents(self::$config);

        return json_decode($params, true);
    }

    /**
     * Проверка, что приложение установлено из заданого портала Битрикс24.
     *
     * @return bool
     */
    public static function check()
    {
        try {
            $params = AddMessageToBitrix24Task::load();
            $bx24 = self::getBX24Instance($params);

            $result = $bx24->call('app.info');

            return $result['result']['CODE'] === $params['B24_APPLICATION_ID'];
        } catch (\Exception $e) {
            return false;
        }
    }
}