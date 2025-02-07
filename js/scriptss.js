document.addEventListener('DOMContentLoaded', () => {
    // Инициализация Toastr с пользовательскими настройками
    toastr.options = {
        "closeButton": true,
        "debug": false,
        "newestOnTop": false,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "preventDuplicates": false,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    };

    // Кнопка для показа/скрытия отладочной информации
    const toggleDebugBtn = document.getElementById('toggle-debug-btn');
    const debugInfo = document.getElementById('debug-info');

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

    // Добавление товаров в корзину
    const addToCartBtns = document.querySelectorAll('.add-to-cart-btn');
    const cartItemsContainer = document.getElementById('cart-items');
    const cartTotal = document.getElementById('cart-total');
    let cart = [];

    addToCartBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const productId = btn.getAttribute('data-product-id');
            const productName = btn.getAttribute('data-product-name');
            const priceMin = parseFloat(btn.getAttribute('data-price-min'));
            const priceMax = parseFloat(btn.getAttribute('data-price-max'));

            // Проверка, что цены существуют
            if (priceMin === 0 && priceMax === 0) {
                toastr.error('Цена товара не установлена.');
                return;
            }

            // Генерация случайной цены в заданном диапазоне
            const randomPrice = Math.floor(Math.random() * (priceMax - priceMin + 1)) + priceMin;

            // Проверяем, есть ли уже этот продукт в корзине
            const existingItem = cart.find(item => item.id === productId);
            if (existingItem) {
                existingItem.amount += 1;
            } else {
                cart.push({ id: productId, name: productName, price: randomPrice, amount: 1 });
            }

            console.log('Добавлено в корзину:', { id: productId, name: productName, price: randomPrice, amount: 1 });
            console.log('Текущая корзина:', cart);
            updateCart();
        });
    });

    // Удаление товаров из корзины
    function removeFromCart(productId) {
        cart = cart.filter(item => item.id !== productId);
        console.log('Удалено из корзины:', productId);
        console.log('Текущая корзина:', cart);
        updateCart();
    }

    // Обновление отображения корзины
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
                    <span>${item.price * item.amount} грн</span>
                    <button class="btn btn-sm btn-danger remove-item-btn" data-product-id="${item.id}">Удалить</button>
                `;
                cartItemsContainer.appendChild(itemDiv);
                total += item.price * item.amount;
            });

            // Добавляем обработчики для кнопок удаления
            const removeBtns = document.querySelectorAll('.remove-item-btn');
            removeBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const productId = btn.getAttribute('data-product-id');
                    removeFromCart(productId);
                });
            });
        }

        cartTotal.textContent = total;
        console.log('Обновленная корзина:', cart);
    }

    // Обработка выбора города и загрузки улиц
    const citySelect = document.getElementById('city');
    const streetSelect = document.getElementById('street');

    if (citySelect && streetSelect) {
        citySelect.addEventListener('change', () => {
            const cityId = citySelect.value;
            const organizationId = cityId ? "<?php echo htmlspecialchars($streetsOrganizationId); ?>" : '';

            if (cityId) {
                // Очистить и отключить улицы перед загрузкой
                streetSelect.innerHTML = '<option value="">Загрузка улиц...</option>';
                streetSelect.disabled = true;

                // Сделать AJAX-запрос для получения улиц
                fetch(`get_streets.php?organizationId=${encodeURIComponent(organizationId)}&cityId=${encodeURIComponent(cityId)}`)
                    .then(response => response.json())
                    .then(data => {
                        console.log('Полученные улицы:', data);
                        streetSelect.innerHTML = '<option value="">-- Выберите улицу --</option>';
                        if (data.streets && data.streets.length > 0) {
                            data.streets.forEach(street => {
                                const option = document.createElement('option');
                                option.value = street.id;
                                option.textContent = street.name;
                                streetSelect.appendChild(option);
                            });
                            streetSelect.disabled = false;
                        } else {
                            streetSelect.innerHTML = '<option value="">Улицы не найдены</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка при загрузке улиц:', error);
                        streetSelect.innerHTML = '<option value="">Ошибка загрузки улиц</option>';
                    });
            } else {
                // Если город не выбран, очистить и отключить улицы
                streetSelect.innerHTML = '<option value="">-- Выберите улицу --</option>';
                streetSelect.disabled = true;
            }
        });
    }

    // Обработка формы доставки
    const deliveryForm = document.getElementById('delivery-form');
    const loader = document.getElementById('loader');
    const requestJson = document.getElementById('request-json');
    const requestBody = document.getElementById('request-body');

    if (deliveryForm && loader) {
        deliveryForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Валидация формы
            const phoneInput = document.getElementById('phone');
            const phoneError = document.getElementById('phone-error');
            const phoneValue = phoneInput.value.trim();

            // Простая валидация телефона
            const phoneRegex = /^\+\d{8,40}$/;
            if (!phoneRegex.test(phoneValue)) {
                phoneError.textContent = 'Введите корректный телефон в формате +XXXXXXXXXX';
                return;
            } else {
                phoneError.textContent = '';
            }

            // Проверка, что корзина не пуста
            if (cart.length === 0) {
                toastr.warning('Корзина пуста. Пожалуйста, добавьте хотя бы один товар.');
                return;
            }

            // Получение выбранного терминала
            const terminalSelect = document.getElementById('terminal-select');
            const terminalId = terminalSelect.value;
            if (!terminalId) {
                toastr.warning('Пожалуйста, выберите терминал.');
                return;
            }

            // Получение выбранного типа оплаты
            const paymentTypeSelect = document.getElementById('payment-type');
            const paymentTypeId = paymentTypeSelect.value;
            const selectedPaymentOption = paymentTypeSelect.options[paymentTypeSelect.selectedIndex];
            const paymentTypeKind = selectedPaymentOption.getAttribute('data-kind'); // Получаем kind

            if (!paymentTypeId) {
                toastr.warning('Пожалуйста, выберите тип оплаты.');
                return;
            }

            // Получение выбранного города
            const citySelect = document.getElementById('city');
            const cityId = citySelect.value;
            if (!cityId) {
                toastr.warning('Пожалуйста, выберите город.');
                return;
            }

            // Получение выбранной улицы
            const streetSelect = document.getElementById('street');
            const streetId = streetSelect.value;
            if (!streetId) {
                toastr.warning('Пожалуйста, выберите улицу.');
                return;
            }

            // Сбор данных адреса
            const house = document.getElementById('house').value.trim();
            const flat = document.getElementById('flat').value.trim();
            const entrance = document.getElementById('entrance').value.trim();
            const floor = document.getElementById('floor').value.trim();

            // Дополнительная валидация адреса
            if (!house) {
                toastr.warning('Пожалуйста, введите номер дома.');
                return;
            }

            // Сбор данных заказа
            const completeBefore = document.getElementById('complete-before').value;
            const comment = document.getElementById('comment').value.trim();

            // Сбор элементов заказа
            const items = cart.map(item => ({
                type: "Product",
                productId: item.id,
                amount: item.amount,
                price: item.price,
                comment: "Без соли" // Здесь можно добавить динамическое заполнение комментария
            }));

            // Генерация уникального externalNumber
            const externalNumber = 'ORDER_' + Date.now();

            // Сбор данных формы
            const deliveryData = {
                "organizationId": "<?php echo htmlspecialchars($organizationId); ?>",
                "terminalGroupId": terminalId,
                "createOrderSettings": {
                    "transportToFrontTimeout": 0,
                    "checkStopList": false
                },
                "order": {
                    "externalNumber": externalNumber, // Уникальный номер заказа
                    "phone": phoneValue,
                    "orderTypeId": "76067ea3-356f-eb93-9d14-1fa00d082c4e", // Замените на актуальный ID
                    "deliveryPoint": {
                        "coordinates": {
                            "latitude": 50.427534, // Можно интегрировать геокодирование для автоматического получения
                            "longitude": 30.424037
                        },
                        "address": {
                            "type": "legacy",
                            "cityId": cityId, // Используем выбранный ID города
                            "street": {
                                "id": streetId // Используем выбранный ID улицы
                            },
                            "house": house,
                            "flat": flat,
                            "entrance": entrance,
                            "floor": floor
                        },
                        "comment": "Оставить у двери"
                    },
                    "comment": comment,
                    "customer": {
                        "type": "regular",
                        "name": "Эрнест"
                    },
                    "guests": {
                        "count": 1,
                        "splitBetweenPersons": true,
                        "details": {
                            "guestName": "Эрнест",
                            "guestPhone": phoneValue,
                            "specialRequests": "Без лука"
                        }
                    },
                    "items": items,
                    "payments": [
                        {
                            "sum": parseFloat(cartTotal.textContent),
                            "paymentTypeId": paymentTypeId,
                            "paymentTypeKind": paymentTypeKind, // Используем выбранный тип оплаты
                            "isProcessedExternally": false,
                            "isFiscalizedExternally": false,
                            "isPrepay": false
                        }
                        // Можно добавить дополнительные типы оплат
                    ],
                    "loyaltyInfo": {
                        "coupon": "" // Заполните, если используется
                    },
                    "sourceKey": "Site"
                }
            };

            // Отображение сформированного запроса
            requestJson.textContent = JSON.stringify(deliveryData, null, 4);
            requestBody.style.display = 'block';

            console.log('Отправляемые данные:', deliveryData);

            // Показать лоадер
            loader.style.display = 'flex';

            try {
                // Отправка данных на API через create_delivery.php
                const response = await fetch('create_delivery.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                        // Токен передаётся через сессию, не нужно добавлять здесь
                    },
                    body: JSON.stringify(deliveryData)
                });

                // Скрыть лоадер
                loader.style.display = 'none';

                // Обработка ответа
                const result = await response.json();

                console.log('Ответ от create_delivery.php:', result);

                if (response.ok) {
                    toastr.success('Доставка успешно создана!');
                    // Очистить корзину и форму
                    cart = [];
                    updateCart();
                    deliveryForm.reset();

                    // Отключить улицы, так как город сброшен
                    streetSelect.innerHTML = '<option value="">-- Выберите улицу --</option>';
                    streetSelect.disabled = true;

                    // Скрыть отображение запроса
                    requestBody.style.display = 'none';
                } else {
                    toastr.error('Ошибка при создании доставки: ' + (result.error || 'Неизвестная ошибка'));
                }
            } catch (error) {
                loader.style.display = 'none';
                toastr.error('Произошла ошибка при отправке запроса.');
                console.error('Ошибка:', error);
            }
        });
    }

    // Фильтрация продуктов по поиску
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
        });
    }
});
