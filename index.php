<?php
session_start();
require_once 'includes/api.php';

// Установите ваш API логин
$apiLogin = '';

// Проверка токена
if (!isset($_SESSION['token']) || isTokenExpired($_SESSION['token'])) {
    if (!getAccessToken($apiLogin)) {
        http_response_code(500);
        echo json_encode(['error' => 'Не удалось получить токен авторизации.']);
        error_log("Ошибка: Не удалось получить токен авторизации.");
        exit;
    }
    error_log("Токен получен и сохранён в сессии.");
} else {
    error_log("Используем существующий токен из сессии.");
}

// Получаем организации
$organizationsResponse = getOrganizations();
if (isset($organizationsResponse['error'])) {
    die("Ошибка при получении организаций: " . $organizationsResponse['error']);
}

$organizations = $organizationsResponse['organizations'];

if (empty($organizations)) {
    die("Организации не найдены.");
}

// Определяем выбранную организацию
$selectedOrganizationId = null;
if (isset($_GET['orgId']) && !empty($_GET['orgId'])) {
    // Проверим, существует ли такая организация
    foreach ($organizations as $org) {
        if ($org['id'] === $_GET['orgId']) {
            $selectedOrganizationId = $_GET['orgId'];
            break;
        }
    }
}

// Если организация не выбрана или не найдена, берём первую
if (!$selectedOrganizationId) {
    $selectedOrganizationId = $organizations[0]['id'];
}

// Получаем номенклатуру для выбранной организации
$nomenclatureResponse = getNomenclature($selectedOrganizationId);
if (isset($nomenclatureResponse['error'])) {
    die("Ошибка при получении номенклатуры: " . $nomenclatureResponse['error']);
}

$groups = $nomenclatureResponse['groups'];
$products = $nomenclatureResponse['products'];
$sizes = $nomenclatureResponse['sizes'];

