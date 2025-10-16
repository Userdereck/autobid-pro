// public/js/catalog.js
(function() {
    function showToast(message, type = 'success') {
        let toast = document.getElementById('autobid-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'autobid-toast';
            toast.className = 'autobid-toast';
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.className = `autobid-toast ${type} show`;
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function formatCurrency(amount, currency = 'USD') {
        if (!amount || isNaN(amount)) amount = 0;
        return new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 0
        }).format(amount);
    }

    let allVehicles = [];
    let filteredVehicles = [];

    document.addEventListener('DOMContentLoaded', () => {
        const mainCatalog = document.querySelector('.autobid-catalog[data-type]');
        if (mainCatalog) {
            initializeCatalog(mainCatalog);
        }
    });

    async function initializeCatalog(catalogElement) {
        const type = catalogElement.dataset.type || '';
        try {
            const vehicles = await loadVehicles(type);
            allVehicles = vehicles;
            filteredVehicles = [...vehicles];
            renderVehicles(catalogElement, filteredVehicles);
            updateResultsCount(catalogElement, filteredVehicles.length);
            setupEventListeners(catalogElement, type);
            populateFilters(vehicles);
        } catch (error) {
            console.error('Error al cargar vehículos:', error);
            const grid = catalogElement.querySelector('.vehicle-grid');
            if (grid) grid.innerHTML = '<p class="error">❌ No se pudieron cargar los vehículos.</p>';
        }
    }

    async function loadVehicles(type) {
        let url = autobid_vars.api_url;
        if (type) url += `?type=${encodeURIComponent(type)}`;
        const response = await fetch(url, {
            headers: { 'X-WP-Nonce': autobid_vars.nonce }
        });
        if (!response.ok) throw new Error('Error al cargar');
        return await response.json();
    }

    function populateFilters(vehicles) {
        const brands = new Set();
        const years = new Set();
        vehicles.forEach(v => {
            if (v.brand) brands.add(v.brand);
            if (v.year && v.year !== 'N/A') years.add(v.year.toString());
        });

        const brandSelect = document.getElementById('filter-brand');
        const yearSelect = document.getElementById('filter-year');

        if (brandSelect && brandSelect.childElementCount <= 1) {
            [...brands].sort().forEach(brand => {
                const opt = document.createElement('option');
                opt.value = brand;
                opt.textContent = brand;
                brandSelect.appendChild(opt);
            });
        }
        if (yearSelect && yearSelect.childElementCount <= 1) {
            [...years].sort((a, b) => b - a).forEach(year => {
                const opt = document.createElement('option');
                opt.value = year;
                opt.textContent = year;
                yearSelect.appendChild(opt);
            });
        }
    }

    function renderVehicles(catalogElement, vehicles) {
        const grid = catalogElement.querySelector('.vehicle-grid');
        if (!grid) return;

        if (vehicles.length === 0) {
            grid.innerHTML = '<p class="no-results">No se encontraron vehículos.</p>';
            return;
        }

        const html = vehicles.map(v => {
            const isAuction = v.type === 'subasta';
            const badgeText = isAuction ?
                (v.auction_status === 'upcoming' ? 'PRÓXIMAMENTE' :
                 v.auction_status === 'live' ? 'EN CURSO' : 'FINALIZADA') :
                (autobid_vars.label_sale || 'Venta');
            const badgeClass = `vehicle-type-badge" data-status="${isAuction ? v.auction_status : 'sale'}`;

            let actionButtonHtml = '';
            if (isAuction) {
                if (v.auction_status === 'live') {
                    if (autobid_vars.current_user_id) {
                        actionButtonHtml = `<button class="btn-view-detail" data-id="${v.id}">Pujar ahora</button>`;
                    } else {
                        // --- Botón de login para pujar ---
                        actionButtonHtml = `<a href="${autobid_auth_vars.login_url || '/login/'}" class="btn-view-detail btn-login-required">Iniciar sesión para pujar</a>`;
                        // --- Fin Botón de login para pujar ---
                    }
                } else {
                    // Para subastas próximas o finalizadas
                    actionButtonHtml = `<button class="btn-view-detail" data-id="${v.id}" ${v.auction_status === 'closed' ? 'disabled' : ''}>Ver detalles</button>`;
                }
            } else {
                // Para ventas directas
                actionButtonHtml = `<button class="btn-view-detail" data-id="${v.id}">Ver detalles</button>`;
            }

            return `
                <div class="vehicle-card" data-id="${v.id}">
                    <div class="vehicle-image">
                        <img src="${v.image}" alt="${v.name}" loading="lazy">
                        <span class="${badgeClass}">${badgeText}</span>
                    </div>
                    <div class="vehicle-info">
                        <h3>${v.name}</h3>
                        <div class="specs">
                            <span>${v.brand || '—'}</span>
                            <span>${v.model || '—'}</span>
                            <span>${v.year || '—'}</span>
                            <span>${v.color || '—'}</span>
                        </div>
                        <div class="price">${formatCurrency(v.price, v.currency)}</div>
                        ${actionButtonHtml}
                    </div>
                </div>
            `;
        }).join('');

        grid.innerHTML = html;

        grid.querySelectorAll('.btn-view-detail:not(.btn-login-required)').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.dataset.id;
                window.location.href = `${autobid_vars.detail_page_url}?id=${id}`;
            });
        });
    }


    function updateResultsCount(catalogElement, count) {
        const el = catalogElement.querySelector('#results-count');
        if (el) el.textContent = `${count} vehículos disponibles`;
    }

    function setupEventListeners(catalogElement, type) {
        const applyBtn = catalogElement.querySelector('#apply-filters');
        const resetBtn = catalogElement.querySelector('#reset-filters');
        const sortSelect = catalogElement.querySelector('#sort-by');
        const searchInput = catalogElement.querySelector('#global-search');

        if (applyBtn) applyBtn.addEventListener('click', () => applyFilters(catalogElement, type));
        if (resetBtn) resetBtn.addEventListener('click', () => resetFilters(catalogElement, type));
        if (sortSelect) sortSelect.addEventListener('change', () => applyFilters(catalogElement, type));
        if (searchInput) {
            let t;
            searchInput.addEventListener('input', () => {
                clearTimeout(t);
                t = setTimeout(() => applyFilters(catalogElement, type), 300);
            });
        }
    }

    function applyFilters(catalogElement, type) {
        const searchTerm = catalogElement.querySelector('#global-search')?.value.toLowerCase() || '';
        const brandFilter = catalogElement.querySelector('#filter-brand')?.value || '';
        const modelFilter = catalogElement.querySelector('#filter-model')?.value.toLowerCase() || '';
        const yearFilter = catalogElement.querySelector('#filter-year')?.value || '';
        const colorFilter = catalogElement.querySelector('#filter-color')?.value.toLowerCase() || '';
        const conditionFilter = catalogElement.querySelector('#filter-condition')?.value || '';
        const minPrice = parseFloat(catalogElement.querySelector('#filter-price-min')?.value) || 0;
        const maxPrice = parseFloat(catalogElement.querySelector('#filter-price-max')?.value) || Infinity;
        const typeFilter = catalogElement.querySelector('#filter-type')?.value || type;

        filteredVehicles = allVehicles.filter(v => {
            const matchesSearch = !searchTerm ||
                v.name.toLowerCase().includes(searchTerm) ||
                (v.brand && v.brand.toLowerCase().includes(searchTerm)) ||
                (v.model && v.model.toLowerCase().includes(searchTerm)) ||
                (v.location && v.location.toLowerCase().includes(searchTerm)) ||
                (v.color && v.color.toLowerCase().includes(searchTerm));

            const matchesBrand = !brandFilter || (v.brand && v.brand.toLowerCase().includes(brandFilter.toLowerCase()));
            const matchesModel = !modelFilter || (v.model && v.model.toLowerCase().includes(modelFilter));
            const matchesYear = !yearFilter || (v.year && v.year.toString() === yearFilter);
            const matchesColor = !colorFilter || (v.color && v.color.toLowerCase().includes(colorFilter));
            const matchesCondition = !conditionFilter || v.condition === conditionFilter;
            const matchesPrice = (v.price || 0) >= minPrice && (v.price || 0) <= maxPrice;
            const matchesType = !typeFilter || v.type === typeFilter;

            return matchesSearch && matchesBrand && matchesModel && matchesYear && matchesColor && matchesCondition && matchesPrice && matchesType;
        });

        sortVehicles();
        renderVehicles(catalogElement, filteredVehicles);
        updateResultsCount(catalogElement, filteredVehicles.length);
    }

    function resetFilters(catalogElement, type) {
        const inputs = [
            '#global-search',
            '#filter-model',
            '#filter-color',
            '#filter-price-min',
            '#filter-price-max'
        ];
        inputs.forEach(sel => {
            const el = catalogElement.querySelector(sel);
            if (el) el.value = '';
        });

        ['#filter-brand', '#filter-year', '#filter-condition', '#filter-type'].forEach(sel => {
            const el = catalogElement.querySelector(sel);
            if (el) el.value = '';
        });

        applyFilters(catalogElement, type);
    }

    function sortVehicles() {
        const sortBy = document.querySelector('#sort-by')?.value || 'newest';
        filteredVehicles.sort((a, b) => {
            if (sortBy === 'newest') return b.id - a.id;
            if (sortBy === 'price-asc') return (a.price || 0) - (b.price || 0);
            if (sortBy === 'price-desc') return (b.price || 0) - (a.price || 0);
            return 0;
        });
    }
})();