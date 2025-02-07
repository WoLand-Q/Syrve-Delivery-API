<?php

ini_set('error_log', __DIR__ . '/php-error.log');
ini_set('log_errors', 1);

/**
 * Проверяет, истёк ли токен.
 *
 * @param string $token Токен JWT.
 * @return bool true, если токен истёк или неверен, иначе false.
 */
function isTokenExpired($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return true;
    }

    $payload = $parts[1];
    $decoded = base64_decode(strtr($payload, '-_', '+/'));
    if (!$decoded) {
        return true;
    }

    $data = json_decode($decoded, true);
    if (!$data || !isset($data['exp'])) {
        return true;
    }

    $exp = $data['exp'];
    $now = time();

    return ($exp <= $now);
}

/**
 * Выполняет POST-запрос к API Syrve.
 *
 * @param string $url URL эндпоинта.
 * @param array $data Данные для отправки.
 * @param int $timeout Время ожидания в секундах.
 * @return array Ответ API.
 */
function postRequest($url, $data, $timeout = 15) {
    $ch = curl_init($url);
    $payload = json_encode($data);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $headers = [
        'Content-Type: application/json'
    ];

    // Добавляем заголовок авторизации, если токен установлен
    global $token;
    if (isset($token)) {
        $authHeader = 'Authorization: Bearer ' . $token;
        $headers[] = $authHeader;
        error_log("postRequest: Добавлен заголовок авторизации: " . $authHeader);
    } else {
        error_log("postRequest: Токен авторизации НЕ установлен.");
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    $result = curl_exec($ch);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        error_log("postRequest: cURL ошибка: " . $error_msg);
        return ['error' => $error_msg];
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 400) {
        error_log("postRequest: HTTP Error $http_code. Ответ API: " . $result);
        return ['error' => "HTTP Error: $http_code", 'response' => $result];
    }

    // Логирование успешного ответа
    error_log("postRequest: Успешный ответ от $url: " . $result);

    return json_decode($result, true);
}

/**
 * Получает токен авторизации.
 *
 * @param string $apiLogin API логин.
 * @return bool Успех или нет.
 */
function getAccessToken($apiLogin) {
    global $token;

    $url = "https://api-eu.syrve.live/api/1/access_token";
    $data = ["apiLogin" => $apiLogin];

    error_log("getAccessToken: Отправка запроса к $url с данными: " . json_encode($data));

    $response = postRequest($url, $data);
    error_log("getAccessToken: Ответ от API: " . print_r($response, true));

    if (isset($response['token'])) {
        $token = $response['token'];
        error_log("getAccessToken: Токен успешно получен: " . $token);
        return true;
    } else {
        error_log("Не удалось получить токен авторизации. Ответ API: " . print_r($response, true));
        return false;
    }
}

/**
 * Получает зоны доставки.
 * @param array $organizationIds
 * @return array
 */
function getDeliveryRestrictions($organizationIds) {
    $url = "https://api-eu.syrve.live/api/1/delivery_restrictions";
    $data = [
        "organizationIds" => $organizationIds
    ];

    error_log("getDeliveryRestrictions: Отправка запроса к $url с данными: " . json_encode($data));
    $response = postRequest($url, $data);
    if (isset($response['error'])) {
        error_log("Ошибка при получении зон доставки: " . $response['error']);
    } else {
        error_log("getDeliveryRestrictions: Успешно получены зоны доставки.");
    }
    return $response;
}
/**
 * Получает организации.
 *
 * @return array Организации или ошибка.
 */
function getOrganizations() {
    $url = "https://api-eu.syrve.live/api/1/organizations";
    $data = [
        "organizationIds" => [],
        "returnAdditionalInfo" => true,
        "includeDisabled" => true,
        "returnExternalData" => []
    ];

    error_log("getOrganizations: Отправка запроса к $url с данными: " . json_encode($data));

    $response = postRequest($url, $data);
    if (isset($response['error'])) {
        error_log("Ошибка при получении организаций: " . $response['error']);
    } else {
        error_log("getOrganizations: Успешно получены организации.");
    }
    return $response;
}

/**
 * Получает группы терминалов для заданной организации.
 *
 * @param array $organizationIds Массив ID организаций.
 * @return array Группы терминалов или ошибка.
 */
function getTerminalGroups($organizationIds) {
    $url = "https://api-eu.syrve.live/api/1/terminal_groups";
    $data = [
        "organizationIds" => $organizationIds,
        "includeDisabled" => true,
        "returnExternalData" => []
    ];

    error_log("getTerminalGroups: Отправка запроса к $url с данными: " . json_encode($data));

    $response = postRequest($url, $data);
    if (isset($response['error'])) {
        error_log("Ошибка при получении групп терминалов: " . $response['error']);
    } else {
        error_log("getTerminalGroups: Успешно получены группы терминалов.");
    }
    return $response;
}

