<?php
// includes/class-frontend-admin.php
if (!defined('ABSPATH')) exit;

class AutoBid_Frontend_Admin {

    public function __construct() {
        add_action('init', [$this, 'create_admin_panel_page']);
        add_shortcode('autobid_admin_panel', [$this, 'render_admin_panel']);

         // --- CORREGIDO: Registrar el hook admin_init aquí, fuera de cualquier función de renderizado ---
        //add_action('admin_init', [$this, 'register_settings_fields']); // <-- Registrado correctamente
        // --- FIN CORREGIDO ---
    }

   

    public function create_admin_panel_page() {
        $admin_panel_page = get_page_by_path('Panel de Administración');
        if (!$admin_panel_page) {
            $admin_panel_id = wp_insert_post([
                'post_title'   => 'Panel de Administración',
                'post_content' => '[autobid_admin_panel]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => 'admin-panel'
            ]);
        } else {
            $admin_panel_id = $admin_panel_page->ID;
        }
        update_option('autobid_admin_panel_page_id', $admin_panel_id);
    }

    // --- Nueva función: Añadir campo de número de WhatsApp a los ajustes ---
    public function add_whatsapp_setting_field() {
        add_settings_field(
            'autobid_whatsapp_number',
            'Número de WhatsApp para Compras',
            [$this, 'render_whatsapp_number_field'],
            'autobid-settings', // Página de ajustes (ajusta si es diferente)
            'autobid_general_settings_section' // Sección de ajustes (ajusta si es diferente)
        );
        register_setting('autobid_settings_group', 'autobid_whatsapp_number'); // Registrar la opción (ajusta el grupo si es diferente)
    }

    public function render_whatsapp_number_field() {
        $whatsapp_number = get_option('autobid_whatsapp_number', '');
        echo '<input type="text" name="autobid_whatsapp_number" value="' . esc_attr($whatsapp_number) . '" placeholder="+1234567890">';
        echo '<p class="description">Ingresa el número de WhatsApp (con código de país) al que se enviarán las solicitudes de compra. Ej: +1234567890</p>';
    }

