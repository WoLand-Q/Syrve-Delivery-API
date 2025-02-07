// scripts.js

document.addEventListener('DOMContentLoaded', () => {
    // Фильтрация по тексту и типу
    const filterInput = document.getElementById('filter-input');
    const filterSelect = document.getElementById('filter-select');

    filterInput.addEventListener('input', filterItems);
    filterSelect.addEventListener('change', filterItems);

    function filterItems() {
        const filterValue = filterInput.value.toLowerCase();
        const filterType = filterSelect.value;
        const items = document.querySelectorAll('.group, .product, .size');

        items.forEach(item => {
            const matchesText = item.textContent.toLowerCase().includes(filterValue);
            let matchesType = false;

            if (filterType === 'all') {
                matchesType = true;
            } else if (filterType === 'group' && item.classList.contains('group')) {
                matchesType = true;
            } else if (filterType === 'product' && item.classList.contains('product')) {
                matchesType = true;
            } else if (filterType === 'size' && item.classList.contains('size')) {
                matchesType = true;
            }

            if (matchesText && matchesType) {
                item.style.display = 'block';
                item.classList.add('animate__animated', 'animate__fadeIn');
                setTimeout(() => {
                    item.classList.remove('animate__animated', 'animate__fadeIn');
                }, 1000);
            } else {
                item.style.display = 'none';
            }
        });
    }

    // Кнопка для показа/скрытия отладочной информации
    const toggleDebugBtn = document.getElementById('toggle-debug-btn');
    const debugInfo = document.getElementById('debug-info');

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

    // Кнопки для показа/скрытия модификаторов
    const toggleModifierBtns = document.querySelectorAll('.toggle-modifier-btn');
    toggleModifierBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const productId = btn.getAttribute('data-product-id');
            const modifiersDetails = document.getElementById(`modifiers-${productId}`);

            if (modifiersDetails.style.display === 'none') {
                modifiersDetails.style.display = 'block';
                btn.textContent = 'Скрыть модификаторы';
                modifiersDetails.classList.add('animate__animated', 'animate__fadeIn');
            } else {
                modifiersDetails.style.display = 'none';
                btn.textContent = 'Показать модификаторы';
            }
        });
    });

    // Кнопки для показа/скрытия дочерних модификаторов
    const toggleChildModifierBtns = document.querySelectorAll('.toggle-child-modifier-btn');
    toggleChildModifierBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const groupModifierId = btn.getAttribute('data-group-modifier-id');
            const childModifiersDetails = document.getElementById(`child-modifiers-${groupModifierId}`);

            if (childModifiersDetails.style.display === 'none') {
                childModifiersDetails.style.display = 'block';
                btn.textContent = 'Скрыть дочерние модификаторы';
                childModifiersDetails.classList.add('animate__animated', 'animate__fadeIn');
            } else {
                childModifiersDetails.style.display = 'none';
                btn.textContent = 'Показать дочерние модификаторы';
            }
        });
    });
});
