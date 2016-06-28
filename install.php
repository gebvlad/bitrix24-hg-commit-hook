<?php
/**
 * Установка приложения.
 *
 * Выполнить из окружения портала Битрикс24 для создания конфигурационного файла
 */
error_reporting(E_ALL & ~E_NOTICE);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/lib.php';

if (null === $_REQUEST['DOMAIN'] || null === $_REQUEST['member_id'] || null === $_REQUEST['AUTH_ID'] || null === $_REQUEST['REFRESH_ID']) {
    die('Приложение необходимо установить из портала Битрикс24');
}

$params = AddMessageToBitrix24Task::load();

if (0 === count($params)) {
    $params = [
        //Идентификатор приложения в портале (из настроек приложения в портале)
        'B24_APPLICATION_ID'     => '<CLIENT_ID>',
        //Секретное слово приложения в портале (из настроек приложения в портале)
        'B24_APPLICATION_SECRET' => '<CLIENT_SECRET>',
        //Требуемые для работы сущности портала (из настроек приложения в портале)
        'B24_APPLICATION_SCOPE'  => ['task'],
        //URL приложения после установки (из настроек приложения в портале)
        'B24_REDIRECT_URI'       => 'http://<APP_DOMAIN>/app.php',
        //Домен портала
        'DOMAIN'                 => $_REQUEST['DOMAIN'],
        //Уникальный идентификатор приложения
        'MEMBER_ID'              => $_REQUEST['member_id'],
        //Токен авторизации
        'AUTH_ID'                => $_REQUEST['AUTH_ID'],
        //Токен обновления
        'REFRESH_ID'             => $_REQUEST['REFRESH_ID'],
    ];

    //Сохранить настройки в кофигурационный файл
    AddMessageToBitrix24Task::save($params);
}

//Проверка, что настроки сохранены корректно
if (AddMessageToBitrix24Task::check()) {
    //Загружаем настройки для вывода в интерфейсе Битрикс24 ключа доступа
    $params = AddMessageToBitrix24Task::load();
    $result = 'Приложение установлено.<br>';
    $result .= 'Добавьте в скрипт hook.php ключ доступа:<br>';
    $result .= $params['KEY'];
} else {
    $result = 'Приложение установлено c ошибками.<br>';
}
die($result);