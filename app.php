<?php
/**
 * Приложение добавляющее комментарии к указанной задаче
 */
error_reporting(E_ALL & ~E_NOTICE);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/lib.php';

//Загрузить настройки
$params = AddMessageToBitrix24Task::load();

try {
    //Формируем ключ для проверки
    $key = $params['B24_APPLICATION_ID'] . $params['MEMBER_ID'] . $params['B24_APPLICATION_SECRET'];

    //Дешифруем полученный ключ и сравниваем с текущим
    if (AddMessageToBitrix24Task::decrypt($_REQUEST['key']) !== $key) {
        die('Некорректный ключ доступа');
    }
} catch (Exception $e) {
    die('Некорректный формат ключа доступа');
}

if (!is_numeric($_REQUEST['task'])) {
    die('Не задан номер задачи');
}

if (null === $_REQUEST['message'] || '' === trim($_REQUEST['message'])) {
    die('Не задан комментарий');
}

try {
    //Получить объект для работы с Bitrix24
    $bx24 = AddMessageToBitrix24Task::getBX24Instance($params);
    //Добавить комментарий к задаче
    $result = AddMessageToBitrix24Task::add($bx24, $_REQUEST['task'], $_REQUEST['message']);
    die($result);
} catch (Exception $e) {
    die('Ошибка при доавлении комментария к задаче');
}