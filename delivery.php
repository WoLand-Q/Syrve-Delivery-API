<?php
// delivery.php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once 'includes/api.php';

$apiLogin = '';

if (!getAccessToken($apiLogin)) {
    die("Не удалось получить токен авторизации.");
}

$organizationId = "";

$terminalGroupsResponse = getTerminalGroups([$organizationId]);
if (isset($terminalGroupsResponse['error'])) {
    die("Ошибка при получении групп терминалов: " . $terminalGroupsResponse['error']);
}

$terminalGroups = $terminalGroupsResponse['terminalGroups'] ?? [];

$paymentTypesResponse = getPaymentTypes([$organizationId]);
if (isset($paymentTypesResponse['error'])) {
    die("Ошибка при получении типов оплаты: " . $paymentTypesResponse['error']);
}
$paymentTypes = $paymentTypesResponse['paymentTypes'] ?? [];

$nomenclatureResponse = getNomenclature($organizationId);
if (isset($nomenclatureResponse['error'])) {
    die("Ошибка при получении номенклатуры: " . $nomenclatureResponse['error']);
}
$products = $nomenclatureResponse['products'] ?? [];

$cities = getCities($organizationId);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Создание Доставки на Терминал</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .product-item {
            border: 1px solid #ccc;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #f9f9f9;
        }

        .cart {
            border: 1px solid #28a745;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
            background-color: #e9ffe9;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .remove-item-btn {
            background-color: #dc3545;
            color: #fff;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
        }

        .submit-btn {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
        }

        .submit-btn:hover {
            background-color: #0056b3;
        }

        .error {
            color: #dc3545;
            margin-top: 5px;
        }

        #loader {
            display: none;
            margin-top: 10px;
            font-size: 16px;
            color: #007bff;
            display: flex;
            align-items: center;
        }

        #loader::before {
            content: '';
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            margin-right: 10px;
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        #products-list {
            max-height: 600px;
            overflow-y: auto;
        }

        #request-body {
            background-color: #f8f9fa;
            border: 1px solid #ced4da;
            padding: 15px;
            border-radius: 5px;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 20px;
            display: none;
        }

        #client-logs {
            background-color: #f1f1f1;
            border: 1px solid #ccc;
            padding: 15px;
            border-radius: 5px;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 20px;
        }

    </style>
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4">Создание Доставки на Терминал</h1>

    <button id="toggle-debug-btn" class="btn btn-secondary mb-3">Показать отладочную информацию</button>

    <div id="debug-info" class="mb-3" style="display: none;">
        <h2>Отладочная Информация</h2>
        <pre>
Организации:
<?php
$organizationsResponse = getOrganizations();
print_r($organizationsResponse);
?>

Основная организация ID:
<?php echo htmlspecialchars($organizationId); ?>

Группы терминалов:
<?php print_r($terminalGroupsResponse); ?>

Типы оплаты:
<?php print_r($paymentTypesResponse); ?>

Номенклатура:
<?php print_r($nomenclatureResponse); ?>

