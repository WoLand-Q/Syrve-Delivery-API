<?php
// Включение отображения ошибок для отладки (удалите на продакшене)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Заголовок для JSON ответа
header('Content-Type: application/json');

// Подключение файла с функциями API
require_once 'includes/api.php';

// Проверка наличия необходимых параметров
if (!isset($_GET['organizationId']) || !isset($_GET['cityId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Отсутствуют необходимые параметры.']);
    exit;
}

$organizationId = $_GET['organizationId'];
$cityId = $_GET['cityId'];

// Установите ваш API логин
$apiLogin = '4b86f201-c265-40e6-98ce-bc7b2f6be667'; // Замените на ваш API логин

// Получение токена
if (!getAccessToken($apiLogin)) {
    http_response_code(500);
    echo json_encode(['error' => 'Не удалось получить токен авторизации.']);
    exit;
}

// Получение улиц
$streets = getStreetsByCity($organizationId, $cityId);

// Проверка успешности получения улиц
if (empty($streets)) {
    http_response_code(404);
    echo json_encode(['error' => 'Улицы не найдены или произошла ошибка.']);
    exit;
}

// Отправка успешного ответа
echo json_encode(['streets' => $streets]);
exit;
?>
