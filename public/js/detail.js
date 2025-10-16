// detail.js - Versión final con multi-moneda y estados
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

    if (!vehicleId || isNaN(vehicleId)) {
        document.getElementById('vehicle-detail').innerHTML = '<p class="error">ID de vehículo inválido.</p>';
    } else {
        loadVehicleDetail(vehicleId);
    }

    async function loadVehicleDetail(id) {
        try {
            const response = await fetch(`${API_BASE}/${id}`, {
                headers: { 'X-WP-Nonce': autobid_vars.nonce }
            });
            
            if (!response.ok) {
                const err = await response.json().catch(() => ({}));
                throw new Error(err.message || 'Vehículo no encontrado');
            }
            
            const vehicle = await response.json();
            renderVehicleDetail(vehicle);
            
            if (vehicle.type === 'subasta' && vehicle.end_time) {
                startAuctionTimer(vehicle.end_time);
            }
        } catch (error) {
            console.error('Error al cargar el vehículo:', error);
            document.getElementById('vehicle-detail').innerHTML = `
                <div class="detail-container">
                    <p class="error">❌ ${error.message}</p>
                    <a href="${autobid_vars.sales_page_url}" class="back-link">← Volver al catálogo</a>
                </div>
            `;
        }
    }

    function renderVehicleDetail(v) {
        const gallery = v.gallery && v.gallery.length > 0 ? v.gallery : [v.image];
        const mainImage = gallery[0];
        const thumbnails = gallery.map((src, i) => 
            `<img src="${src}" class="thumbnail ${i === 0 ? 'active' : ''}" data-index="${i}">`
        ).join('');

        const isAuction = v.type === 'subasta';
        const priceDisplay = isAuction 
            ? `${formatCurrency(v.current_bid, v.currency)} <small>(puja actual)</small>`
            : `${formatCurrency(v.price, v.currency)} <small>(precio fijo)</small>`;

        let statusBadge = '';
        if (isAuction) {
            const statusText = v.auction_status === 'upcoming' ? 'PRÓXIMAMENTE' :
                              v.auction_status === 'live' ? 'EN CURSO' : 'FINALIZADA';
            statusBadge = `<span class="vehicle-status-badge ${v.auction_status}">${statusText}</span>`;
        }

        const backUrl = isAuction ? autobid_vars.auctions_page_url : autobid_vars.sales_page_url;

        document.getElementById('vehicle-detail').innerHTML = `
            <div class="detail-container">
                <div class="gallery-section">
                    <div class="main-image-container">
                        <img src="${mainImage}" class="main-image" id="main-image">
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
                            <span class="spec-value">${v.brand}</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Año</span>
                            <span class="spec-value">${v.year}</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Ubicación</span>
                            <span class="spec-value">${v.location}</span>
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
                        <div class="spec-item">
                            <span class="spec-label">Moneda</span>
                            <span class="spec-value">${v.currency}</span>
                        </div>
                        ` : `
                        <div class="spec-item">
                            <span class="spec-label">Moneda</span>
                            <span class="spec-value">${v.currency}</span>
                        </div>
                        `}
                    </div>

                    <div class="description-section">
                        <h3>Descripción</h3>
                        <p>${v.description || 'Sin descripción.'}</p>
                    </div>

                    <button class="action-button" id="action-button">
                        ${isAuction ? 
                            (v.auction_status === 'live' ? 'Pujar ahora' : 
                             v.auction_status === 'upcoming' ? 'Notificarme al iniciar' : 'Subasta finalizada') 
                            : 'Comprar ahora'}
                    </button>
                </div>
            </div>
        `;

        document.querySelectorAll('.thumbnail').forEach(thumb => {
            thumb.addEventListener('click', () => {
                const idx = thumb.dataset.index;
                document.getElementById('main-image').src = gallery[idx];
                document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
                thumb.classList.add('active');
            });
        });

        const btn = document.getElementById('action-button');
        if (btn && isAuction && v.auction_status === 'live') {
            btn.addEventListener('click', async () => {
                if (!autobid_vars.current_user_id) {
                    showToast('Debes iniciar sesión para pujar.', 'error');
                    return;
                }
                const bid = prompt(`Ingresa tu puja (${v.currency}):`);
                if (!bid || isNaN(bid)) return;
                
                try {
                    const res = await fetch(`${API_BASE}/${v.id}/bid`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': autobid_vars.nonce
                        },
                        body: JSON.stringify({ bid_amount: parseFloat(bid) })
                    });
                    const data = await res.json();
                    if (res.ok) {
                        showToast(data.message, 'success');
                        loadVehicleDetail(v.id);
                    } else {
                        showToast(data.message || 'Error.', 'error');
                    }
                } catch (e) {
                    showToast('Error de conexión.', 'error');
                }
            });
        }
    }

    function startAuctionTimer(endTimeStr) {
        const endTime = new Date(endTimeStr);
        const timerEl = document.getElementById('auction-timer');
        if (!timerEl) return;

        const update = () => {
            const now = new Date();
            const diff = endTime - now;
            if (diff <= 0) {
                timerEl.innerHTML = '<span class="ended">⏰ Subasta finalizada</span>';
                return;
            }
            const d = Math.floor(diff / 86400000);
            const h = Math.floor((diff % 86400000) / 3600000);
            const m = Math.floor((diff % 3600000) / 60000);
            timerEl.innerHTML = `⏳ Termina en: <strong>${d}d ${h}h ${m}m</strong>`;
        };

        update();
        setInterval(update, 60000);
    }

    const style = document.createElement('style');
    style.textContent = `
        .vehicle-status-badge {
            display: inline-block;
            padding: 0.3rem 0.7rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 1rem;
            color: white;
        }
        .upcoming { background: #3498db; }
        .live { background: var(--accent, #e74c3c); }
        .closed { background: #95a5a6; }
        .ended { color: var(--accent, #e74c3c); font-weight: bold; }
        .back-link {
            display: inline-block;
            margin-bottom: 1.2rem;
            color: var(--primary, #1e3c72);
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover { text-decoration: underline; }
        .autobid-toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateX(200%);
            transition: transform 0.4s;
            z-index: 10000;
        }
        .autobid-toast.show { transform: translateX(0); }
        .autobid-toast.success { background: #27ae60; }
        .autobid-toast.error { background: var(--accent, #e74c3c); }
        
        .upcoming-slider-items {
            display: flex;
            gap: 1.5rem;
            overflow-x: auto;
            padding: 1rem 0;
        }
        .upcoming-item {
            min-width: 280px;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
        }
        .upcoming-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }
        .live-auction-card {
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
        }
        .live-auction-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }
        .current-bid {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--secondary, #2a5298);
            margin: 0.5rem 0;
        }
        .auction-timer {
            font-size: 1.1rem;
            color: var(--accent, #e74c3c);
            margin: 0.5rem 0;
        }
    `;
    document.head.appendChild(style);
})();