Города:
<?php print_r($cities); ?>
        </pre>
    </div>

    <div id="client-logs">
        <h5>Логи Клиентской Стороны:</h5>
        <pre id="log-output"></pre>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group mb-3">
                <label for="terminal-select" class="form-label">Выберите Терминал:</label>
                <select id="terminal-select" class="form-select" required>
                    <option value="">-- Выберите терминал --</option>
                    <?php foreach ($terminalGroups as $group): ?>
                        <?php foreach ($group['items'] as $terminal): ?>
                            <option value="<?php echo htmlspecialchars($terminal['id']); ?>">
                                <?php echo htmlspecialchars($terminal['name'] . " - " . $terminal['address']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <input type="text" id="search-products" class="form-control" placeholder="Поиск продуктов...">
            </div>

            <h2 class="mb-3">Выберите Продукты:</h2>
            <div id="products-list">
                <?php
                function displayProducts($products) {
                    foreach ($products as $product) {
                        $priceMin = 50;
                        $priceMax = 300;
                        $price = rand($priceMin, $priceMax);
                        echo '<div class="product-item mb-2 p-3 shadow-sm rounded">';
                        echo '<div>';
                        echo '<strong>' . htmlspecialchars($product['name']) . '</strong><br>';
                        echo 'Цена: ' . htmlspecialchars($price) . ' грн';
                        echo '</div>';
                        echo '<button class="btn btn-primary add-to-cart-btn" ';
                        echo 'data-product-id="' . htmlspecialchars($product['id']) . '" ';
                        echo 'data-product-name="' . htmlspecialchars($product['name']) . '" ';
                        echo 'data-price="' . htmlspecialchars($price) . '">Добавить</button>';
                        echo '</div>';
                    }
                }

                displayProducts($products);
                ?>
            </div>
        </div>

        <div class="col-md-6">
            <h2 class="mb-3">Корзина:</h2>
            <div class="cart p-3 shadow-sm rounded">
                <div id="cart-items">
                    <p>Корзина пуста.</p>
                </div>
                <p><strong>Итого:</strong> <span id="cart-total">0.00</span> грн</p>
            </div>
        </div>
    </div>

    <h2 class="mt-5 mb-3">Детали Доставки:</h2>
    <form id="delivery-form">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="name" class="form-label">Имя:</label>
                <input type="text" id="name" name="name" class="form-control" placeholder="Введите ваше имя" required>
                <div class="error" id="name-error"></div>
            </div>

            <div class="col-md-6 mb-3">
                <label for="phone" class="form-label">Телефон:</label>
                <input type="text" id="phone" name="phone" class="form-control" placeholder="+380637468921" required>
                <div class="error" id="phone-error"></div>
            </div>

            <div class="col-md-6 mb-3">
                <label for="complete-before" class="form-label">Время Доставки:</label>
                <input type="datetime-local" id="complete-before" name="completeBefore" class="form-control">
            </div>
        </div>

        <div class="mb-3">
            <label for="comment" class="form-label">Комментарий:</label>
            <textarea id="comment" name="comment" class="form-control" rows="3" placeholder="Пожалуйста, позвоните при доставке"></textarea>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="payment-type" class="form-label">Тип Оплаты:</label>
                <select id="payment-type" name="paymentTypeId" class="form-select" required>
                    <option value="">-- Выберите тип оплаты --</option>
                    <?php foreach ($paymentTypes as $paymentType): ?>
                        <?php
                            if (!isset($paymentType['kind'])) {
                                if (stripos($paymentType['name'], 'cash') !== false) {
                                    $paymentType['kind'] = 'Cash';
                                } elseif (stripos($paymentType['name'], 'card') !== false) {
                                    $paymentType['kind'] = 'Card';
                                } else {
                                    $paymentType['kind'] = 'Online';
                                }
                            }
                        ?>
                        <option value="<?php echo htmlspecialchars($paymentType['id']); ?>" data-kind="<?php echo htmlspecialchars($paymentType['kind']); ?>">
                            <?php echo htmlspecialchars($paymentType['name']); ?> (<?php echo htmlspecialchars($paymentType['kind']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label for="payment-type-kind" class="form-label">Тип Оплаты Kind:</label>
                <select id="payment-type-kind" name="paymentTypeKind" class="form-select" required>
                    <option value="">-- Выберите kind оплаты --</option>
                    <option value="Cash">Cash</option>
                    <option value="Card">Card</option>
                    <option value="Online">Online</option>
                </select>
            </div>
        </div>

        <!-- Выбор типа адреса -->
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="address-type" class="form-label">Тип адреса(Переключите повторно если улицы не подтягиваются):</label>
                <select id="address-type" name="addressType" class="form-select" required>
                    <option value="legacy">Legacy (из справочников)</option>
                    <option value="city">City (ввести вручную)</option>
                </select>
            </div>
        </div>

        <div id="legacy-address-fields">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="city" class="form-label">Город (legacy):</label>
                    <select id="city" name="cityId" class="form-select">
                        <option value="">-- Выберите город --</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo htmlspecialchars($city['id']); ?>">
                                <?php echo htmlspecialchars($city['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="street" class="form-label">Улица (legacy):</label>
                    <select id="street" name="streetId" class="form-select" disabled>
                        <option value="">-- Выберите улицу --</option>
                    </select>
                </div>
            </div>
        </div>

        <div id="city-address-fields" style="display:none;">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="cityName" class="form-label">Город (city):</label>
                    <input type="text" id="cityName" name="cityName" class="form-control" placeholder="Введите название города">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="streetName" class="form-label">Улица (city):</label>
                    <input type="text" id="streetName" name="streetName" class="form-control" placeholder="Введите название улицы">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="house" class="form-label">Дом:</label>
                <input type="text" id="house" name="house" class="form-control" placeholder="Введите номер дома" required>
            </div>

            <div class="col-md-4 mb-3">
                <label for="flat" class="form-label">Квартира:</label>
                <input type="text" id="flat" name="flat" class="form-control" placeholder="Введите номер квартиры">
            </div>

            <div class="col-md-2 mb-3">
                <label for="entrance" class="form-label">Подъезд:</label>
                <input type="text" id="entrance" name="entrance" class="form-control" placeholder="Введите номер подъезда">
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="floor" class="form-label">Этаж:</label>
                <input type="text" id="floor" name="floor" class="form-control" placeholder="Введите этаж">
            </div>
        </div>

        <button type="submit" class="btn btn-success">Создать Доставку</button>
        <div id="loader" class="mt-3">Отправка...</div>
    </form>

    <div id="request-body">
        <h3>Сформированный Запрос:</h3>
        <pre id="request-json"></pre>
    </div>
</div>
        
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', () => {

        const toggleDebugBtn = document.getElementById('toggle-debug-btn');
        const debugInfo = document.getElementById('debug-info');
        const logOutput = document.getElementById('log-output');
        const addToCartBtns = document.querySelectorAll('.add-to-cart-btn');
        const cartItemsContainer = document.getElementById('cart-items');
        const cartTotal = document.getElementById('cart-total');
        let cart = [];

        const legacyAddressFields = document.getElementById('legacy-address-fields');
        const cityAddressFields = document.getElementById('city-address-fields');
        const addressTypeSelect = document.getElementById('address-type');

        addressTypeSelect.addEventListener('change', () => {
            const val = addressTypeSelect.value;
            if (val === 'city') {
                legacyAddressFields.style.display = 'none';
                cityAddressFields.style.display = 'block';
            } else {
                legacyAddressFields.style.display = 'block';
                cityAddressFields.style.display = 'none';
            }
            updateRequestBody();
        });

        function addLog(message) {
            if (logOutput) {
                const timestamp = new Date().toLocaleTimeString();
                const fullMessage = `[${timestamp}] ${message}\n`;
                logOutput.textContent += fullMessage;
                logOutput.parentElement.scrollTop = logOutput.parentElement.scrollHeight;
            }
        }

        const originalConsoleLog = console.log;
        console.log = function(...args) {
            originalConsoleLog.apply(console, args);
            addLog(args.join(' '));
        };

        if (toggleDebugBtn && debugInfo) {
            toggleDebugBtn.addEventListener('click', () => {
                if (debugInfo.style.display === 'none') {
                    debugInfo.style.display = 'block';
                    toggleDebugBtn.textContent = 'Скрыть отладочную информацию';
                    debugInfo.classList.add('animate__animated', 'animate__fadeIn');
                } else {
                    debugInfo.style.display = 'none';
                    toggleDebugBtn.textContent = 'Показать отладочную информацию';
                }
            });
        }

        addToCartBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const productId = btn.getAttribute('data-product-id');
                const productName = btn.getAttribute('data-product-name');
                const price = parseFloat(btn.getAttribute('data-price'));

                if (isNaN(price) || price <= 0) {
                    alert('Цена товара не установлена.');
                    console.log('Ошибка: Цена товара не установлена для продукта ID ' + productId);
                    return;
                }

                const existingItem = cart.find(item => item.id === productId);
                if (existingItem) {
                    existingItem.amount += 1;
                } else {
                    cart.push({ id: productId, name: productName, price: price, amount: 1 });
                }

                console.log('Добавлено в корзину:', { id: productId, name: productName, price: price, amount: 1 });
                console.log('Текущая корзина:', cart);
                updateCart();
                updateRequestBody();
            });
        });

        function removeFromCart(productId) {
            cart = cart.filter(item => item.id !== productId);
            console.log('Удалено из корзины:', productId);
            console.log('Текущая корзина:', cart);
            updateCart();
            updateRequestBody();
        }

        function updateCart() {
            cartItemsContainer.innerHTML = '';
            let total = 0;

            if (cart.length === 0) {
                cartItemsContainer.innerHTML = '<p>Корзина пуста.</p>';
            } else {
                cart.forEach(item => {
                    const itemDiv = document.createElement('div');
                    itemDiv.classList.add('cart-item', 'mb-2');
                    itemDiv.innerHTML = `
                        <span>${item.name} x ${item.amount}</span>
                        <span>${(item.price * item.amount).toFixed(2)} грн</span>
                        <button class="btn btn-sm btn-danger remove-item-btn" data-product-id="${item.id}">Удалить</button>
                    `;
                    cartItemsContainer.appendChild(itemDiv);
                    total += item.price * item.amount;
                });

                const removeBtns = document.querySelectorAll('.remove-item-btn');
                removeBtns.forEach(btn => {
                    btn.addEventListener('click', () => {
                        const productId = btn.getAttribute('data-product-id');
                        removeFromCart(productId);
                    });
                });
            }

            cartTotal.textContent = total.toFixed(2);
            console.log('Обновленная корзина:', cart);
        }

        const citySelect = document.getElementById('city');
        const streetSelect = document.getElementById('street');
        if (citySelect && streetSelect) {
            citySelect.addEventListener('change', () => {
                const cityId = citySelect.value;
                const organizationId = "<?php echo htmlspecialchars($organizationId); ?>";

                if (cityId) {
                    streetSelect.innerHTML = '<option value="">Загрузка улиц...</option>';
                    streetSelect.disabled = true;

                    fetch(`get_streets.php?organizationId=${encodeURIComponent(organizationId)}&cityId=${encodeURIComponent(cityId)}`, {
                        method: 'GET'
                    })
                    .then(response => response.json())
                    .then(data => {
                        streetSelect.innerHTML = '<option value="">-- Выберите улицу --</option>';
                        if (data.streets && Array.isArray(data.streets) && data.streets.length > 0) {
                            data.streets.forEach(street => {
                                const option = document.createElement('option');
                                option.value = street.id;
                                option.textContent = street.name;
                                streetSelect.appendChild(option);
                            });
                            streetSelect.disabled = false;
                            alert('Улицы успешно загружены.');
                        } else if (data.error) {
                            streetSelect.innerHTML = `<option value="">${data.error}</option>`;
                            alert(data.error);
                        } else {
                            streetSelect.innerHTML = '<option value="">Улицы не найдены</option>';
                            alert('Улицы не найдены.');
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка при загрузке улиц:', error);
                        streetSelect.innerHTML = '<option value="">Ошибка загрузки улиц</option>';
                        alert('Ошибка загрузки улиц: ' + (error.message || error));
                    });
                } else {
                    streetSelect.innerHTML = '<option value="">-- Выберите улицу --</option>';
                    streetSelect.disabled = true;
                }
            });
        }

        const deliveryForm = document.getElementById('delivery-form');
        const loader = document.getElementById('loader');
        const requestJson = document.getElementById('request-json');
        const requestBody = document.getElementById('request-body');

        if (deliveryForm && loader) {
            deliveryForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                const nameInput = document.getElementById('name');
                const nameError = document.getElementById('name-error');
                const nameValue = nameInput.value.trim();

                const phoneInput = document.getElementById('phone');
                const phoneError = document.getElementById('phone-error');
                const phoneValue = phoneInput.value.trim();

                if (nameValue === "") {
                    nameError.textContent = 'Пожалуйста, введите ваше имя.';
                    console.log('Ошибка: Не введено имя.');
                    return;
                } else {
                    nameError.textContent = '';
                }

                const phoneRegex = /^\+\d{8,40}$/;
                if (!phoneRegex.test(phoneValue)) {
                    phoneError.textContent = 'Введите корректный телефон в формате +XXXXXXXXXX';
                    console.log('Ошибка: Некорректный формат телефона: ' + phoneValue);
                    return;
                } else {
                    phoneError.textContent = '';
                }

                if (cart.length === 0) {
                    alert('Корзина пуста. Добавьте товар.');
                    console.log('Предупреждение: Корзина пуста.');
                    return;
                }

                const terminalSelect = document.getElementById('terminal-select');
                const terminalId = terminalSelect.value;
                if (!terminalId) {
                    alert('Выберите терминал.');
                    console.log('Предупреждение: Терминал не выбран.');
                    return;
                }

                const paymentTypeSelect = document.getElementById('payment-type');
                const paymentTypeId = paymentTypeSelect.value;

                const paymentTypeKindSelect = document.getElementById('payment-type-kind');
                const paymentTypeKind = paymentTypeKindSelect.value;

                if (!paymentTypeId) {
                    alert('Выберите тип оплаты.');
                    console.log('Предупреждение: Тип оплаты не выбран.');
                    return;
                }

                if (!paymentTypeKind) {
                    alert('Выберите kind оплаты.');
                    console.log('Предупреждение: Тип оплаты kind не выбран.');
                    return;
                }

                const addressType = document.getElementById('address-type').value;

                let cityId = null;
                let streetId = null;
                let cityNameValue = '';
                let streetNameValue = '';

                if (addressType === 'legacy') {
                    const citySelect = document.getElementById('city');
                    cityId = citySelect.value;
                    if (!cityId) {
                        alert('Выберите город (legacy).');
                        console.log('Предупреждение: Город (legacy) не выбран.');
                        return;
                    }
                    const streetSelect = document.getElementById('street');
                    streetId = streetSelect.value;
                    if (!streetId) {
                        alert('Выберите улицу (legacy).');
                        console.log('Предупреждение: Улица (legacy) не выбрана.');
                        return;
                    }
                } else {
                    const cityNameInput = document.getElementById('cityName');
                    cityNameValue = cityNameInput.value.trim();
                    if (!cityNameValue) {
                        alert('Введите название города (city).');
                        console.log('Предупреждение: Город (city) не введен.');
                        return;
                    }

                    const streetNameInput = document.getElementById('streetName');
                    streetNameValue = streetNameInput.value.trim();
                    if (!streetNameValue) {
                        alert('Введите название улицы (city).');
                        console.log('Предупреждение: Улица (city) не введена.');
                        return;
                    }
                }

                const house = document.getElementById('house').value.trim();
                const flat = document.getElementById('flat').value.trim();
                const entrance = document.getElementById('entrance').value.trim();
                const floor = document.getElementById('floor').value.trim();

                if (!house) {
                    alert('Введите номер дома.');
                    console.log('Ошибка: Не введён номер дома.');
                    return;
                }

                const completeBefore = document.getElementById('complete-before').value;
                const comment = document.getElementById('comment').value.trim();

                const items = cart.map(item => ({
                    type: "Product",
                    productId: item.id,
                    amount: item.amount,
                    price: item.price,
                    comment: "Без комментария"
                }));

                const externalNumber = 'ORDER_' + Date.now();

                let addressObj;
                if (addressType === 'legacy') {
                    addressObj = {
                        "type": "legacy",
                        "cityId": cityId,
                        "street": {
                            "id": streetId
                        },
                        "house": house,
                        "flat": flat,
                        "entrance": entrance,
                        "floor": floor
                    };
                } else {
                    const line1 = cityNameValue + ", " + streetNameValue + ", " + house;
                    addressObj = {
        				"city": cityNameValue,
        				"street": {
            					"name": streetNameValue
        						},
        				"house": house,
        				"flat": flat,
        				"entrance": entrance,
        				"floor": floor
    				};
                }

                const deliveryData = {
                    "organizationId": "<?php echo htmlspecialchars($organizationId); ?>",
                    "terminalGroupId": terminalId,
                    "createOrderSettings": {
                        "transportToFrontTimeout": 0,
                        "checkStopList": false
                    },
                    "order": {
                        "externalNumber": externalNumber,
                        "phone": phoneValue,
                        "orderTypeId": "76067ea3-356f-eb93-9d14-1fa00d082c4e",
                        "deliveryPoint": {
                            "coordinates": {
                                "latitude": 50.427534,
                                "longitude": 30.424037
                            },
                            "address": addressObj,
                            "comment": "Оставить у двери"
                        },
                        "comment": comment,
                        "customer": {
                            "type": "regular",
                            "name": nameValue
                        },
                        "guests": {
                            "count": 1,
                            "splitBetweenPersons": true,
                            "details": {
                                "guestName": nameValue,
                                "guestPhone": phoneValue,
                                "specialRequests": "Без лука"
                            }
                        },
                        "items": items,
                        "payments": [
                            {
                                "sum": parseFloat(document.getElementById('cart-total').textContent),
                                "paymentTypeId": paymentTypeId,
                                "paymentTypeKind": paymentTypeKind,
                                "isProcessedExternally": false,
                                "isFiscalizedExternally": false,
                                "isPrepay": false
                            }
                        ],
                        "loyaltyInfo": {
                            "coupon": ""
                        },
                        "sourceKey": "febrein_delivery"
                    }
                };

                requestJson.textContent = JSON.stringify(deliveryData, null, 4);
                requestBody.style.display = 'block';

                console.log('Отправляемые данные:', deliveryData);

                loader.style.display = 'flex';

                try {
                    const response = await fetch('create_delivery.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(deliveryData)
                    });

                    loader.style.display = 'none';

                    const result = await response.json();

                    console.log('Ответ от create_delivery.php:', result);

                    if (response.ok) {
                        alert('Доставка успешно создана!');
                        console.log('Доставка успешно создана. ExternalNumber: ' + externalNumber);
                        cart = [];
                        updateCart();
                        deliveryForm.reset();
                        const streetSel = document.getElementById('street');
                        if (streetSel) {
                            streetSel.innerHTML = '<option value="">-- Выберите улицу --</option>';
                            streetSel.disabled = true;
                        }
                        requestBody.style.display = 'none';
                    } else {
                        alert('Ошибка при создании доставки: ' + (result.error || 'Неизвестная ошибка'));
                        console.log('Ошибка при создании доставки:', result);
                    }
                } catch (error) {
                    loader.style.display = 'none';
                    alert('Произошла ошибка при отправке запроса: ' + error.message);
                    console.log('Ошибка при отправке запроса:', error);
                }
            });
        }

        const searchInput = document.getElementById('search-products');
        const productsList = document.getElementById('products-list');

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                const query = searchInput.value.toLowerCase();
                const products = productsList.querySelectorAll('.product-item');

                products.forEach(product => {
                    const name = product.querySelector('strong').textContent.toLowerCase();
                    if (name.includes(query)) {
                        product.style.display = 'flex';
                    } else {
                        product.style.display = 'none';
                    }
                });

                updateRequestBody();
            });
        }

        function updateRequestBody() {
            const name = document.getElementById('name').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const completeBefore = document.getElementById('complete-before').value;
            const comment = document.getElementById('comment').value.trim();
            const paymentTypeId = document.getElementById('payment-type').value;
            const paymentTypeKind = document.getElementById('payment-type-kind').value;
            const addressType = document.getElementById('address-type').value;

            let cityId = '';
            let streetId = '';
            let cityNameValue = '';
            let streetNameValue = '';

            if (addressType === 'legacy') {
                cityId = document.getElementById('city').value;
                streetId = document.getElementById('street').value;
            } else {
                cityNameValue = document.getElementById('cityName').value.trim();
                streetNameValue = document.getElementById('streetName').value.trim();
            }

            const house = document.getElementById('house').value.trim();
            const flat = document.getElementById('flat').value.trim();
            const entrance = document.getElementById('entrance').value.trim();
            const floor = document.getElementById('floor').value.trim();

            const items = cart.map(item => ({
                type: "Product",
                productId: item.id,
                amount: item.amount,
                price: item.price,
                comment: "Без комментария"
            }));

            const externalNumber = 'ORDER_' + Date.now();

            let addressObj;
            if (addressType === 'legacy') {
                addressObj = {
                    "type": "legacy",
                    "cityId": cityId,
                    "street": { "id": streetId },
                    "house": house,
                    "flat": flat,
                    "entrance": entrance,
                    "floor": floor
                };
            } else {
                const line1 = cityNameValue + ", " + streetNameValue + ", " + house;
                addressObj = {
                    "type": "city",
                    "line1": line1,
                    "flat": flat,
                    "entrance": entrance,
                    "floor": floor
                };
            }

            const deliveryData = {
                "organizationId": "<?php echo htmlspecialchars($organizationId); ?>",
                "terminalGroupId": document.getElementById('terminal-select').value,
                "createOrderSettings": {
                    "transportToFrontTimeout": 0,
                    "checkStopList": false
                },
                "order": {
                    "externalNumber": externalNumber,
                    "phone": phone,
                    "orderTypeId": "76067ea3-356f-eb93-9d14-1fa00d082c4e",
                    "deliveryPoint": {
                        "coordinates": {
                            "latitude": 50.427534,
                            "longitude": 30.424037
                        },
                        "address": addressObj,
                        "comment": "Оставить у двери"
                    },
                    "comment": comment,
                    "customer": {
                        "type": "regular",
                        "name": name
                    },
                    "guests": {
                        "count": 1,
                        "splitBetweenPersons": true,
                        "details": {
                            "guestName": name,
                            "guestPhone": phone,
                            "specialRequests": "Без лука"
                        }
                    },
                    "items": items,
                    "payments": [
                        {
                            "sum": parseFloat(document.getElementById('cart-total').textContent),
                            "paymentTypeId": paymentTypeId,
                            "paymentTypeKind": paymentTypeKind,
                            "isProcessedExternally": false,
                            "isFiscalizedExternally": false,
                            "isPrepay": false
                        }
                    ],
                    "loyaltyInfo": {
                        "coupon": ""
                    },
                    "sourceKey": "febrein_delivery"
                }
            };

            const requestJson = document.getElementById('request-json');
            const requestBody = document.getElementById('request-body');
            if (requestJson && requestBody) {
                requestJson.textContent = JSON.stringify(deliveryData, null, 4);
                requestBody.style.display = 'block';
            }
        }

        const formElements = document.querySelectorAll('#delivery-form input, #delivery-form select, #delivery-form textarea');
        formElements.forEach(element => {
            element.addEventListener('input', updateRequestBody);
            element.addEventListener('change', updateRequestBody);
        });
    });
</script>
</body>
</html>
