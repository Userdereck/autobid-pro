// public/js/detail.js

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

    const API_BASE = autobid_vars.api_url;
    const urlParams = new URLSearchParams(window.location.search);
    const vehicleId = urlParams.get('id');

    // --- Validar ID ---
    if (!vehicleId || isNaN(vehicleId)) {
        document.getElementById('vehicle-detail').innerHTML = `
            <div class="detail-container">
                <p class="error">❌ ID de vehículo inválido.</p>
                <a href="${autobid_vars.sales_page_url}" class="back-link">← Volver al catálogo</a>
            </div>
        `;
        console.error('AutoBid Pro: ID de vehículo inválido en la URL.');
        return; // Salir si no hay ID válido
    }
    // --- Fin Validar ID ---

    // --- Cargar Detalle ---
    async function loadVehicleDetail(id) {
        try {
            const response = await fetch(`${API_BASE}/${id}`, {
                headers: { 'X-WP-Nonce': autobid_vars.nonce }
            });
            if (!response.ok) {
                const errData = await response.json().catch(() => ({}));
                throw new Error(errData.message || `Error ${response.status} al cargar el vehículo.`);
            }

            const vehicle = await response.json();
            renderVehicleDetail(vehicle);

            // Iniciar temporizador si es una subasta en vivo
            if (vehicle.type === 'subasta' && vehicle.auction_status === 'live' && vehicle.end_time) {
                startAuctionTimer(vehicle.end_time);
            }

        } catch (error) {
            console.error('AutoBid Pro: Error al cargar el vehículo:', error);
            document.getElementById('vehicle-detail').innerHTML = `
                <div class="detail-container">
                    <p class="error">❌ ${error.message}</p>
                    <a href="${autobid_vars.sales_page_url}" class="back-link">← Volver al catálogo</a>
                </div>
            `;
        }
    }
    // --- Fin Cargar Detalle ---

    // --- Renderizar Detalle CORREGIDO ---
    function renderVehicleDetail(v) {
        // Manejar galería
        const gallery = v.gallery && Array.isArray(v.gallery) && v.gallery.length > 0 ? v.gallery : [v.image];
        const mainImage = gallery[0];
        const thumbnails = gallery.map((src, i) =>
            `<img src="${src}" class="thumbnail ${i === 0 ? 'active' : ''}" data-index="${i}" alt="Imagen ${i + 1} de ${v.name}">`
        ).join('');

        const isAuction = v.type === 'subasta';

        // Formatear precio o puja actual
        const priceDisplay = isAuction
            ? `${formatCurrency(v.current_bid, v.currency)} <small>(puja actual)</small>`
            : `${formatCurrency(v.price, v.currency)} <small>(precio fijo)</small>`;

        // Badge de estado para subastas
        let statusBadge = '';
        if (isAuction) {
            let statusText = 'Desconocido';
            let statusClass = '';
            switch (v.auction_status) {
                case 'upcoming':
                    statusText = 'PRÓXIMAMENTE';
                    statusClass = 'upcoming';
                    break;
                case 'live':
                    statusText = 'EN CURSO';
                    statusClass = 'live';
                    break;
                case 'closed':
                    statusText = 'FINALIZADA';
                    statusClass = 'closed';
                    break;
                default:
                    statusText = v.auction_status.toUpperCase(); // Para otros estados
            }
            statusBadge = `<span class="vehicle-status-badge ${statusClass}">${statusText}</span>`;
        }

        // URLs de retorno
        const backUrl = isAuction ? autobid_vars.auctions_page_url : autobid_vars.sales_page_url;

        // Texto del botón de acción
        // Texto del botón de acción
        let actionButtonText = 'Ver detalles';
        if (isAuction) {
            if (v.auction_status === 'live') {
                actionButtonText = autobid_texts?.bid_button || 'Pujar ahora';
            } else if (v.auction_status === 'upcoming') {
                actionButtonText = 'Notificarme al iniciar';
            } else if (v.auction_status === 'closed') {
                actionButtonText = 'Subasta finalizada';
            }
        } else {
            // Para ventas directas
            actionButtonText = autobid_texts?.buy_button || 'Comprar ahora';
        }

        // Renderizar HTML
        document.getElementById('vehicle-detail').innerHTML = `
            <div class="detail-container">
                <div class="gallery-section">
                    <div class="main-image-container">
                        <img src="${mainImage}" class="main-image" id="main-image" alt="${v.name}">
                    </div>
                    <div class="thumbnails-container">
                        ${thumbnails}
                    </div>
                </div>
                <div class="vehicle-info-section">
                    <a href="${backUrl}" class="back-link">← Volver al catálogo</a>
                    <h1 class="vehicle-title">${v.name}</h1>
                    ${statusBadge}
                    <div class="price-section">
                        <div class="current-price">${priceDisplay}</div>
                        ${isAuction && v.auction_status === 'live' ? '<div class="auction-timer" id="auction-timer">⏳ Cargando tiempo...</div>' : ''}
                    </div>
                    <div class="specs-grid">
                        <div class="spec-item">
                            <span class="spec-label">Marca</span>
                            <span class="spec-value">${v.brand || 'N/A'}</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Modelo</span>
                            <span class="spec-value">${v.model || 'N/A'}</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Año</span>
                            <span class="spec-value">${v.year || 'N/A'}</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Color</span>
                            <span class="spec-value">${v.color || 'N/A'}</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Condición</span>
                            <span class="spec-value">${v.condition || 'N/A'}</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Ubicación</span>
                            <span class="spec-value">${v.location || 'N/A'}</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Tipo</span>
                            <span class="spec-value">${isAuction ? 'Subasta' : 'Venta directa'}</span>
                        </div>
                        ${isAuction ? `
                        <div class="spec-item">
                            <span class="spec-label">Inicio</span>
                            <span class="spec-value">${v.start_time ? new Date(v.start_time).toLocaleString() : 'Inmediato'}</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Fin</span>
                            <span class="spec-value">${v.end_time ? new Date(v.end_time).toLocaleString() : 'N/A'}</span>
                        </div>
                        ` : ''}
                        <div class="spec-item">
                            <span class="spec-label">Moneda</span>
                            <span class="spec-value">${v.currency || 'USD'}</span>
                        </div>
                    </div>
                    <div class="description-section">
                        <h3>Descripción</h3>
                        <p>${v.description || 'No hay descripción disponible.'}</p>
                    </div>
                    <button class="action-button" id="action-button" ${isAuction && v.auction_status === 'closed' ? 'disabled' : ''}>
                        ${actionButtonText}
                    </button>
                </div>
            </div>
        `;

        // --- Eventos para galería ---
        document.querySelectorAll('.thumbnail').forEach(thumb => {
            thumb.addEventListener('click', () => {
                const idx = parseInt(thumb.dataset.index, 10);
                if (!isNaN(idx) && idx < gallery.length) {
                    document.getElementById('main-image').src = gallery[idx];
                    document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
                    thumb.classList.add('active');
                }
            });
        });

        // --- Evento para botón de acción (Pujar/Comprar) CORREGIDO ---
        const btn = document.getElementById('action-button');        
        if (btn) {
            if (isAuction && v.auction_status === 'live') {
                // Lógica para pujar
                btn.addEventListener('click', async () => {
                    if (!autobid_vars.current_user_id) {
                        showToast('Debes iniciar sesión para pujar.', 'error');
                        return;
                    }
                    const bid = prompt(`Ingresa tu puja (${v.currency}):`);
                    if (!bid || isNaN(bid)) return;
                    const bidAmount = parseFloat(bid);
                    if (bidAmount <= 0) {
                        showToast('La puja debe ser un número positivo.', 'error');
                        return;
                    }
                    try {
                        const res = await fetch(`${API_BASE}/${v.id}/bid`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': autobid_vars.nonce
                            },
                            body: JSON.stringify({ bid_amount: bidAmount })
                        });
                        const data = await res.json();
                        if (res.ok) {
                            showToast(data.message || 'Puja registrada exitosamente.', 'success');
                            loadVehicleDetail(v.id);
                        } else {
                            showToast(data.message || 'Error al pujar.', 'error');
                        }
                    } catch (e) {
                        console.error('Error al pujar:', e);
                        showToast('Error de conexión.', 'error');
                    }
                });
            } else if (isAuction && v.auction_status === 'upcoming') {
                // --- NUEVO: Lógica para "Notificarme al iniciar" ---
                btn.addEventListener('click', async () => {
                    if (!autobid_vars.current_user_id) {
                        showToast('Debes iniciar sesión para recibir notificaciones.', 'error');
                        return;
                    }
                    try {
                        const res = await fetch(`${API_BASE}/${v.id}/watch`, {
                            method: 'POST',
                            headers: {
                                'X-WP-Nonce': autobid_vars.nonce
                            }
                        });
                        const data = await res.json();
                        if (res.ok) {
                            showToast(data.message || '¡Te notificaremos al iniciar!', 'success');
                            // Opcional: deshabilitar botón
                            btn.disabled = true;
                            btn.textContent = 'Notificación solicitada';
                        } else {
                            showToast(data.message || 'Error al solicitar notificación.', 'error');
                        }
                    } catch (e) {
                        console.error('Error al solicitar notificación:', e);
                        showToast('Error de conexión.', 'error');
                    }
                });
                // --- FIN NUEVO ---
            } else if (!isAuction) {
                // Lógica para "Comprar ahora"
                btn.addEventListener('click', async () => {
                    if (!autobid_vars.current_user_id) {
                        showToast('Debes iniciar sesión para comprar.', 'error');
                        return;
                    }
                    if (!confirm(`¿Estás seguro de que deseas solicitar la compra de "${v.name}" por ${formatCurrency(v.price, v.currency)}?`)) {
                        return;
                    }
                    try {
                        const res = await fetch(`${API_BASE}/${v.id}/purchase`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': autobid_vars.nonce
                            },
                            body: JSON.stringify({ action: 'purchase' })
                        });
                        const result = await res.json();
                        if (res.ok) {
                            showToast(result.message || 'Solicitud de compra enviada.', 'success');
                            if (result.whatsapp_url) {
                                window.open(result.whatsapp_url, '_blank', 'noopener,noreferrer');
                            }
                        } else {
                            showToast(result.message || 'Error al procesar la compra.', 'error');
                        }
                    } catch (error) {
                        console.error('Error al comprar:', error);
                        showToast('Error de conexión.', 'error');
                    }
                });
            }
        }
        // --- Fin Evento para botón de acción ---
    }
    // --- Fin Renderizar Detalle CORREGIDO ---

    // --- Temporizador de Subasta ---
    function startAuctionTimer(endTimeStr) {
        const endTime = new Date(endTimeStr);
        const timerEl = document.getElementById('auction-timer');
        if (!timerEl) return;

        const update = () => {
            const now = new Date();
            const diff = endTime - now;
            if (diff <= 0) {
                timerEl.innerHTML = '<span class="ended">⏰ Subasta finalizada</span>';
                // Opcional: Recargar la página o actualizar estado visual
                // location.reload();
                return;
            }
            const d = Math.floor(diff / 86400000);
            const h = Math.floor((diff % 86400000) / 3600000);
            const m = Math.floor((diff % 3600000) / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            timerEl.innerHTML = `⏳ Termina en: <strong>${d}d ${h}h ${m}m ${s}s</strong>`;
        };
        update();
        setInterval(update, 1000); // Actualizar cada segundo
    }
    // --- Fin Temporizador ---

    // --- Iniciar carga ---
    loadVehicleDetail(vehicleId);

})();