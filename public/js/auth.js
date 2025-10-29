(function() {
    function showToast(message, type = 'success') {
        let toast = document.getElementById('autobid-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'autobid-toast';
            toast.className = 'autobid-toast';
            document.body.appendChild(toast);
        }
        toast.innerHTML = `<span>${message}</span>`;
        toast.className = `autobid-toast ${type} show`;
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function toggleForms(showRegister) {
        const loginForm = document.getElementById('autobid-login-form');
        const registerForm = document.getElementById('autobid-register-form');
        if (showRegister) {
            loginForm.style.display = 'none';
            registerForm.style.display = 'block';
        } else {
            loginForm.style.display = 'block';
            registerForm.style.display = 'none';
        }
    }
document.addEventListener
    // Nueva función: cargar pujas
    async function loadUserBids() {
        try {
            const res = await fetch(`${autobid_auth_vars.bids_url}`, {
                headers: { 'X-WP-Nonce': autobid_auth_vars.nonce }
            });
            const bids = await res.json();

            const container = document.getElementById('bids-list');
            if (bids.length === 0) {
                container.innerHTML = '<p>No has realizado ninguna puja aún.</p>';
                return;
            }

            const html = bids.map(bid => `
                <div class="bid-item">
                    <h4>${bid.vehicle_name}</h4>
                    <p>Puja: ${new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'USD' }).format(bid.bid_amount)}</p>
                    <p>Fecha: ${new Date(bid.created_at).toLocaleString()}</p>
                </div>
            `).join('');
            container.innerHTML = html;
        } catch (err) {
            showToast('Error al cargar pujas.', 'error');
        }
    }

    // Nueva función: actualizar perfil
    async function updateProfile(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);

        try {
            const res = await fetch(`${autobid_auth_vars.auth_api_url}/update-profile`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': autobid_auth_vars.nonce
                },
                body: JSON.stringify(data)
            });

            const response = await res.json();

            if (res.ok) {
                showToast(response.message, 'success');
            } else {
                showToast(response.message || 'Error al actualizar.', 'error');
            }
        } catch (err) {
            showToast('Error de conexión.', 'error');
        }
    }

  // Panel de administración mejorado
async function loadAdminVehicles() {
    const content = document.getElementById('vehicle-list');
    content.innerHTML = `
        <div class="admin-panel-nav">
            <button class="nav-btn active" data-view="list">Listado</button>
            <button class="nav-btn" data-view="create">Crear Vehículo</button>
            <button class="nav-btn" data-view="bulk">Editar Masivo</button>
            <button class="nav-btn" data-view="reports">Reportes</button>
        </div>
        <div class="admin-panel-views">
            <div class="view active" id="view-list">
                <div class="admin-search-bar">
                    <input type="text" id="admin-search" placeholder="Buscar por nombre, marca, modelo...">
                    <select id="admin-filter-type">
                        <option value="">Todos los tipos</option>
                        <option value="venta">Venta</option>
                        <option value="subasta">Subasta</option>
                    </select>
                    <button id="admin-apply-filters">Filtrar</button>
                </div>
                <div class="admin-view-controls">
                    <button id="toggle-layout">Cambiar a Tabla</button>
                    <button id="export-csv">Exportar a CSV</button>
                </div>
                <!-- Cambiamos el ID para que coincida con el estilo del catálogo -->
                <div id="admin-vehicle-grid" class="vehicle-grid"></div>
            </div>
            <div class="view" id="view-create">
                <h3>Crear Nuevo Vehículo</h3>
                <form id="create-vehicle-form">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="description"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Marca</label>
                            <input type="text" name="brand">
                        </div>
                        <div class="form-group">
                            <label>Modelo</label>
                            <input type="text" name="model">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Año</label>
                            <input type="number" name="year">
                        </div>
                        <div class="form-group">
                            <label>Color</label>
                            <input type="text" name="color">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Ubicación</label>
                        <input type="text" name="location">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Precio</label>
                            <input type="number" name="price" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Moneda</label>
                            <input type="text" name="currency" value="USD">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="type">
                            <option value="venta">Venta</option>
                            <option value="subasta">Subasta</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fecha de inicio (subasta)</label>
                            <input type="datetime-local" name="start_time">
                        </div>
                        <div class="form-group">
                            <label>Fecha de fin (subasta)</label>
                            <input type="datetime-local" name="end_time">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Galería de Imágenes</label>
                        <div id="gallery-preview"></div>
                        <input type="hidden" name="gallery" id="gallery-field" value="">
                        <button type="button" class="button" id="upload_gallery_button_create">Agregar imágenes</button>
                    </div>
                    <button type="submit">Crear Vehículo</button>
                    <button type="button" id="cancel-create">Cancelar</button>
                </form>
            </div>
            <div class="view" id="view-bulk">
                <h3>Edición Masiva</h3>
                <p>Selecciona vehículos y aplica cambios a todos a la vez.</p>
            </div>
            <div class="view" id="view-reports">
                <h3>Reportes</h3>
                <button id="export-csv-full">Exportar Todos los Vehículos</button>
                <button id="show-no-image">Ver Vehículos sin Imagen</button>
                <button id="show-ended-auctions">Ver Subastas Finalizadas</button>
            </div>
        </div>
    `;

    // Cargar listado inicial
    loadVehicleList();

    // Eventos de navegación
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
            e.target.classList.add('active');
            document.getElementById(`view-${e.target.dataset.view}`).classList.add('active');
        });
    });

    // Eventos de búsqueda y filtros
    document.getElementById('admin-apply-filters').addEventListener('click', () => {
        loadVehicleList();
    });

    document.getElementById('toggle-layout').addEventListener('click', toggleLayout);
    document.getElementById('export-csv').addEventListener('click', exportToCSV);
    document.getElementById('export-csv-full').addEventListener('click', exportToCSV);
    document.getElementById('show-no-image').addEventListener('click', showNoImageVehicles);
    document.getElementById('show-ended-auctions').addEventListener('click', showEndedAuctions);

    // Eventos del formulario de creación
    document.getElementById('create-vehicle-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);

        try {
            const res = await fetch(`${autobid_auth_vars.admin_api_url}/vehicles`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': autobid_auth_vars.nonce
                },
                body: JSON.stringify(data)
            });

            const response = await res.json();

            if (res.ok) {
                showToast(response.message, 'success');
                // Volver a la vista de listado
                document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
                document.querySelector('.nav-btn[data-view="list"]').classList.add('active');
                document.getElementById('view-list').classList.add('active');
                loadVehicleList();
            } else {
                showToast(response.message || 'Error al crear.', 'error');
            }
        } catch (err) {
            showToast('Error de conexión.', 'error');
        }
    });

    document.getElementById('cancel-create').addEventListener('click', () => {
        document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
        document.querySelector('.nav-btn[data-view="list"]').classList.add('active');
        document.getElementById('view-list').classList.add('active');
    });

    // Inicializar el media uploader para crear
    document.getElementById('upload_gallery_button_create').addEventListener('click', function(e) {
        e.preventDefault();

        // Verificar si wp.media está disponible
        if (typeof wp === 'undefined' || !wp.media) {
            console.error('WordPress Media Library no está disponible aún.');
            showToast('Error: Media Library no cargada.', 'error');
            return;
        }

        const frame = wp.media({
            title: 'Selecciona imágenes',
            button: { text: 'Usar seleccionadas' },
            multiple: true,
            library: { type: 'image' }
        });

        frame.on('select', function() {
            const selection = frame.state().get('selection');
            let ids = document.getElementById('gallery-field').value.split(',').filter(id => id);
            selection.each(function(attachment) {
                ids.push(attachment.id);
            });
            document.getElementById('gallery-field').value = ids.join(',');
            const preview = document.getElementById('gallery-preview');
            preview.innerHTML = ids.map(id => {
                const url = wp.media.attachment(id).get('url');
                return `<img src="${url}" style="height: 80px; margin: 5px; border: 1px solid #ddd; border-radius: 4px;">`;
            }).join('');
        });

        frame.open();
    });
}