// Получаем зоны доставки для выбранной организации
$deliveryRestrictionsResponse = getDeliveryRestrictions([$selectedOrganizationId]);
if (isset($deliveryRestrictionsResponse['error'])) {
    error_log("Ошибка при получении зон доставки: " . $deliveryRestrictionsResponse['error']);
    $deliveryZones = [];
} else {
    // Извлекаем зоны
    $deliveryZones = [];
    if (!empty($deliveryRestrictionsResponse['deliveryRestrictions'])) {
        foreach ($deliveryRestrictionsResponse['deliveryRestrictions'] as $dr) {
            if (!empty($dr['deliveryZones'])) {
                foreach ($dr['deliveryZones'] as $zone) {
                    $deliveryZones[] = $zone;
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Номенклатура Syrve</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<div class="container">
    <!-- Форма для выбора организации -->
    <form method="GET" style="margin-bottom:20px;">
        <label for="orgId">Выберите организацию:</label>
        <select name="orgId" id="orgId" style="padding:5px;margin-left:10px;">
            <?php foreach ($organizations as $org): ?>
                <option value="<?php echo htmlspecialchars($org['id']); ?>" <?php if($org['id']===$selectedOrganizationId) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($org['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" style="padding:5px 10px;margin-left:10px;">Показать</button>
    </form>

    <h1>Номенклатура Организации: 
        <?php 
            // Найдём имя выбранной организации для вывода
            $selectedOrgName = 'Неизвестно';
            foreach ($organizations as $org) {
                if ($org['id'] === $selectedOrganizationId) {
                    $selectedOrgName = $org['name'];
                    break;
                }
            }
            echo htmlspecialchars($selectedOrgName); 
        ?>
    </h1>

    <!--  секция для отображения зон доставки -->
    <h2>Зоны Доставки</h2>
    <div class="delivery-zones-container">
        <?php if (!empty($deliveryZones)): ?>
            <?php 
            // Поскольку мы внутри foreach выше не сохранили $dr, используем текущий ответ
            // Проверим есть ли mapUrl в $deliveryRestrictionsResponse
            $mapUrl = "#";
            if (!empty($deliveryRestrictionsResponse['deliveryRestrictions'])) {
                $mapUrl = $deliveryRestrictionsResponse['deliveryRestrictions'][0]['deliveryRegionsMapUrl'] ?? "#";
            }
            foreach ($deliveryZones as $zone): ?>
                <div class="delivery-zone">
                    <h3><?php echo htmlspecialchars($zone['name']); ?></h3>
                    <p>Количество точек в зоне: <?php echo count($zone['coordinates']); ?></p>
                    <?php if($mapUrl !== "#"): ?>
                        <p><a href="<?php echo htmlspecialchars($mapUrl); ?>" target="_blank">Открыть карту зон</a></p>
                    <?php else: ?>
                        <p>Карта зон недоступна.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Зоны доставки не найдены.</p>
        <?php endif; ?>
    </div>

    <!-- Кнопка для показа/скрытия отладочной информации -->
    <button id="toggle-debug-btn" class="toggle-btn">Показать отладочную информацию</button>
    <!-- Кнопка для перехода на страницу создания доставки -->
    <a href="delivery.php" class="toggle-btn">Создать Доставку</a>

    <div id="filter-container">
        <input type="text" id="filter-input" placeholder="Фильтр по названию...">
        <select id="filter-select">
            <option value="all">Все</option>
            <option value="group">Группы</option>
            <option value="product">Продукты</option>
            <option value="size">Размеры</option>
        </select>
    </div>

    <div id="nomenclature">
        <h2>Группы</h2>
        <div class="groups">
            <?php foreach ($groups as $group): ?>
                <div class="group animate__animated animate__fadeIn">
                    <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                    <p><?php echo htmlspecialchars($group['description']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <h2>Продукты</h2>
        <div class="products">
            <?php foreach ($products as $product): ?>
                <div class="product animate__animated animate__fadeIn">
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p>Категория: <?php echo htmlspecialchars($product['productCategoryId']); ?></p>
                    <p>Тип: <?php echo htmlspecialchars($product['type']); ?></p>
                    <p>Вес: <?php echo htmlspecialchars($product['weight']); ?> г</p>
                    
                    <?php if (!empty($product['modifiers'])): ?>
                        <button class="toggle-modifier-btn" data-product-id="<?php echo htmlspecialchars($product['id']); ?>">
                            Показать модификаторы
                        </button>
                        <div class="modifiers-details" id="modifiers-<?php echo htmlspecialchars($product['id']); ?>" style="display: none;">
                            <?php foreach ($product['modifiers'] as $modifier): ?>
                                <div class="modifier">
                                    <p>ID: <?php echo htmlspecialchars($modifier['id']); ?></p>
                                    <p>Минимум: <?php echo htmlspecialchars($modifier['minAmount']); ?></p>
                                    <p>Максимум: <?php echo htmlspecialchars($modifier['maxAmount']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <h2>Размеры</h2>
        <div class="sizes">
            <?php foreach ($sizes as $size): ?>
                <div class="size animate__animated animate__fadeIn">
                    <h4><?php echo htmlspecialchars($size['name']); ?></h4>
                    <p>Приоритет: <?php echo htmlspecialchars($size['priority']); ?></p>
                    <p>По умолчанию: <?php echo $size['isDefault'] ? 'Да' : 'Нет'; ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <h2>Модификаторы</h2>
    <div class="modifiers">
        <?php foreach ($products as $product): ?>
            <?php if (!empty($product['modifiers'])): ?>
                <div class="modifier-group">
                    <h3>Модификаторы для <?php echo htmlspecialchars($product['name']); ?></h3>
                    <?php foreach ($product['modifiers'] as $modifier): ?>
                        <div class="modifier">
                            <p>ID: <?php echo htmlspecialchars($modifier['id']); ?></p>
                            <p>Минимум: <?php echo htmlspecialchars($modifier['minAmount']); ?></p>
                            <p>Максимум: <?php echo htmlspecialchars($modifier['maxAmount']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <h2>Групповые Модификаторы</h2>
    <div class="group-modifiers">
        <?php foreach ($products as $product): ?>
            <?php if (!empty($product['groupModifiers'])): ?>
                <div class="group-modifier-group">
                    <h3>Групповые Модификаторы для <?php echo htmlspecialchars($product['name']); ?></h3>
                    <?php foreach ($product['groupModifiers'] as $groupModifier): ?>
                        <div class="group-modifier">
                            <p>ID: <?php echo htmlspecialchars($groupModifier['id']); ?></p>
                            <p>Минимум: <?php echo htmlspecialchars($groupModifier['minAmount']); ?></p>
                            <p>Максимум: <?php echo htmlspecialchars($groupModifier['maxAmount']); ?></p>
                            <?php if (!empty($groupModifier['childModifiers'])): ?>
                                <button class="toggle-child-modifier-btn" data-group-modifier-id="<?php echo htmlspecialchars($groupModifier['id']); ?>">
                                    Показать дочерние модификаторы
                                </button>
                                <div class="child-modifiers-details" id="child-modifiers-<?php echo htmlspecialchars($groupModifier['id']); ?>" style="display: none;">
                                    <?php foreach ($groupModifier['childModifiers'] as $childModifier): ?>
                                        <div class="child-modifier">
                                            <p>ID: <?php echo htmlspecialchars($childModifier['id']); ?></p>
                                            <p>Минимум: <?php echo htmlspecialchars($childModifier['minAmount']); ?></p>
                                            <p>Максимум: <?php echo htmlspecialchars($childModifier['maxAmount']); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div id="debug-info" style="display: none;">
        <h2>Отладочная Информация</h2>
        <pre><?php print_r($nomenclatureResponse); ?></pre>
    </div>
</div>
<script src="js/scripts.js"></script>
</body>
</html>