    public function render_admin_panel($atts) {
        // Verificación simple de usuario
        if (!is_user_logged_in()) {
            return '<div class="autobid-message error">Acceso denegado. Debes iniciar sesión para ver esta página.</div>';
        }

        // Permitir administradores ó usuarios con capacidad de editar posts (autores/vendedores)
        $current_user = wp_get_current_user();
        if (!current_user_can('edit_posts') && !in_array('administrator', $current_user->roles)) {
            return '<div class="autobid-message error">Acceso denegado. No tienes permisos suficientes para ver esta página.</div>';
        }

        ob_start();
        ?>
        <div class="autobid-admin-frontend-wrap">
            <h1>Panel de Administración - AutoBid Pro</h1>

            <nav class="autobid-admin-tabs">
                <button class="tab-button active" data-tab="dashboard">Dashboard</button>
                <button class="tab-button" data-tab="vehicles">Vehículos</button>
                <button class="tab-button" data-tab="bids">Pujas</button>
                <button class="tab-button" data-tab="sales">Ventas</button>
                <button class="tab-button" data-tab="users">Usuarios</button>
            </nav>

            <div class="autobid-admin-tab-content">
                <div id="tab-dashboard" class="tab-pane active">
                    <h2>Resumen del Sistema</h2>
                    <p>Bienvenido al panel de administración frontend. Desde aquí puedes gestionar vehículos, pujas, ventas y usuarios.</p>
                    <div class="admin-stats-grid">
                        <div class="admin-stat-card">
                            <h3>Total Vehículos</h3>
                            <p id="stat-vehicles-count">Cargando...</p>
                        </div>
                        <div class="admin-stat-card">
                            <h3>Pujas Hoy</h3>
                            <p id="stat-bids-today">Cargando...</p>
                        </div>
                        <div class="admin-stat-card">
                            <h3>Ventas Hoy</h3>
                            <p id="stat-sales-today">Cargando...</p>
                        </div>
                        <div class="admin-stat-card">
                            <h3>Usuarios Registrados</h3>
                            <p id="stat-users-count">Cargando...</p>
                        </div>
                    </div>
                </div>

                <div id="tab-vehicles" class="tab-pane">
                    <h2>Gestión de Vehículos</h2>
                    <button id="create-vehicle-btn" class="admin-action-button">Crear Nuevo Vehículo</button>

                    <div id="vehicle-form-container" style="display:none;">
                        <h3 id="vehicle-form-title">Crear Vehículo</h3>
                        <form id="vehicle-form" enctype="multipart/form-data">
                            <?php wp_nonce_field('vehicle_form_nonce', 'vehicle_form_nonce_field'); ?>
                            <input type="hidden" id="vehicle-id" name="vehicle_id">

                            <label for="vehicle-title">Título:</label>
                            <input type="text" id="vehicle-title" name="title" required>

                            <label for="vehicle-description">Descripción:</label>
                            <textarea id="vehicle-description" name="content"></textarea>

                            <label for="vehicle-type">Tipo:</label>
                            <select id="vehicle-type" name="type" required>
                                <option value="venta">Venta Directa</option>
                                <option value="subasta">Subasta</option>
                            </select>

                            <label for="vehicle-price">Precio:</label>
                            <input type="number" step="0.01" id="vehicle-price" name="price">

                            <label for="vehicle-currency">Moneda:</label>
                            <select id="vehicle-currency" name="currency">
                                <option value="USD">Dólar (USD)</option>
                                <option value="EUR">Euro (EUR)</option>
                                <option value="DOP">Peso Dominicano (DOP)</option>
                                <option value="MXN">Peso Mexicano (MXN)</option>
                                <option value="COP">Peso Colombiano (COP)</option>
                                <option value="PEN">Sol Peruano (PEN)</option>
                            </select>

                            <label for="vehicle-brand">Marca:</label>
                            <input type="text" id="vehicle-brand" name="brand">

                            <label for="vehicle-model">Modelo:</label>
                            <input type="text" id="vehicle-model" name="model">

                            <label for="vehicle-year">Año:</label>
                            <input type="number" id="vehicle-year" name="year">

                            <label for="vehicle-color">Color:</label>
                            <input type="text" id="vehicle-color" name="color">

                            <label for="vehicle-condition">Condición:</label>
                            <select id="vehicle-condition" name="condition">
                                <option value="nuevo">Nuevo</option>
                                <option value="usado">Usado</option>
                            </select>

                            <label for="vehicle-location">Ubicación:</label>
                            <input type="text" id="vehicle-location" name="location">

                            <label for="vehicle-featured">Destacado:</label>
                            <input type="checkbox" id="vehicle-featured" name="featured" value="1">

                            <label for="vehicle-start-time">Fecha Inicio (Subasta):</label>
                            <input type="datetime-local" id="vehicle-start-time" name="start_time">

                            <label for="vehicle-end-time">Fecha Fin (Subasta):</label>
                            <input type="datetime-local" id="vehicle-end-time" name="end_time">
                            <p class="description">Obligatorio solo para subastas.</p>

                            <label for="vehicle-gallery">Galería de Imágenes:</label>
                            <input type="file" id="vehicle-gallery" name="gallery[]" multiple accept="image/*">
                            <div id="gallery-preview-container"></div>

                            <button type="submit" id="submit-vehicle-btn" class="admin-action-button">Guardar Vehículo</button>
                            <button type="button" id="cancel-vehicle-btn" class="admin-action-button secondary">Cancelar</button>
                        </form>
                    </div>

                    <div id="vehicle-list-container">
                        <p id="vehicle-loading-text">Cargando vehículos...</p>
                        <table id="vehicle-list-table" class="admin-data-table" style="display:none;">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Imagen</th>
                                    <th>Título</th>
                                    <th>Tipo</th>
                                    <th>Precio/Actual</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="vehicle-list-body">
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pestañas placeholders -->
                <!-- Nueva pestaña para Pujas -->
                <!-- Nueva sección para Pujas MEJORADA -->
                <div id="tab-bids" class="tab-pane">
                    <h2>Gestión de Pujas</h2>
                    <p>Lista de todas las pujas realizadas por los usuarios.</p>
                    <div id="bids-list-container">
                        <table id="bids-list-table" class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Vehículo</th>
                                    <th>Usuario</th>
                                    <th>Email</th>
                                    <th>Monto</th>
                                    <th>Fecha</th>
                                    <th>Ganadora</th> <!-- Nueva columna -->
                                </tr>
                            </thead>
                            <tbody id="bids-list-body">
                                <!-- Filas cargadas dinámicamente -->
                                <tr><td colspan="7"><p>Cargando pujas...</p></td></tr> <!-- Actualizado colspan -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Fin Nueva sección para Pujas MEJORADA -->
                <!-- Fin Nueva pestaña para Pujas -->

                <!-- Nueva pestaña para Ventas -->
                <div id="tab-sales" class="tab-pane">
                    <h2>Gestión de Ventas</h2>
                    <p>Vehículos vendidos (ventas directas).</p>                   

                    <h3>Ventas Directas</h3>
                    <div id="direct-sales-list-container">
                        <table id="direct-sales-list-table" class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Imagen</th>
                                    <th>Título</th>
                                    <th>Precio</th>
                                    <th>Moneda</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody id="direct-sales-list-body">
                                <!-- Filas cargadas dinámicamente -->
                                <tr><td colspan="6"><p>Cargando ventas directas...</p></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Fin Nueva pestaña para Ventas -->


                <div id="tab-users" class="tab-pane">
                    <h2>Gestión de Usuarios</h2>
                    <div id="users-list-container"><p>Lista de usuarios cargada dinámicamente aquí...</p></div>
                </div>
            </div>
        </div>

        <style>
            /* --- Mantengo un CSS completo y detallado (versión extensa) --- */
            :root {
                --primary: #1e3c72;
                --primary-dark: #162b50;
                --light: #ffffff;
                --light-bg: #f8f9fa;
                --border: #dee2e6;
                --gray: #6c757d;
                --dark: #212529;
                --radius: 10px;
                --radius-sm: 8px;
                --shadow: 0 4px 12px rgba(0,0,0,0.08);
            }
            .autobid-admin-frontend-wrap {
                max-width: 1400px;
                margin: 2rem auto;
                padding: 0 1.5rem 2rem 1.5rem;
                background-color: var(--light);
                border-radius: var(--radius);
                box-shadow: var(--shadow);
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif;
            }
            .autobid-admin-frontend-wrap h1 {
                color: var(--primary);
                padding: 1.5rem 0;
                border-bottom: 2px solid var(--primary);
                margin: 0;
            }
            .autobid-admin-tabs {
                display: flex;
                overflow-x: auto;
                border-bottom: 1px solid var(--border);
                padding: 0 1rem;
                margin-top: 1rem;
            }
            .tab-button {
                background: none;
                border: none;
                padding: 1rem 1.5rem;
                margin: 0 0.2rem;
                cursor: pointer;
                font-size: 1rem;
                font-weight: 600;
                color: var(--gray);
                border-bottom: 3px solid transparent;
                white-space: nowrap;
            }
            .tab-button.active {
                color: var(--primary);
                border-bottom-color: var(--primary);
            }
            .tab-pane { display: none; padding: 2rem 1.5rem; }
            .tab-pane.active { display: block; }
            .admin-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin: 1rem 0 2rem; }
            .admin-stat-card { background: var(--light-bg); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 1.5rem; text-align: center; }
            .admin-stat-card h3 { margin: 0 0 1rem; color: var(--primary); font-size: 1.1rem; }
            .admin-stat-card p { font-size: 1.8rem; font-weight: bold; color: var(--primary-dark); margin: 0; }