// Nueva función: cargar listado de vehículos con filtros
async function loadVehicleList() {
    try {
        const res = await fetch(`${autobid_auth_vars.admin_api_url}/vehicles`, {
            headers: { 'X-WP-Nonce': autobid_auth_vars.nonce }
        });
        let vehicles = await res.json();

        // Aplicar filtros
        const searchTerm = document.getElementById('admin-search').value.toLowerCase();
        const typeFilter = document.getElementById('admin-filter-type').value;

        if (searchTerm) {
            vehicles = vehicles.filter(v => 
                v.name.toLowerCase().includes(searchTerm) ||
                (v.brand && v.brand.toLowerCase().includes(searchTerm)) ||
                (v.model && v.model.toLowerCase().includes(searchTerm))
            );
        }

        if (typeFilter) {
            vehicles = vehicles.filter(v => v.type === typeFilter);
        }

        renderVehicleList(vehicles);
    } catch (err) {
        showToast('Error al cargar vehículos.', 'error');
    }
}

// Nueva función: renderizar listado (tarjetas del catálogo o tabla)
function renderVehicleList(vehicles) {
    const grid = document.getElementById('admin-vehicle-grid');
    const isTable = grid.classList.contains('table-layout');

    if (isTable) {
        const headers = ['Nombre', 'Marca', 'Modelo', 'Año', 'Tipo', 'Precio', 'Acciones'];
        const rows = vehicles.map(v => `
            <tr>
                <td>${v.name}</td>
                <td>${v.brand}</td>
                <td>${v.model}</td>
                <td>${v.year}</td>
                <td>${v.type}</td>
                <td>${v.price ? new Intl.NumberFormat('es-ES', { style: 'currency', currency: v.currency }).format(v.price) : '—'}</td>
                <td>
                    <button class="edit-btn" data-id="${v.id}">Editar</button>
                    <button class="delete-btn" data-id="${v.id}">Eliminar</button>
                </td>
            </tr>
        `).join('');

        grid.innerHTML = `
            <table class="admin-table">
                <thead><tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr></thead>
                <tbody>${rows}</tbody>
            </table>
        `;
    } else {
        // ✅ Versión del catálogo: renderizar como tarjetas
        const html = vehicles.map(v => {
            const isAuction = v.type === 'subasta';
            const badgeText = isAuction ? 
                (v.auction_status === 'upcoming' ? 'PRÓXIMAMENTE' : 
                 v.auction_status === 'live' ? 'EN CURSO' : 'FINALIZADA') : 
                (autobid_auth_vars.label_sale || 'Venta');
            const badgeClass = `vehicle-type-badge" data-status="${isAuction ? v.auction_status : 'sale'}`;
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
                        <div class="admin-vehicle-actions">
                            <button class="edit-btn" data-id="${v.id}">Editar</button>
                            <button class="delete-btn" data-id="${v.id}">Eliminar</button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        grid.innerHTML = html;
    }

    // Añadir eventos a los botones de editar y eliminar
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const id = e.target.dataset.id;
            openEditForm(id);
        });
    });

    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const id = e.target.dataset.id;
            if (!confirm('¿Estás seguro de eliminar este vehículo?')) return;
            const res = await fetch(`${autobid_auth_vars.admin_api_url}/vehicles/${id}`, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': autobid_auth_vars.nonce }
            });
            if (res.ok) {
                showToast('Vehículo eliminado.', 'success');
                loadVehicleList();
            } else {
                showToast('Error al eliminar.', 'error');
            }
        });
    });
}

// Función auxiliar para formatear moneda
function formatCurrency(amount, currency = 'USD') {
    if (!amount || isNaN(amount)) amount = 0;
    return new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 0
    }).format(amount);
}