/**
 * Получает типы оплаты для заданных организаций.
 *
 * @param array $organizationIds Массив ID организаций.
 * @return array Типы оплаты или ошибка.
 */
function getPaymentTypes($organizationIds) {
    $url = "https://api-eu.syrve.live/api/1/payment_types";
    $data = [
        "organizationIds" => $organizationIds
    ];

    error_log("getPaymentTypes: Отправка запроса к $url с данными: " . json_encode($data));

    $response = postRequest($url, $data);
    if (isset($response['error'])) {
        error_log("Ошибка при получении типов оплаты: " . $response['error']);
    } else {
        error_log("getPaymentTypes: Успешно получены типы оплаты.");
    }
    return $response;
}

/**
 * Получает номенклатуру для заданной организации.
 *
 * @param string $organizationId ID организации.
 * @return array Номенклатура или ошибка.
 */
function getNomenclature($organizationId) {
    $url = "https://api-eu.syrve.live/api/1/nomenclature";
    $data = [
        "organizationId" => $organizationId,
        "startRevision" => 0
    ];

    error_log("getNomenclature: Отправка запроса к $url с данными: " . json_encode($data));

    $response = postRequest($url, $data);
    if (isset($response['error'])) {
        error_log("Ошибка при получении номенклатуры: " . $response['error']);
    } else {
        error_log("getNomenclature: Успешно получена номенклатура.");
    }
    return $response;
}

/**
 * Получает города через API.
 *
 * @param string $organizationId ID организации.
 * @return array Города или ошибка.
 */
function getCities($organizationId) {
    $url = 'https://api-eu.syrve.live/api/1/cities';
    $data = [
        "organizationIds" => [$organizationId],
        "includeDeleted" => false
    ];

    error_log("getCities: Отправка запроса к $url с данными: " . json_encode($data));

    $response = postRequest($url, $data);

    error_log("getCities: Ответ от API: " . print_r($response, true));

    if (isset($response['cities']) && is_array($response['cities'])) {
        $cities = [];
        foreach ($response['cities'] as $cityGroup) {
            if (isset($cityGroup['items']) && is_array($cityGroup['items'])) {
                foreach ($cityGroup['items'] as $city) {
                    // Проверяем, если город не удалён
                    if (!$city['isDeleted']) {
                        $cities[] = [
                            'id' => $city['id'],
                            'name' => $city['name']
                        ];
                    }
                }
            }
        }
        error_log("getCities: Успешно получены города.");
        return $cities;
    } else {
        error_log("getCities: Не удалось получить города. Ответ API: " . print_r($response, true));
        return [];
    }
}

/**
 * Получает улицы по ID города и организации.
 *
 * @param string $organizationId ID организации.
 * @param string $cityId ID города.
 * @return array Улицы или ошибка.
 */
function getStreetsByCity($organizationId, $cityId) {
    $url = 'https://api-eu.syrve.live/api/1/streets/by_city';
    $data = [
        "organizationId" => $organizationId,
        "cityId" => $cityId,
        "includeDeleted" => false
    ];

    error_log("getStreetsByCity: Отправка запроса к $url с данными: " . json_encode($data));

    $response = postRequest($url, $data);

    error_log("getStreetsByCity: Ответ от API: " . print_r($response, true));

    if (isset($response['streets']) && is_array($response['streets'])) {
        $streets = [];
        foreach ($response['streets'] as $street) {
            // Проверяем, если улица не удалена
            if (!$street['isDeleted']) {
                $streets[] = [
                    'id' => $street['id'],
                    'name' => $street['name']
                ];
            }
        }
        error_log("getStreetsByCity: Успешно получены улицы.");
        return $streets;
    } else {
        error_log("getStreetsByCity: Не удалось получить улицы. Ответ API: " . print_r($response, true));
        return [];
    }
}

/**
 * Создает доставку.
 *
 * @param array $deliveryData Данные доставки.
 * @return array Ответ API.
 */
function createDelivery($deliveryData) {
    $url = "https://api-eu.syrve.live/api/1/deliveries/create";

    error_log("createDelivery: Отправка запроса к $url с данными: " . json_encode($deliveryData));

    $response = postRequest($url, $deliveryData);
    if (isset($response['error'])) {
        error_log("createDelivery: Ошибка при создании доставки: " . $response['error']);
    } else {
        error_log("createDelivery: Доставка успешно создана.");
    }
    return $response;
}
?>
