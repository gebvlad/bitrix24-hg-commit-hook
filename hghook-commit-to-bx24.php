<?php
/**
 * Хук срабатывающий после выполнения команды hg commit
 */

//Адрес приложения Битрикс24
define('BX24_APP_URL', 'https://<APP_DOMAIN>/app.php');

//Ключ для обращения к приложению. Получить можно после установки из конфигурационного файла
define('KEY', '<ACCESS_KEY>');

echo 'Запущен хук Mercurial, добавляющий информацию о коммите в задачу на портал' . PHP_EOL;

if(!function_exists('shell_exec')){
    echo 'Ошибка: функция «shell_exec» недоступна' . PHP_EOL;
    echo 'Завершение хука' . PHP_EOL;
    exit(0);
}

if(!function_exists('curl_exec')){
    echo 'Ошибка: функция «curl_exec» недоступна' . PHP_EOL;
    echo 'Завершение хука' . PHP_EOL;
    exit(0);
}

//Путь к исполняемому файлу Mercurial
$hg = $_SERVER['HG'];

if (!is_file($hg) || !is_executable($hg)) {
    echo 'Ошибка: не найден исполняемый файл Mercurial' . PHP_EOL;
    echo 'Завершение хука' . PHP_EOL;
    exit(0);
}

echo 'Получение информации о коммите' . PHP_EOL;

//Получить полное название хоста, на котором работает Mercurial
$hostname = trim(shell_exec('hostname -f'));

//Абсолютный путь к текущему репозиторию
$pwd = $_SERVER['PWD'];

//Текущая активная ветка Mercurial
$branch = shell_exec("$hg branch");

//Получаем информацию о сделанном коммите
$log = trim(shell_exec("$hg log -l 1"));

//Автор коммита
$matches = null;
$user = preg_match('/user:\s+(?<user>\S.*)/ium', $log, $matches) ? $matches['user'] : 'unknown';

//Комментарий коммита
$summary = preg_match('/summary:\s+(?<summary>\S.*)/ium', $log, $matches) ? $matches['summary'] : '';

//Получить список файлов текущего коммита за исключюенеи удаленных
$files = trim(shell_exec("$hg st -amr"));

//Количество файлов текущего коммита
$filesCount = substr_count($files, PHP_EOL);

//Получить номер задачи из названия текущей ветки Mercurial или из комментария к коммиту
echo 'Поиск номера задачи в названии ветки или комментарии' . PHP_EOL;

$task = 0;

if (preg_match('/^task[#\@\$](?<id>\d+)/iu', $branch, $matches)) {
    $task = (int)$matches['id'];
} elseif (preg_match('/^task[#\@\$](?<id>\d+)/iu', $summary, $matches)) {
    $task = (int)$matches['id'];
}

//Если номер не обнаружен, то предлагаем пользователю его ввести
if ($task <= 0) {
    echo 'Номер задачи не найден' . PHP_EOL;
    echo 'Введите номер задачи на портале или нажмите Enter, чтобы пропустить: ';
    $count = fscanf(STDIN, "%d\n", $task);

    if ($count <= 0) {
        echo 'Номер задачи не введен.' . PHP_EOL;
        echo 'Информация о коммите не будет отправлена на портал' . PHP_EOL;
        echo 'Завершение хука' . PHP_EOL;
        exit(0);
    }
}

echo 'Отправка информации о коммите на портал' . PHP_EOL;

$message = <<<EOT
Новый набор изменений закоммичен пользователем $user в $pwd на $hostname:
$summary
======
Техническая информация о коммите:
$log

Список закоммиченных файлов (всего $filesCount шт):
$files

Комментарий сгенерирован автоматически.
EOT;

//Данные для отправки
$postData = [
    'message' => $message,
    'task'    => $task,
    'key'     => KEY
];

//Формируем запрос к приложению
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, BX24_APP_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
$result = curl_exec($ch);

echo $result . PHP_EOL;
echo 'Завершение хука' . PHP_EOL;