// Nueva función: alternar entre tarjetas y tabla
function toggleLayout() {
    const grid = document.getElementById('admin-vehicle-grid');
    const btn = document.getElementById('toggle-layout');
    if (grid.classList.contains('table-layout')) {
        grid.classList.remove('table-layout');
        btn.textContent = 'Cambiar a Tabla';
    } else {
        grid.classList.add('table-layout');
        btn.textContent = 'Cambiar a Tarjetas';
    }
    loadVehicleList();
}

// Nueva función: exportar a CSV
function exportToCSV() {
    // Lógica para exportar vehículos a CSV
    showToast('Exportar a CSV en desarrollo.', 'success');
}

// Nueva función: ver vehículos sin imagen
function showNoImageVehicles() {
    showToast('Filtrar vehículos sin imagen en desarrollo.', 'success');
}

// Nueva función: ver subastas finalizadas
function showEndedAuctions() {
    showToast('Ver subastas finalizadas en desarrollo.', 'success');
}


// Nueva función: cargar listado de vehículos con filtros
async function loadVehicleList() {
    try {
        const res = await fetch(`${autobid_auth_vars.admin_api_url}/vehicles`, {
            headers: { 'X-WP-Nonce': autobid_auth_vars.nonce }
        });
        let vehicles = await res.json();

        // Aplicar filtros
        const searchTerm = document.getElementById('admin-search').value.toLowerCase();
        const typeFilter = document.getElementById('admin-filter-type').value;

        if (searchTerm) {
            vehicles = vehicles.filter(v => 
                v.name.toLowerCase().includes(searchTerm) ||
                (v.brand && v.brand.toLowerCase().includes(searchTerm)) ||
                (v.model && v.model.toLowerCase().includes(searchTerm))
            );
        }

        if (typeFilter) {
            vehicles = vehicles.filter(v => v.type === typeFilter);
        }

        renderVehicleList(vehicles);
    } catch (err) {
        showToast('Error al cargar vehículos.', 'error');
    }
}

// Nueva función: renderizar listado (tarjetas o tabla)
function renderVehicleList(vehicles) {
    const grid = document.getElementById('admin-vehicle-grid');
    const isTable = grid.classList.contains('table-layout');

    if (isTable) {
        const headers = ['Nombre', 'Marca', 'Modelo', 'Año', 'Tipo', 'Precio', 'Acciones'];
        const rows = vehicles.map(v => `
            <tr>
                <td>${v.name}</td>
                <td>${v.brand}</td>
                <td>${v.model}</td>
                <td>${v.year}</td>
                <td>${v.type}</td>
                <td>${v.price ? new Intl.NumberFormat('es-ES', { style: 'currency', currency: v.currency }).format(v.price) : '—'}</td>
                <td>
                    <button class="edit-btn" data-id="${v.id}">Editar</button>
                    <button class="delete-btn" data-id="${v.id}">Eliminar</button>
                </td>
            </tr>
        `).join('');

        grid.innerHTML = `
            <table class="admin-table">
                <thead><tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr></thead>
                <tbody>${rows}</tbody>
            </table>
        `;
    } else {
        const html = vehicles.map(v => `
            <div class="admin-vehicle-card">
                <div class="admin-vehicle-image">
                    <img src="${v.image}" alt="${v.name}" loading="lazy">
                </div>
                <div class="admin-vehicle-info">
                    <h4>${v.name}</h4>
                    <p><strong>Marca:</strong> ${v.brand || '—'}</p>
                    <p><strong>Modelo:</strong> ${v.model || '—'}</p>
                    <p><strong>Año:</strong> ${v.year || '—'}</p>
                    <p><strong>Tipo:</strong> ${v.type === 'venta' ? autobid_auth_vars.label_sale : autobid_auth_vars.label_auction}</p>
                    <div class="admin-vehicle-actions">
                        <button class="edit-btn" data-id="${v.id}">Editar</button>
                        <button class="delete-btn" data-id="${v.id}">Eliminar</button>
                    </div>
                </div>
            </div>
        `).join('');
        grid.innerHTML = html;
    }

    // Añadir eventos a los botones de editar y eliminar
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const id = e.target.dataset.id;
            openEditForm(id);
        });
    });

    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const id = e.target.dataset.id;
            if (!confirm('¿Estás seguro de eliminar este vehículo?')) return;
            const res = await fetch(`${autobid_auth_vars.admin_api_url}/vehicles/${id}`, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': autobid_auth_vars.nonce }
            });
            if (res.ok) {
                showToast('Vehículo eliminado.', 'success');
                loadVehicleList();
            } else {
                showToast('Error al eliminar.', 'error');
            }
        });
    });
}

// Nueva función: alternar entre tarjetas y tabla
function toggleLayout() {
    const grid = document.getElementById('admin-vehicle-grid');
    const btn = document.getElementById('toggle-layout');
    if (grid.classList.contains('table-layout')) {
        grid.classList.remove('table-layout');
        btn.textContent = 'Cambiar a Tabla';
    } else {
        grid.classList.add('table-layout');
        btn.textContent = 'Cambiar a Tarjetas';
    }
    loadVehicleList();
}

// Nueva función: exportar a CSV
function exportToCSV() {
    // Lógica para exportar vehículos a CSV
    showToast('Exportar a CSV en desarrollo.', 'success');
}

// Nueva función: ver vehículos sin imagen
function showNoImageVehicles() {
    showToast('Filtrar vehículos sin imagen en desarrollo.', 'success');
}

