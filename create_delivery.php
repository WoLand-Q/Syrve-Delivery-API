<?php
// Включение отображения ошибок для отладки (удалите на продакшене)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Заголовки для JSON ответа
header('Content-Type: application/json');

// Подключение файла с функциями API
require_once 'includes/api.php';

// Получение данных из POST-запроса
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Проверка успешности декодирования JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Некорректный JSON.']);
    error_log("create_delivery.php: Некорректный JSON. Входные данные: " . $input);
    exit;
}

// Установите ваш API логин
$apiLogin = ''; // Замените на ваш API логин

// Получение токена
if (!getAccessToken($apiLogin)) {
    http_response_code(500);
    echo json_encode(['error' => 'Не удалось получить токен авторизации.']);
    exit;
}

// Вызов функции для создания доставки
$response = createDelivery($data);

// Проверка наличия ошибок
if (isset($response['error'])) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Ошибка при создании доставки: ' . $response['error']]);
    error_log("create_delivery.php: Ошибка при создании доставки: " . $response['error']);
    exit;
}

// Отправка успешного ответа
http_response_code(200);
echo json_encode(['success' => 'Доставка успешно создана.']);
exit;
?>