            .admin-action-button { background: var(--primary); color: white; border: none; padding: 0.8rem 1.5rem; font-size: 1rem; font-weight: 600; border-radius: var(--radius-sm); cursor: pointer; margin-bottom: 1rem; }
            .admin-action-button.secondary { background: #6c757d; }
            .admin-action-button:hover { background: var(--primary-dark); }

            #vehicle-form-container { background: var(--light-bg); padding: 1.5rem; border-radius: var(--radius-sm); margin: 1rem 0 2rem; border: 1px solid var(--border); }
            #vehicle-form label { display: block; margin-top: 1rem; font-weight: 600; color: var(--dark); }
            #vehicle-form input[type="text"], #vehicle-form input[type="number"], #vehicle-form input[type="datetime-local"], #vehicle-form select, #vehicle-form textarea { width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 1rem; }
            #vehicle-form textarea { min-height: 120px; resize: vertical; }

            #gallery-preview-container { display:flex; flex-wrap:wrap; gap:10px; margin-top:10px; }
            #gallery-preview-container img { width:100px; height:75px; object-fit:cover; border-radius:4px; border:1px solid var(--border); }

            .admin-data-table { width:100%; border-collapse: collapse; margin-top: 1rem; }
            .admin-data-table th, .admin-data-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border); vertical-align: middle; }
            .admin-data-table th { background-color: var(--light-bg); font-weight: 600; }
            .vehicle-thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border); }

            .autobid-message { padding: 1rem; margin: 1rem 0; border-radius: var(--radius-sm); text-align: center; font-weight: 500; }
            .autobid-message.error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
            .autobid-message.success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }

            .description { font-size: 0.85rem; color: #6c757d; margin-top: 0.2rem; font-style: italic; }
            @media (max-width: 800px) {
                .admin-stats-grid { grid-template-columns: 1fr; }
                .tab-button { padding: 0.75rem 1rem; }
            }

            #bids-list-table img.vehicle-thumb,
            #sold-auctions-list-table img.vehicle-thumb,
            #direct-sales-list-table img.vehicle-thumb {
                width: 60px;
                height: 60px;
                object-fit: cover;
                border-radius: 4px;
                border: 1px solid var(--border, #dee2e6);
            }
            #bids-list-table th,
            #bids-list-table td,
            #sold-auctions-list-table th,
            #sold-auctions-list-table td,
            #direct-sales-list-table th,
            #direct-sales-list-table td {
                padding: 0.75rem;
                text-align: left;
                border-bottom: 1px solid var(--border, #dee2e6);
            }
            #bids-list-table th,
            #sold-auctions-list-table th,
            #direct-sales-list-table th {
                background-color: var(--light-bg, #f8f9fa);
                font-weight: 600;
            }
             #bids-list-table th,
            #bids-list-table td {
                padding: 0.75rem;
                text-align: left;
                border-bottom: 1px solid var(--border, #dee2e6);
            }
             
            .bid-winner-badge {
                display: inline-block;
                padding: 0.2rem 0.5rem;
                border-radius: 4px;
                font-size: 0.8rem;
                font-weight: bold;
                text-transform: uppercase;
                color: white;
                background: #27ae60; /* Verde para ganadora */
            }
            .bid-not-winner {
                color: #95a5a6; /* Gris para no ganadora */
                font-style: italic;
            }
            /* Mensajes de error/info */
            .autobid-message {
                padding: 1rem;
                margin: 1rem 0;
                border-radius: var(--radius-sm, 6px);
                text-align: center;
                font-weight: 500;
            }
            .autobid-message.error {
                background: #ffebee;
                color: #c62828;
                border: 1px solid #ffcdd2;
            }
            .autobid-message.success {
                background: #e8f5e9;
                color: #2e7d32;
                border: 1px solid #c8e6c9;
            }
        </style>

        <script>
        (function() {
            const API_BASE_URL = '<?php echo esc_url(rest_url("autobid/v1/vehicles")); ?>';            
            const API_BIDS_URL = '<?php echo esc_url(rest_url("autobid/v1/bids")); ?>'; // Nueva URL para pujas
            const API_SALES_URL = '<?php echo esc_url(rest_url("autobid/v1/sales")); ?>'; // Nueva URL para ventas
            const API_NONCE = '<?php echo wp_create_nonce("wp_rest"); ?>';

            // Helper: intenta normalizar respuesta en array de vehículos
            function normalizeVehiclesResponse(payload) {
                // payload puede ser:
                // 1) un array de vehículos
                // 2) un objeto { data: [...] }
                // 3) un objeto { vehicles: [...] }
                // 4) un objeto simple vehicle (cuando se pide un id)
                if (!payload) return [];
                if (Array.isArray(payload)) return payload;
                if (payload.data && Array.isArray(payload.data)) return payload.data;
                if (payload.vehicles && Array.isArray(payload.vehicles)) return payload.vehicles;
                // algunas respuestas WP REST encapsulan en { success: true, data: [...] }
                if (payload.success && payload.data && Array.isArray(payload.data)) return payload.data;
                // si el objeto parece ser un solo vehículo (tiene id o ID), envolverlo en array
                if (payload.id || payload.ID || payload.ID === 0) return [payload];
                // como fallback, intentar tomar las propiedades enumerables y convertir a array si parecen vehículos
                // si no se detecta nada, retornar array vacío
                return [];
            }

            document.addEventListener('DOMContentLoaded', function() {
                const tabButtons = document.querySelectorAll('.tab-button');
                const tabPanes = document.querySelectorAll('.tab-pane');

                const vehicleListBody = document.getElementById('vehicle-list-body');
                const vehicleListContainer = document.getElementById('vehicle-list-container');
                const vehicleLoadingText = document.getElementById('vehicle-loading-text');
                const vehicleFormContainer = document.getElementById('vehicle-form-container');
                const vehicleForm = document.getElementById('vehicle-form');
                const createVehicleBtn = document.getElementById('create-vehicle-btn');
                const cancelVehicleBtn = document.getElementById('cancel-vehicle-btn');
                const galleryInput = document.getElementById('vehicle-gallery');
                const galleryPreviewContainer = document.getElementById('gallery-preview-container');
                const vehicleTable = document.getElementById('vehicle-list-table');

                // Tabs click
                tabButtons.forEach(button => {
                    button.addEventListener('click', () => {
                        tabButtons.forEach(btn => btn.classList.remove('active'));
                        tabPanes.forEach(pane => pane.classList.remove('active'));
                        button.classList.add('active');
                        const targetPaneId = 'tab-' + button.dataset.tab;
                        document.getElementById(targetPaneId).classList.add('active');
                        // Cargar contenido específico
                        if (button.dataset.tab === 'vehicles') {
                            loadVehiclesList();
                        } else if (button.dataset.tab === 'dashboard') {
                            loadDashboardStats();
                        } else if (button.dataset.tab === 'bids') {
                            loadBidsList();
                        } else if (button.dataset.tab === 'sales') {
                            loadSalesList();
                        }
                        
                    });
                });

                // Dashboard dummy
                async function loadDashboardStats() {
                    try {
                        // Obtener estadísticas básicas
                        const statsResponse = await fetch(`${API_BASE_URL}/stats`, {
                            headers: {
                                'X-WP-Nonce': API_NONCE // <-- Usar la variable definida
                            }
                        });

                        if (!statsResponse.ok) {
                            const errorText = await statsResponse.text(); // Obtener el cuerpo de la respuesta para ver posibles errores PHP
                            console.error('Error de red o servidor al cargar estadísticas:', statsResponse.status, errorText);
                            throw new Error(`Error de red: ${statsResponse.status} - ${errorText}`);
                        }

                        const stats = await statsResponse.json();

                        // Actualizar las tarjetas del dashboard con los datos obtenidos
                        document.getElementById('stat-vehicles-count').textContent = stats.total_vehicles || '0';
                        document.getElementById('stat-bids-today').textContent = stats.bids_today || '0';
                        document.getElementById('stat-sales-today').textContent = stats.sales_today || '0';
                        document.getElementById('stat-users-count').textContent = stats.total_users || '0';

                    } catch (error) {
                        console.error('Error al cargar estadísticas del dashboard:', error);
                        // Mostrar error en las tarjetas
                        document.getElementById('stat-vehicles-count').textContent = 'Error';
                        document.getElementById('stat-bids-today').textContent = 'Error';
                        document.getElementById('stat-sales-today').textContent = 'Error';
                        document.getElementById('stat-users-count').textContent = 'Error';
                    }
                }

                    // --- Nueva función: Cargar lista de Pujas ---
                async function loadBidsList() {
                    const bidsListBody = document.getElementById('bids-list-body');
                    if (!bidsListBody) return;

                    bidsListBody.innerHTML = '<tr><td colspan="6"><p>Cargando pujas...</p></td></tr>'; // Indicador de carga

                    try {
                        const response = await fetch(API_BIDS_URL, {
                            headers: {
                                'X-WP-Nonce': API_NONCE
                            }
                        });

                        if (!response.ok) {
                            const errorText = await response.text();
                            console.error('Error de red o servidor al cargar pujas:', response.status, errorText);
                            throw new Error(`Error de red: ${response.status} - ${errorText}`);
                        }

                        const bids = await response.json();
                        bidsListBody.innerHTML = ''; // Limpiar lista actual

                        if (bids.length === 0) {
                            bidsListBody.innerHTML = '<tr><td colspan="6"><p>No se encontraron pujas.</p></td></tr>';
                            return;
                        }

                        bids.forEach(bid => {
                            const row = document.createElement('tr');
                            // Asegurar valores por defecto
                            const safeVehicleName = bid.vehicle_name || 'Vehículo no encontrado';
                            const safeUserName = bid.user_name || 'Usuario no encontrado';
                            const safeUserEmail = bid.user_email || 'N/A';
                            const safeBidAmount = bid.bid_amount ? parseFloat(bid.bid_amount).toFixed(2) : 'N/A';
                            const safeCreatedAt = bid.created_at ? new Date(bid.created_at).toLocaleString() : 'N/A';

                            row.innerHTML = `
                                <td>${bid.id || 'N/A'}</td>
                                <td>${safeVehicleName}</td>
                                <td>${safeUserName}</td>
                                <td>${safeUserEmail}</td>
                                <td>$${safeBidAmount}</td>
                                <td>${safeCreatedAt}</td>
                            `;
                            bidsListBody.appendChild(row);
                        });

                    } catch (error) {
                        console.error('Error al cargar la lista de pujas:', error);
                        bidsListBody.innerHTML = `<tr><td colspan="6"><p class="error">❌ ${error.message}</p></td></tr>`;
                    }
                }
                // --- Fin Nueva función ---

                // --- Nueva función: Cargar lista de Ventas ---
                async function loadSalesList() {
                    const soldAuctionsListBody = document.getElementById('sold-auctions-list-body');
                    const directSalesListBody = document.getElementById('direct-sales-list-body');
                    if (!soldAuctionsListBody || !directSalesListBody) return;

                    // Indicadores de carga
                    soldAuctionsListBody.innerHTML = '<tr><td colspan="7"><p>Cargando subastas vendidas...</p></td></tr>';
                    directSalesListBody.innerHTML = '<tr><td colspan="6"><p>Cargando ventas directas...</p></td></tr>';

                    try {
                        const response = await fetch(API_SALES_URL, {
                            headers: {
                                'X-WP-Nonce': API_NONCE
                            }
                        });

                        if (!response.ok) {
                            const errorText = await response.text();
                            console.error('Error de red o servidor al cargar ventas:', response.status, errorText);
                            throw new Error(`Error de red: ${response.status} - ${errorText}`);
                        }

                        const salesData = await response.json();
                        
                        // --- Cargar Subastas Vendidas ---
                        const soldAuctions = salesData.sold_auctions || [];
                        soldAuctionsListBody.innerHTML = ''; // Limpiar lista actual

                        if (soldAuctions.length === 0) {
                            soldAuctionsListBody.innerHTML = '<tr><td colspan="7"><p>No se encontraron subastas vendidas.</p></td></tr>';
                        } else {
                            soldAuctions.forEach(vehicle => {
                                const row = document.createElement('tr');
                                // Asegurar valores por defecto
                                const safeImage = vehicle.image || 'https://placehold.co/600x400';
                                const safeName = vehicle.name || 'Nombre no disponible';
                                const safeFinalBid = vehicle.final_bid ? parseFloat(vehicle.final_bid).toFixed(2) : 'N/A';
                                const safeWinnerName = vehicle.winner && vehicle.winner.name ? vehicle.winner.name : 'N/A';
                                const safeWinnerEmail = vehicle.winner && vehicle.winner.email ? vehicle.winner.email : 'N/A';
                                const safeEndTime = vehicle.end_time ? new Date(vehicle.end_time).toLocaleString() : 'N/A';

                                row.innerHTML = `
                                    <td>${vehicle.id || 'N/A'}</td>
                                    <td><img src="${safeImage}" alt="${safeName}" class="vehicle-thumb" onerror="this.src='https://placehold.co/600x400';"/></td>
                                    <td>${safeName}</td>
                                    <td>$${safeFinalBid}</td>
                                    <td>${safeWinnerName}</td>
                                    <td>${safeWinnerEmail}</td>
                                    <td>${safeEndTime}</td>
                                `;
                                soldAuctionsListBody.appendChild(row);
                            });
                        }
                        // --- Fin Cargar Subastas Vendidas ---

                        // --- Cargar Ventas Directas ---
                        const directSales = salesData.direct_sales || [];
                        directSalesListBody.innerHTML = ''; // Limpiar lista actual

                        if (directSales.length === 0) {
                            directSalesListBody.innerHTML = '<tr><td colspan="6"><p>No se encontraron ventas directas.</p></td></tr>';
                        } else {
                            directSales.forEach(vehicle => {
                                const row = document.createElement('tr');
                                // Asegurar valores por defecto
                                const safeImage = vehicle.image || 'https://placehold.co/600x400';
                                const safeName = vehicle.name || 'Nombre no disponible';
                                const safePrice = vehicle.price ? parseFloat(vehicle.price).toFixed(2) : 'N/A';
                                const safeCurrency = vehicle.currency || 'USD';
                                const safeDate = vehicle.date ? new Date(vehicle.date).toLocaleString() : new Date(vehicle.post_date).toLocaleString();

                                row.innerHTML = `
                                    <td>${vehicle.id || 'N/A'}</td>
                                    <td><img src="${safeImage}" alt="${safeName}" class="vehicle-thumb" onerror="this.src='https://placehold.co/600x400';"/></td>
                                    <td>${safeName}</td>
                                    <td>$${safePrice}</td>
                                    <td>${safeCurrency}</td>
                                    <td>${safeDate}</td>
                                `;
                                directSalesListBody.appendChild(row);
                            });
                        }
                        // --- Fin Cargar Ventas Directas ---

                    } catch (error) {
                        console.error('Error al cargar la lista de ventas:', error);
                        soldAuctionsListBody.innerHTML = `<tr><td colspan="7"><p class="error">❌ Error al cargar subastas vendidas: ${error.message}</p></td></tr>`;
                        directSalesListBody.innerHTML = `<tr><td colspan="6"><p class="error">❌ Error al cargar ventas directas: ${error.message}</p></td></tr>`;
                    }
                }
                // --- Fin Nueva función ---

                // Gallery preview
                galleryInput.addEventListener('change', function(e) {
                    galleryPreviewContainer.innerHTML = '';
                    const files = e.target.files;
                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        if (!file.type.startsWith('image/')) continue;
                        const reader = new FileReader();
                        reader.onload = function(ev) {
                            const img = document.createElement('img');
                            img.src = ev.target.result;
                            galleryPreviewContainer.appendChild(img);
                        }
                        reader.readAsDataURL(file);
                    }
                });

                // LOAD VEHICLES
                async function loadVehiclesList() {
                    vehicleTable.style.display = 'none';
                    vehicleLoadingText.style.display = 'block';
                    vehicleLoadingText.textContent = 'Cargando vehículos...';
                    try {
                        const response = await fetch(API_BASE_URL, {
                            method: 'GET',
                            headers: {
                                'X-WP-Nonce': API_NONCE,
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin' // importante para cookies/wp-auth si aplica
                        });

                        const textBody = await response.text();
                        let payload;
                        try {
                            payload = textBody ? JSON.parse(textBody) : null;
                        } catch (e) {
                            console.warn('Respuesta no JSON al listar vehículos. Texto recibido:', textBody);
                            throw new Error('Respuesta del servidor no es JSON. Revisa el log.');
                        }

                        if (!response.ok) {
                            console.error('Error HTTP al obtener vehículos:', response.status, payload);
                            const message = (payload && payload.message) ? payload.message : ('HTTP ' + response.status);
                            throw new Error(message);
                        }

                        const vehicles = normalizeVehiclesResponse(payload);

                        // Debug: mostrar en consola la forma original y la normalizada
                        console.log('API response raw:', payload);
                        console.log('Vehicles normalizados:', vehicles);

                        if (!Array.isArray(vehicles) || vehicles.length === 0) {
                            vehicleLoadingText.textContent = 'No se encontraron vehículos.';
                            vehicleListBody.innerHTML = '';
                            vehicleTable.style.display = 'none';
                            return;
                        }

                        // Construir filas
                        vehicleListBody.innerHTML = '';
                        vehicles.forEach(vehicle => {
                            // Soportar distintos nombres de campos (id / ID / post_id)
                            const vid = vehicle.id ?? vehicle.ID ?? vehicle.post_id ?? '';
                            const image = vehicle.image ?? vehicle.thumbnail ?? (vehicle.gallery && vehicle.gallery[0]) ?? '';
                            const title = vehicle.name ?? vehicle.title ?? vehicle.post_title ?? '';
                            const type = vehicle.type ?? vehicle._type ?? 'venta';
                            const currency = vehicle.currency ?? vehicle._currency ?? 'USD';
                            const price = vehicle.price ?? vehicle._price ?? vehicle.current_bid ?? 'N/A';

                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${vid}</td>
                                <td><img src="${image || 'https://placehold.co/600x400'}" class="vehicle-thumb" onerror="this.src='https://placehold.co/600x400'"></td>
                                <td>${escapeHtml(title)}</td>
                                <td>${escapeHtml(type)}</td>
                                <td>${escapeHtml(currency)} ${escapeHtml(price)}</td>
                                <td>
                                    <button class="edit-vehicle-btn" data-id="${vid}">Editar</button>
                                    <button class="delete-vehicle-btn" data-id="${vid}">Eliminar</button>
                                </td>
                            `;
                            vehicleListBody.appendChild(tr);
                        });

                        // Delegación de eventos (más seguro si se recargan filas)
                        document.querySelectorAll('.edit-vehicle-btn').forEach(btn => {
                            btn.addEventListener('click', (e) => {
                                const id = e.currentTarget.dataset.id;
                                if (id) loadVehicleForEdit(id);
                            });
                        });
                        document.querySelectorAll('.delete-vehicle-btn').forEach(btn => {
                            btn.addEventListener('click', (e) => {
                                const id = e.currentTarget.dataset.id;
                                if (id && confirm('¿Estás seguro de que deseas eliminar este vehículo?')) {
                                    deleteVehicle(id);
                                }
                            });
                        });

                        vehicleLoadingText.style.display = 'none';
                        vehicleTable.style.display = 'table';
                    } catch (err) {
                        console.error('Error cargando vehículos:', err);
                        vehicleLoadingText.innerHTML = `<span class="autobid-message error">Error al cargar vehículos: ${escapeHtml(err.message)}</span>`;
                    }
                }

                // LOAD A SINGLE VEHICLE FOR EDIT
                async function loadVehicleForEdit(id) {
                    vehicleFormContainer.style.display = 'none';
                    try {
                        const response = await fetch(`${API_BASE_URL}/${id}`, {
                            method: 'GET',
                            headers: { 'X-WP-Nonce': API_NONCE, 'Accept': 'application/json' },
                            credentials: 'same-origin'
                        });

                        const textBody = await response.text();
                        let payload;
                        try {
                            payload = textBody ? JSON.parse(textBody) : null;
                        } catch (e) {
                            console.warn('Respuesta no JSON al obtener vehículo:', textBody);
                            throw new Error('Respuesta del servidor no es JSON.');
                        }

                        if (!response.ok) {
                            console.error('Error HTTP al obtener vehículo:', response.status, payload);
                            const message = (payload && payload.message) ? payload.message : ('HTTP ' + response.status);
                            throw new Error(message);
                        }

                        // Si la respuesta viene integrada (p.ej. { success:true, vehicle: {...} } )
                        let vehicle = null;
                        if (payload) {
                            if (payload.vehicle) vehicle = payload.vehicle;
                            else if (payload.data && (payload.data.id || payload.data.ID)) vehicle = payload.data;
                            else if (payload.id || payload.ID) vehicle = payload;
                            else if (payload.length === 1 && payload[0].id) vehicle = payload[0];
                            else vehicle = payload; // fallback
                        }

                        if (!vehicle) throw new Error('Vehículo no encontrado en la respuesta.');

                        // Mapear campos defensivamente
                        const vid = vehicle.id ?? vehicle.ID ?? vehicle.post_id ?? '';
                        document.getElementById('vehicle-id').value = vid;
                        document.getElementById('vehicle-title').value = vehicle.name ?? vehicle.title ?? vehicle.post_title ?? '';
                        document.getElementById('vehicle-description').value = vehicle.description ?? vehicle.content ?? vehicle.post_content ?? '';
                        document.getElementById('vehicle-type').value = vehicle.type ?? vehicle._type ?? 'venta';
                        document.getElementById('vehicle-price').value = vehicle.price ?? vehicle._price ?? '';
                        document.getElementById('vehicle-currency').value = vehicle.currency ?? vehicle._currency ?? 'USD';
                        document.getElementById('vehicle-brand').value = vehicle.brand ?? '';
                        document.getElementById('vehicle-model').value = vehicle.model ?? '';
                        document.getElementById('vehicle-year').value = vehicle.year ?? '';
                        document.getElementById('vehicle-color').value = vehicle.color ?? '';
                        document.getElementById('vehicle-condition').value = vehicle.condition ?? 'usado';
                        document.getElementById('vehicle-location').value = vehicle.location ?? '';
                        document.getElementById('vehicle-featured').checked = (vehicle.featured === '1' || vehicle.featured === 1 || vehicle._featured === '1');

                        // Fechas (si vienen como "YYYY-mm-dd HH:ii:ss")
                        const formatDateForInput = (dateStr) => {
                            if (!dateStr) return '';
                            const d = new Date(dateStr);
                            if (isNaN(d.getTime())) return '';
                            const pad = n => n.toString().padStart(2, '0');
                            return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
                        };
                        document.getElementById('vehicle-start-time').value = formatDateForInput(vehicle.start_time ?? vehicle._start_time ?? '');
                        document.getElementById('vehicle-end-time').value = formatDateForInput(vehicle.end_time ?? vehicle._end_time ?? '');

                        // Gallery preview
                        galleryPreviewContainer.innerHTML = '';
                        const gallery = vehicle.gallery ?? vehicle._gallery ?? vehicle.images ?? null;
                        if (Array.isArray(gallery)) {
                            gallery.forEach(u => {
                                const img = document.createElement('img');
                                img.src = u;
                                img.onerror = () => img.src = 'https://placehold.co/600x400';
                                galleryPreviewContainer.appendChild(img);
                            });
                        } else if (typeof gallery === 'string' && gallery.length) {
                            const img = document.createElement('img');
                            img.src = gallery;
                            img.onerror = () => img.src = 'https://placehold.co/600x400';
                            galleryPreviewContainer.appendChild(img);
                        }

                        vehicleFormContainer.style.display = 'block';

                    } catch (err) {
                        console.error('Error al cargar vehículo para editar:', err);
                        alert('Error al cargar el vehículo para editar: ' + err.message);
                    }
                }

                // SUBMIT (create/update) with POST + X-HTTP-Method-Override for PUT (compatibilidad FormData)
                vehicleForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(vehicleForm);
                    const id = document.getElementById('vehicle-id').value;
                    let url = API_BASE_URL;
                    let method = 'POST';
                    const headers = { 'X-WP-Nonce': API_NONCE };

                    if (id) {
                        url = `${API_BASE_URL}/${id}`;
                        // Usamos override para forzar PUT desde FormData
                        headers['X-HTTP-Method-Override'] = 'PUT';
                    }

                    try {
                        const res = await fetch(url, {
                            method,
                            headers,
                            body: formData,
                            credentials: 'same-origin'
                        });

                        const textBody = await res.text();
                        let payload;
                        try { payload = textBody ? JSON.parse(textBody) : null; } catch (err) { console.warn('Respuesta no JSON al guardar:', textBody); throw new Error('Respuesta inesperada del servidor. Revisa el log.'); }

                        if (!res.ok) {
                            console.error('Error guardando vehículo:', res.status, payload);
                            throw new Error((payload && payload.message) ? payload.message : 'Error al guardar vehiculo');
                        }

                        // Éxito
                        alert(payload.message ?? 'Vehículo guardado correctamente.');
                        vehicleFormContainer.style.display = 'none';
                        loadVehiclesList();

                    } catch (err) {
                        console.error('Error en submit vehicle:', err);
                        alert('Error al guardar el vehículo: ' + err.message);
                    }
                });

                // DELETE
                async function deleteVehicle(id) {
                    if (!confirm('¿Eliminar este vehículo?')) return;
                    try {
                        const res = await fetch(`${API_BASE_URL}/${id}`, {
                            method: 'DELETE',
                            headers: { 'X-WP-Nonce': API_NONCE, 'Accept': 'application/json' },
                            credentials: 'same-origin'
                        });
                        const textBody = await res.text();
                        let payload;
                        try { payload = textBody ? JSON.parse(textBody) : null; } catch (err) { throw new Error('Respuesta no JSON al eliminar'); }

                        if (!res.ok) {
                            console.error('Error eliminando vehículo:', res.status, payload);
                            throw new Error((payload && payload.message) ? payload.message : 'Error al eliminar');
                        }

                        alert(payload.message ?? 'Vehículo eliminado.');
                        loadVehiclesList();
                    } catch (err) {
                        console.error('Error eliminando vehículo:', err);
                        alert('Error al eliminar: ' + err.message);
                    }
                }

                // Create button
                createVehicleBtn.addEventListener('click', () => {
                    vehicleForm.reset();
                    document.getElementById('vehicle-id').value = '';
                    galleryPreviewContainer.innerHTML = '';
                    vehicleFormContainer.style.display = 'block';
                });

                cancelVehicleBtn.addEventListener('click', () => {
                    vehicleFormContainer.style.display = 'none';
                });

                // safe html escape minimal
                function escapeHtml(str) {
                    if (str === null || str === undefined) return '';
                    return String(str).replace(/[&<>"'`=\/]/g, function(s) {
                        return ({
                            '&': '&amp;',
                            '<': '&lt;',
                            '>': '&gt;',
                            '"': '&quot;',
                            "'": '&#39;',
                            '/': '&#x2F;',
                            '`': '&#x60;',
                            '=': '&#x3D;'
                        })[s];
                    });
                }
                // Cargar contenido inicial del dashboard
                loadDashboardStats();
                // Cargar la lista inicial cuando el usuario abra el panel (o por defecto)
                // Si la pestaña activa al cargar es vehicles, cargar; si no se cargará al activarla.
                const activeTab = document.querySelector('.tab-button.active');
                if (activeTab && activeTab.dataset.tab === 'vehicles') {
                    loadVehiclesList();
                }

            }); // DOMContentLoaded

            
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