// Nueva función: ver subastas finalizadas
function showEndedAuctions() {
    showToast('Ver subastas finalizadas en desarrollo.', 'success');
}

    // Abrir formulario de edición
    async function openEditForm(id) {
        try {
            const res = await fetch(`${autobid_auth_vars.admin_api_url}/vehicles/${id}`, {
                headers: { 'X-WP-Nonce': autobid_auth_vars.nonce }
            });
            const vehicle = await res.json();

            const content = document.getElementById('vehicle-list');
            content.innerHTML = `
                <h3>Editar Vehículo</h3>
                <form id="edit-vehicle-form">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="name" value="${vehicle.name || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="description">${vehicle.description || ''}</textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Marca</label>
                            <input type="text" name="brand" value="${vehicle.brand || ''}">
                        </div>
                        <div class="form-group">
                            <label>Modelo</label>
                            <input type="text" name="model" value="${vehicle.model || ''}">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Año</label>
                            <input type="number" name="year" value="${vehicle.year || ''}">
                        </div>
                        <div class="form-group">
                            <label>Color</label>
                            <input type="text" name="color" value="${vehicle.color || ''}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Ubicación</label>
                        <input type="text" name="location" value="${vehicle.location || ''}">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Precio</label>
                            <input type="number" name="price" value="${vehicle.price || ''}" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Moneda</label>
                            <input type="text" name="currency" value="${vehicle.currency || 'USD'}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="type">
                            <option value="venta" ${vehicle.type === 'venta' ? 'selected' : ''}>Venta</option>
                            <option value="subasta" ${vehicle.type === 'subasta' ? 'selected' : ''}>Subasta</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fecha de inicio (subasta)</label>
                            <input type="datetime-local" name="start_time" value="${vehicle.start_time ? new Date(vehicle.start_time).toISOString().slice(0, 16) : ''}">
                        </div>
                        <div class="form-group">
                            <label>Fecha de fin (subasta)</label>
                            <input type="datetime-local" name="end_time" value="${vehicle.end_time ? new Date(vehicle.end_time).toISOString().slice(0, 16) : ''}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Galería de Imágenes</label>
                        <div id="gallery-preview">
                            ${(vehicle.gallery || []).map(img => `<img src="${img}" style="height: 80px; margin: 5px; border: 1px solid #ddd; border-radius: 4px;">`).join('')}
                        </div>
                        <input type="hidden" name="gallery" id="gallery-field" value="${(vehicle.gallery_ids || []).join(',')}">
                        <button type="button" class="button" id="upload_gallery_button">Agregar imágenes</button>
                    </div>
                    <button type="submit">Guardar Cambios</button>
                    <button type="button" id="cancel-edit">Cancelar</button>
                </form>
            `;

            // Inicializar el media uploader
            document.getElementById('upload_gallery_button').addEventListener('click', function(e) {
                e.preventDefault();

                // Verificar si wp.media está disponible
                if (typeof wp === 'undefined' || !wp.media) {
                    console.error('WordPress Media Library no está disponible aún.');
                    showToast('Error: Media Library no cargada.', 'error');
                    return;
                }

                const frame = wp.media({
                    title: 'Selecciona imágenes',
                    button: { text: 'Usar seleccionadas' },
                    multiple: true,
                    library: { type: 'image' }
                });

                frame.on('select', function() {
                    const selection = frame.state().get('selection');
                    let ids = document.getElementById('gallery-field').value.split(',').filter(id => id);
                    selection.each(function(attachment) {
                        ids.push(attachment.id);
                    });
                    document.getElementById('gallery-field').value = ids.join(',');
                    const preview = document.getElementById('gallery-preview');
                    preview.innerHTML = ids.map(id => {
                        const url = wp.media.attachment(id).get('url');
                        return `<img src="${url}" style="height: 80px; margin: 5px; border: 1px solid #ddd; border-radius: 4px;">`;
                    }).join('');
                });

                frame.open();
            });

            document.getElementById('edit-vehicle-form').addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                const data = Object.fromEntries(formData);
                data.id = id;

                try {
                    const res = await fetch(`${autobid_auth_vars.admin_api_url}/vehicles/${id}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': autobid_auth_vars.nonce
                        },
                        body: JSON.stringify(data)
                    });

                    const response = await res.json();

                    if (res.ok) {
                        showToast(response.message, 'success');
                        loadAdminVehicles();
                    } else {
                        showToast(response.message || 'Error al actualizar.', 'error');
                    }
                } catch (err) {
                    showToast('Error de conexión.', 'error');
                }
            });

            document.getElementById('cancel-edit').addEventListener('click', () => {
                loadAdminVehicles();
            });
        } catch (err) {
            showToast('Error al cargar vehículo.', 'error');
        }
    }

    // Abrir formulario de creación
    function openCreateForm() {
        const content = document.getElementById('vehicle-list');
        content.innerHTML = `
            <h3>Crear Nuevo Vehículo</h3>
            <form id="create-vehicle-form">
                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="description"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Marca</label>
                        <input type="text" name="brand">
                    </div>
                    <div class="form-group">
                        <label>Modelo</label>
                        <input type="text" name="model">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Año</label>
                        <input type="number" name="year">
                    </div>
                    <div class="form-group">
                        <label>Color</label>
                        <input type="text" name="color">
                    </div>
                </div>
                <div class="form-group">
                    <label>Ubicación</label>
                    <input type="text" name="location">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Precio</label>
                        <input type="number" name="price" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Moneda</label>
                        <input type="text" name="currency" value="USD">
                    </div>
                </div>
                <div class="form-group">
                    <label>Tipo</label>
                    <select name="type">
                        <option value="venta">Venta</option>
                        <option value="subasta">Subasta</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha de inicio (subasta)</label>
                        <input type="datetime-local" name="start_time">
                    </div>
                    <div class="form-group">
                        <label>Fecha de fin (subasta)</label>
                        <input type="datetime-local" name="end_time">
                    </div>
                </div>
                <div class="form-group">
                    <label>Galería de Imágenes</label>
                    <div id="gallery-preview"></div>
                    <input type="hidden" name="gallery" id="gallery-field" value="">
                    <button type="button" class="button" id="upload_gallery_button_create">Agregar imágenes</button>
                </div>
                <button type="submit">Crear Vehículo</button>
                <button type="button" id="cancel-create">Cancelar</button>
            </form>
        `;

        // Inicializar el media uploader para crear
        document.getElementById('upload_gallery_button_create').addEventListener('click', function(e) {
            e.preventDefault();

            // Verificar si wp.media está disponible
            if (typeof wp === 'undefined' || !wp.media) {
                console.error('WordPress Media Library no está disponible aún.');
                showToast('Error: Media Library no cargada.', 'error');
                return;
            }

            const frame = wp.media({
                title: 'Selecciona imágenes',
                button: { text: 'Usar seleccionadas' },
                multiple: true,
                library: { type: 'image' }
            });

            frame.on('select', function() {
                const selection = frame.state().get('selection');
                let ids = document.getElementById('gallery-field').value.split(',').filter(id => id);
                selection.each(function(attachment) {
                    ids.push(attachment.id);
                });
                document.getElementById('gallery-field').value = ids.join(',');
                const preview = document.getElementById('gallery-preview');
                preview.innerHTML = ids.map(id => {
                    const url = wp.media.attachment(id).get('url');
                    return `<img src="${url}" style="height: 80px; margin: 5px; border: 1px solid #ddd; border-radius: 4px;">`;
                }).join('');
            });

            frame.open();
        });

        document.getElementById('create-vehicle-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);

            try {
                const res = await fetch(`${autobid_auth_vars.admin_api_url}/vehicles`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': autobid_auth_vars.nonce
                    },
                    body: JSON.stringify(data)
                });

                const response = await res.json();

                if (res.ok) {
                    showToast(response.message, 'success');
                    loadAdminVehicles();
                } else {
                    showToast(response.message || 'Error al crear.', 'error');
                }
            } catch (err) {
                showToast('Error de conexión.', 'error');
            }
        });

        document.getElementById('cancel-create').addEventListener('click', () => {
            loadAdminVehicles();
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const loginForm = document.getElementById('autobid-login-form');
        const registerForm = document.getElementById('autobid-register-form');
        const switchToRegister = document.getElementById('switch-to-register');
        const switchToLogin = document.getElementById('switch-to-login');
        const logoutBtn = document.getElementById('logout-btn');
        const profileForm = document.getElementById('autobid-profile-form');
        const createVehicleBtn = document.getElementById('create-vehicle-btn');

        if (switchToRegister) {
            switchToRegister.addEventListener('click', (e) => {
                e.preventDefault();
                toggleForms(true);
            });
        }

        if (switchToLogin) {
            switchToLogin.addEventListener('click', (e) => {
                e.preventDefault();
                toggleForms(false);
            });
        }

        if (loginForm) {
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(loginForm);
                const data = Object.fromEntries(formData);

                try {
                    const res = await fetch(`${autobid_auth_vars.auth_api_url}/login`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': autobid_auth_vars.nonce
                        },
                        body: JSON.stringify(data)
                    });

                    const response = await res.json();

                    if (res.ok) {
                        showToast(response.message, 'success');
                        // ✅ Redirigir al usuario normal a su dashboard
                        setTimeout(() => {
                            // Si existe la URL de redirección en la respuesta del servidor, usarla
                            // Si no, ir a la página de dashboard de usuario
                            const redirectUrl = response.redirect_url || 
                                            autobid_auth_vars.user_dashboard_url || 
                                            autobid_auth_vars.redirect_url;
                            window.location.href = redirectUrl;
                        }, 1000);
                    } else {
                        showToast(response.message || 'Error al iniciar sesión.', 'error');
                    }
                } catch (err) {
                    showToast('Error de conexión.', 'error');
                }
            });
        }

        if (registerForm) {
            registerForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(registerForm);
                const data = Object.fromEntries(formData);

                try {
                    const res = await fetch(`${autobid_auth_vars.auth_api_url}/register`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': autobid_auth_vars.nonce
                        },
                        body: JSON.stringify(data)
                    });

                    const response = await res.json();

                    if (res.ok) {
                        showToast(response.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(response.message || 'Error al registrarse.', 'error');
                    }
                } catch (err) {
                    showToast('Error de conexión.', 'error');
                }
            });
        }

        // Logout
        if (logoutBtn) {
            logoutBtn.addEventListener('click', async () => {
                try {
                    const res = await fetch(`${autobid_auth_vars.auth_api_url}/logout`, {
                        method: 'POST',
                        headers: { 'X-WP-Nonce': autobid_auth_vars.nonce }
                    });
                    if (res.ok) {
                        showToast('Sesión cerrada correctamente.', 'success');
                        setTimeout(() => location.reload(), 1000);
                    }
                } catch (err) {
                    showToast('Error al cerrar sesión.', 'error');
                }
            });
        }

        // Cargar pujas
        if (document.getElementById('bids-list')) {
            loadUserBids();
        }

        // Actualizar perfil
        if (profileForm) {
            profileForm.addEventListener('submit', updateProfile);
        }

        // Dashboard
        const dashboardLinks = document.querySelectorAll('.dashboard-link');
        dashboardLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const section = link.dataset.section;
                const content = document.getElementById('dashboard-content');
                if (section === 'bids') {
                    content.innerHTML = '<div id="bids-list">Cargando pujas...</div>';
                    loadUserBids();
                } else if (section === 'profile') {
                    content.innerHTML = '<p>Cargando perfil...</p>';
                    setTimeout(() => {
                        content.innerHTML = `
                            <h3>Editar Perfil</h3>
                            <form id="autobid-profile-form">
                                <div class="form-group">
                                    <label>Nombre</label>
                                    <input type="text" name="first_name" value="${autobid_auth_vars.current_user.first_name || ''}">
                                </div>
                                <div class="form-group">
                                    <label>Apellido</label>
                                    <input type="text" name="last_name" value="${autobid_auth_vars.current_user.last_name || ''}">
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" value="${autobid_auth_vars.current_user.user_email}">
                                </div>
                                <div class="form-group">
                                    <label>Nueva Contraseña</label>
                                    <input type="password" name="new_password">
                                </div>
                                <div class="form-group">
                                    <label>Confirmar Contraseña</label>
                                    <input type="password" name="confirm_password">
                                </div>
                                <button type="submit">Guardar Cambios</button>
                            </form>
                        `;
                        document.getElementById('autobid-profile-form').addEventListener('submit', updateProfile);
                    }, 300);
                } else if (section === 'admin') {
                    content.innerHTML = '<div id="vehicle-list">Cargando vehículos...</div>';
                    loadAdminVehicles();
                }else if (section === 'favorites') { // <-- Nueva condición
                    loadUserFavorites();
                }
            });
        });

        // Cargar panel de administrador si existe el contenedor
        if (document.querySelector('.autobid-admin-panel')) {
            loadAdminVehicles();

            // Añadir evento al botón de crear vehículo
            if (createVehicleBtn) {
                createVehicleBtn.addEventListener('click', openCreateForm);
            }
        }
    });

    // Cargar pujas del usuario
async function loadUserBids() {
    try {
        const res = await fetch(`${autobid_auth_vars.bids_url}`, {
            headers: { 'X-WP-Nonce': autobid_auth_vars.nonce }
        });
        const bids = await res.json();

        const container = document.getElementById('bids-list');
        if (bids.length === 0) {
            container.innerHTML = '<p>No has realizado ninguna puja aún.</p>';
            return;
        }

        const html = bids.map(bid => `
            <div class="bid-item">
                <h4>${bid.vehicle_name}</h4>
                <p>Puja: ${new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'USD' }).format(bid.bid_amount)}</p>
                <p>Fecha: ${new Date(bid.created_at).toLocaleString()}</p>
            </div>
        `).join('');
        container.innerHTML = html;
    } catch (err) {
        showToast('Error al cargar pujas.', 'error');
    }
}

// Cargar perfil del usuario
function loadUserProfile() {
    const user = autobid_auth_vars.current_user;
    const content = document.getElementById('dashboard-content');
    content.innerHTML = `
        <h3>Editar Perfil</h3>
        <form id="autobid-profile-form">
            <div class="form-group">
                <label>Nombre</label>
                <input type="text" name="first_name" value="${user.first_name || ''}">
            </div>
            <div class="form-group">
                <label>Apellido</label>
                <input type="text" name="last_name" value="${user.last_name || ''}">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="${user.user_email}">
            </div>
            <div class="form-group">
                <label>Nueva Contraseña</label>
                <input type="password" name="new_password">
            </div>
            <div class="form-group">
                <label>Confirmar Contraseña</label>
                <input type="password" name="confirm_password">
            </div>
            <button type="submit">Guardar Cambios</button>
        </form>
    `;

    document.getElementById('autobid-profile-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);

        try {
            const res = await fetch(`${autobid_auth_vars.auth_api_url}/update-profile`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': autobid_auth_vars.nonce
                },
                body: JSON.stringify(data)
            });

            const response = await res.json();

            if (res.ok) {
                showToast(response.message, 'success');
            } else {
                showToast(response.message || 'Error al actualizar.', 'error');
            }
        } catch (err) {
            showToast('Error de conexión.', 'error');
        }
    });
}

// Cargar favoritos del usuario (funcionalidad en desarrollo)
function loadUserFavorites() {
    const content = document.getElementById('dashboard-content');
    content.innerHTML = '<p>Funcionalidad de favoritos en desarrollo.</p>';
}

// Añadir eventos al dashboard de usuario
document.addEventListener('DOMContentLoaded', () => {
    const dashboardLinks = document.querySelectorAll('.dashboard-link');
    if (dashboardLinks.length > 0) {
        dashboardLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const section = link.dataset.section;
                if (section === 'bids') {
                    document.getElementById('dashboard-content').innerHTML = '<div id="bids-list">Cargando pujas...</div>';
                    loadUserBids();
                } else if (section === 'profile') {
                    loadUserProfile();
                } else if (section === 'favorites') {
                    loadUserFavorites();
                }else if (section === 'auction-history') { // <-- Nueva condición
                    loadAuctionHistory();
                }
            });
        });
    }
});

// Cargar pujas del usuario
async function loadUserBids() {
    try {
        const res = await fetch(`${autobid_auth_vars.bids_url}`, {
            headers: { 'X-WP-Nonce': autobid_auth_vars.nonce }
        });
        const bids = await res.json();

        const container = document.getElementById('dashboard-content');
        if (bids.length === 0) {
            container.innerHTML = '<p>No has realizado ninguna puja aún.</p>';
            return;
        }

        const html = bids.map(bid => `
            <div class="user-dashboard-vehicle-card">
                <div class="user-dashboard-vehicle-image">
                    <img src="${bid.image || 'https://placehold.co/600x400'}" alt="${bid.vehicle_name}">
                </div>
                <div class="user-dashboard-vehicle-info">
                    <h4>${bid.vehicle_name}</h4>
                    <p>Puja: ${new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'USD' }).format(bid.bid_amount)}</p>
                    <p>Fecha: ${new Date(bid.created_at).toLocaleString()}</p>
                    <p>Estado: ${bid.status || 'Activa'}</p>
                </div>
            </div>
        `).join('');
        container.innerHTML = html;
    } catch (err) {
        console.error('Error al cargar pujas:', err);
        document.getElementById('dashboard-content').innerHTML = '<p>Error al cargar pujas.</p>';
    }
}

// Cargar perfil del usuario
// Cargar perfil del usuario (actualizado)
function loadUserProfile() {
    const user = autobid_auth_vars.current_user;
    const avatarUrl = getAvatarUrl(user.ID); // <-- Función para obtener avatar
    const content = document.getElementById('dashboard-content');
    content.innerHTML = `
        <h3>Editar Perfil</h3>
        <form id="autobid-profile-form">
            <div class="form-group">
                <label>Imagen de Perfil</label>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <img src="${avatarUrl}" alt="Avatar" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover;">
                    <button type="button" id="upload-avatar-btn">Cambiar Avatar</button>
                    <input type="hidden" name="avatar_id" id="avatar_id" value="">
                </div>
            </div>
            <div class="form-group">
                <label>Nombre</label>
                <input type="text" name="first_name" value="${user.first_name || ''}">
            </div>
            <div class="form-group">
                <label>Apellido</label>
                <input type="text" name="last_name" value="${user.last_name || ''}">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="${user.user_email}">
            </div>
            <div class="form-group">
                <label>Nueva Contraseña</label>
                <input type="password" name="new_password">
            </div>
            <div class="form-group">
                <label>Confirmar Contraseña</label>
                <input type="password" name="confirm_password">
            </div>
            <button type="submit">Guardar Cambios</button>
        </form>
    `;

    // Inicializar el media uploader para avatar
    document.getElementById('upload-avatar-btn').addEventListener('click', function(e) {
        e.preventDefault();
        if (typeof wp === 'undefined' || !wp.media) {
            showToast('Media Library no disponible.', 'error');
            return;
        }
        const frame = wp.media({
            title: 'Selecciona una imagen de perfil',
            button: { text: 'Usar esta imagen' },
            multiple: false,
            library: { type: 'image' }
        });
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first();
            document.getElementById('avatar_id').value = attachment.id;
            // Actualizar imagen en el formulario
            const img = document.querySelector('#autobid-profile-form img');
            if (img) img.src = attachment.url;
        });
        frame.open();
    });

    // Evento submit del formulario
    document.getElementById('autobid-profile-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);

        try {
            const res = await fetch(`${autobid_auth_vars.auth_api_url}/update-profile`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': autobid_auth_vars.nonce
                },
                body: JSON.stringify(data)
            });
            const response = await res.json();

            if (res.ok) {
                showToast(response.message, 'success');
                // Opcional: Actualizar avatar en otras partes de la UI si se usa
                autobid_auth_vars.current_user.avatar_url = getAvatarUrl(autobid_auth_vars.current_user.ID, Date.now()); // Forzar recarga
            } else {
                showToast(response.message || 'Error al actualizar.', 'error');
            }
        } catch (err) {
            showToast('Error de conexión.', 'error');
        }
    });
}

// Función para obtener URL del avatar
// public/js/auth.js

// Función para obtener URL del avatar
function getAvatarUrl(userId, timestamp = '') {
    // Opción 1: Usar el avatar devuelto por el endpoint de usuario (autobid_auth_vars.current_user.avatar_url)
    // Este valor debería haber sido devuelto por get_current_user en class-auth.php
    if (autobid_auth_vars.current_user && autobid_auth_vars.current_user.avatar_url) {
        // Devolver la URL del avatar actual, opcionalmente con un timestamp para forzar recarga
        return autobid_auth_vars.current_user.avatar_url + (timestamp ? `?t=${timestamp}` : '');
    }

    // Opción 2: Si no se tiene avatar_url, usar gravatar con email
    // Esto es más complejo en JS, pero si no tienes avatar_url, puedes usar el email
    // y construir la URL de gravatar. Pero NO uses md5 aquí.
    // Para evitar md5, lo ideal es que el backend ya devuelva la URL completa de gravatar si no hay avatar personalizado.
    // Por ahora, si no tenemos avatar_url, devolvemos una imagen por defecto.
    console.warn("No se encontró avatar_url en autobid_auth_vars.current_user. Devolviendo imagen por defecto.");
    return 'https://placehold.co/100x100?text=U'; // Imagen por defecto
}

// Elimina o comenta esta línea si la tenías:
// const hash = md5(email); // ❌ No usar md5 en JS si no está definida

// Función para obtener ID del avatar (esto se haría mejor en PHP y se pasaría en autobid_auth_vars)
function getAvatarId(userId) {
    // Esta es una aproximación JS, no ideal.
    // La mejor práctica es que el endpoint de usuario (auth/me) devuelva el avatar_id.
    // Por ahora, simulamos que no lo tenemos en JS y lo obtenemos en PHP.
    return null; // Placeholder
}

// Cargar favoritos del usuario (funcionalidad en desarrollo)
function loadUserFavorites() {
    const content = document.getElementById('dashboard-content');
    content.innerHTML = '<p>Funcionalidad de favoritos en desarrollo.</p>';
}

// Añadir eventos al dashboard de usuario
document.addEventListener('DOMContentLoaded', () => {
    const dashboardLinks = document.querySelectorAll('.dashboard-link');
    if (dashboardLinks.length > 0) {
        dashboardLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const section = link.dataset.section;
                if (section === 'bids') {
                    loadUserBids();
                } else if (section === 'profile') {
                    loadUserProfile();
                } else if (section === 'favorites') {
                    loadUserFavorites();
                }
            });
        });
    }
});

// --- Favoritos ---

// Verificar si un vehículo está en favoritos
async function isFavorite(vehicleId) {
    try {
        const res = await fetch(`${autobid_auth_vars.favorites_url}`, {
            headers: { 'X-WP-Nonce': autobid_auth_vars.nonce }
        });
        if (!res.ok) return false;
        const favorites = await res.json();
        return favorites.some(fav => fav.id == vehicleId);
    } catch (e) {
        console.error('Error al verificar favoritos:', e);
        return false;
    }
}

// Añadir a favoritos
async function addToFavorites(vehicleId) {
    try {
        const res = await fetch(`${autobid_auth_vars.favorites_url}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': autobid_auth_vars.nonce
            },
            body: JSON.stringify({ vehicle_id: vehicleId })
        });
        const data = await res.json();
        if (res.ok) {
            showToast(data.message, 'success');
            // Actualizar botón si está en catálogo
            const favBtn = document.querySelector(`.fav-btn[data-id="${vehicleId}"]`);
            if (favBtn) favBtn.classList.add('favorited');
        } else {
            showToast(data.message || 'Error al añadir a favoritos.', 'error');
        }
    } catch (e) {
        showToast('Error de conexión.', 'error');
    }
}

// Eliminar de favoritos
async function removeFromFavorites(vehicleId) {
    try {
        const res = await fetch(`${autobid_auth_vars.favorites_url}/${vehicleId}`, {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': autobid_auth_vars.nonce }
        });
        const data = await res.json();
        if (res.ok) {
            showToast(data.message, 'success');
            // Actualizar botón si está en catálogo
            const favBtn = document.querySelector(`.fav-btn[data-id="${vehicleId}"]`);
            if (favBtn) favBtn.classList.remove('favorited');
            // Si estamos en la pestaña de favoritos, recargar la lista
            if (document.querySelector('#view-favorites.active')) {
                loadUserFavorites();
            }
        } else {
            showToast(data.message || 'Error al eliminar de favoritos.', 'error');
        }
    } catch (e) {
        showToast('Error de conexión.', 'error');
    }
}

// Cargar favoritos del usuario (funcionalidad en desarrollo)
async function loadUserFavorites() {
    const content = document.getElementById('dashboard-content');
    content.innerHTML = '<p>Cargando favoritos...</p>';
    try {
        const res = await fetch(`${autobid_auth_vars.favorites_url}`, {
            headers: { 'X-WP-Nonce': autobid_auth_vars.nonce }
        });
        if (!res.ok) throw new Error('Error al cargar favoritos');
        const favorites = await res.json();

        if (favorites.length === 0) {
            content.innerHTML = '<p>No tienes vehículos favoritos aún.</p>';
            return;
        }

        const html = favorites.map(v => {
            const isAuction = v.type === 'subasta';
            const badgeText = isAuction ?
                (v.auction_status === 'upcoming' ? 'PRÓXIMAMENTE' :
                 v.auction_status === 'live' ? 'EN CURSO' : 'FINALIZADA') :
                (autobid_vars?.label_sale || 'Venta');
            const badgeClass = `vehicle-type-badge" data-status="${isAuction ? v.auction_status : 'sale'}`;
            return `
                <div class="user-dashboard-vehicle-card">
                    <div class="user-dashboard-vehicle-image">
                        <img src="${v.image}" alt="${v.name}">
                    </div>
                    <div class="user-dashboard-vehicle-info">
                        <h4>${v.name}</h4>
                        <p>${v.brand} ${v.model} (${v.year})</p>
                        <p class="price">${formatCurrency(v.price, v.currency)}</p>
                        <div class="admin-vehicle-actions">
                            <button class="view-detail-btn" data-id="${v.id}">Ver Detalles</button>
                            <button class="remove-fav-btn" data-id="${v.id}">Eliminar de Favoritos</button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        content.innerHTML = html;

        // Eventos para botones de ver detalle y eliminar
        content.querySelectorAll('.view-detail-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.dataset.id;
                window.location.href = `${autobid_vars.detail_page_url}?id=${id}`;
            });
        });

        content.querySelectorAll('.remove-fav-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.dataset.id;
                removeFromFavorites(id);
            });
        });

    } catch (err) {
        console.error('Error al cargar favoritos:', err);
        content.innerHTML = '<p>Error al cargar favoritos.</p>';
    }
}

// Función auxiliar para formatear moneda (reutilizable)
function formatCurrency(amount, currency = 'USD') {
    if (!amount || isNaN(amount)) amount = 0;
    return new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 0
    }).format(amount);
}

// Cargar historial de subastas del usuario
async function loadAuctionHistory() {
    const content = document.getElementById('dashboard-content');
    content.innerHTML = '<p>Cargando historial de subastas...</p>';
    try {
        const res = await fetch(`${autobid_auth_vars.auction_history_url}`, {
            headers: { 'X-WP-Nonce': autobid_auth_vars.nonce }
        });
        if (!res.ok) throw new Error('Error al cargar historial');
        const history = await res.json();

        let html = '<h3>Subastas Ganadas</h3>';
        if (history.won.length === 0) {
            html += '<p>No has ganado ninguna subasta aún.</p>';
        } else {
            html += history.won.map(v => `
                <div class="user-dashboard-vehicle-card">
                    <div class="user-dashboard-vehicle-image">
                        <img src="${v.image}" alt="${v.name}">
                    </div>
                    <div class="user-dashboard-vehicle-info">
                        <h4>${v.name}</h4>
                        <p>Oferta ganadora: ${formatCurrency(v.current_bid, v.currency)}</p>
                        <p>Fecha: ${v.end_time ? new Date(v.end_time).toLocaleDateString() : 'N/A'}</p>
                    </div>
                </div>
            `).join('');
        }

        html += '<h3 style="margin-top: 2rem;">Subastas Perdidas</h3>';
        if (history.lost.length === 0) {
            html += '<p>No has perdido ninguna subasta aún.</p>';
        } else {
            html += history.lost.map(v => `
                <div class="user-dashboard-vehicle-card">
                    <div class="user-dashboard-vehicle-image">
                        <img src="${v.image}" alt="${v.name}">
                    </div>
                    <div class="user-dashboard-vehicle-info">
                        <h4>${v.name}</h4>
                        <p>Tu última puja: ${formatCurrency(v.current_bid, v.currency)}</p>
                        <p>Ganador: ${v.highest_bidder ? getUserName(v.highest_bidder) : 'N/A'}</p>
                        <p>Fecha: ${v.end_time ? new Date(v.end_time).toLocaleDateString() : 'N/A'}</p>
                    </div>
                </div>
            `).join('');
        }

        content.innerHTML = html;

    } catch (err) {
        console.error('Error al cargar historial:', err);
        content.innerHTML = '<p>Error al cargar historial de subastas.</p>';
    }
}

// Función auxiliar para obtener nombre de usuario (opcional, si se quiere mostrar)
async function getUserName(userId) {
    // Esta función es más compleja si se quiere hacer en JS.
    // Lo ideal es que el endpoint devuelva también el nombre del ganador.
    // Por ahora, devolvemos el ID o un placeholder.
    return `Usuario ${userId}`;
    // O podrías tener una caché de nombres si se cargan al inicio.
}

})();


