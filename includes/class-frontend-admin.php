<?php
// includes/class-frontend-admin.php

if (!defined('ABSPATH')) {
    exit; // Salir si se accede directamente
}

class AutoBid_Frontend_Admin {

    public function __construct() {
        add_action('init', [$this, 'create_admin_panel_page']);
        add_shortcode('autobid_admin_panel', [$this, 'render_admin_panel']);
    }

    public function create_admin_panel_page() {
        $admin_panel_page = get_page_by_title('Panel de Administración');
        if (!$admin_panel_page) {
            $admin_panel_id = wp_insert_post([
                'post_title'   => 'Panel de Administración',
                'post_content' => '[autobid_admin_panel]', // Shortcode del panel
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => 'admin-panel' // Slug amigable
            ]);
        } else {
            $admin_panel_id = $admin_panel_page->ID;
        }
        update_option('autobid_admin_panel_page_id', $admin_panel_id);
    }

    public function render_admin_panel($atts) {
        // Verificar si el usuario está logueado
        if (!is_user_logged_in()) {
            return '<div class="autobid-message error">Acceso denegado. Debes iniciar sesión para ver esta página.</div>';
        }

        // Verificar si el usuario tiene el rol de 'administrator'
        $current_user = wp_get_current_user();
        if (!in_array('administrator', $current_user->roles)) {
            return '<div class="autobid-message error">Acceso denegado. No tienes permisos suficientes para ver esta página.</div>';
        }

        // Si pasa las verificaciones, renderizar el panel
        ob_start();
        ?>
        <div class="autobid-admin-frontend-wrap">
            <h1>Panel de Administración - AutoBid Pro</h1>
            <nav class="autobid-admin-tabs">
                <button class="tab-button active" data-tab="dashboard">Dashboard</button>
                <button class="tab-button" data-tab="vehicles">Vehículos</button>
                <button class="tab-button" data-tab="bids">Pujas</button>
                <button class="tab-button" data-tab="sales">Ventas</button>
                <button class="tab-button" data-tab="users">Usuarios</button> <!-- Opcional -->
            </nav>

            <div class="autobid-admin-tab-content">
                <div id="tab-dashboard" class="tab-pane active">
                    <h2>Resumen del Sistema</h2>
                    <p>Bienvenido al panel de administración frontend. Desde aquí puedes gestionar vehículos, pujas, ventas y usuarios.</p>
                    <div class="admin-stats-grid">
                        <!-- Aquí se pueden cargar estadísticas dinámicamente con JS -->
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
                    <p>Lista de vehículos y herramientas para crear/editar/eliminar.</p>
                    <!-- Contenido dinámico aquí -->
                    <button id="create-vehicle-btn" class="admin-action-button">Crear Nuevo Vehículo</button>
                    <div id="vehicle-list-container">
                        <!-- Lista de vehículos cargada via JS -->
                    </div>
                </div>

                <div id="tab-bids" class="tab-pane">
                    <h2>Gestión de Pujas</h2>
                    <p>Lista de todas las pujas realizadas.</p>
                    <!-- Contenido dinámico aquí -->
                    <div id="bids-list-container">
                        <!-- Lista de pujas cargada via JS -->
                    </div>
                </div>

                <div id="tab-sales" class="tab-pane">
                    <h2>Gestión de Ventas</h2>
                    <p>Vehículos vendidos (subastas finalizadas y ventas directas).</p>
                    <!-- Contenido dinámico aquí -->
                    <div id="sales-list-container">
                        <!-- Lista de ventas cargada via JS -->
                    </div>
                </div>

                 <div id="tab-users" class="tab-pane">
                    <h2>Gestión de Usuarios</h2>
                    <p>Lista de usuarios registrados (opcional).</p>
                    <!-- Contenido dinámico aquí -->
                    <div id="users-list-container">
                        <!-- Lista de usuarios cargada via JS -->
                    </div>
                </div>
            </div>
        </div>

        <style>
            .autobid-admin-frontend-wrap {
                max-width: 1400px;
                margin: 2rem auto;
                padding: 0 1.5rem;
                background-color: var(--light, #ffffff);
                border-radius: var(--radius, 10px);
                box-shadow: var(--shadow, 0 4px 12px rgba(0,0,0,0.08));
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif;
            }
            .autobid-admin-frontend-wrap h1 {
                color: var(--primary, #1e3c72);
                padding: 1.5rem 0;
                border-bottom: 2px solid var(--primary, #1e3c72);
            }
            .autobid-admin-tabs {
                display: flex;
                overflow-x: auto;
                border-bottom: 1px solid var(--border, #dee2e6);
                padding: 0 1rem;
            }
            .tab-button {
                background: none;
                border: none;
                padding: 1rem 1.5rem;
                margin: 0 0.2rem;
                cursor: pointer;
                font-size: 1rem;
                font-weight: 600;
                color: var(--gray, #6c757d);
                border-bottom: 3px solid transparent;
                white-space: nowrap;
            }
            .tab-button:hover {
                color: var(--primary, #1e3c72);
            }
            .tab-button.active {
                color: var(--primary, #1e3c72);
                border-bottom-color: var(--primary, #1e3c72);
            }
            .tab-pane {
                display: none;
                padding: 2rem 1.5rem;
            }
            .tab-pane.active {
                display: block;
            }
            .admin-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1.5rem;
                margin: 2rem 0;
            }
            .admin-stat-card {
                background: var(--light-bg, #f8f9fa);
                border: 1px solid var(--border, #dee2e6);
                border-radius: var(--radius-sm, 8px);
                padding: 1.5rem;
                text-align: center;
            }
            .admin-stat-card h3 {
                margin: 0 0 1rem;
                color: var(--primary, #1e3c72);
                font-size: 1.1rem;
            }
            .admin-stat-card p {
                font-size: 1.8rem;
                font-weight: bold;
                color: var(--secondary, #2a5298);
                margin: 0;
            }
            .admin-action-button {
                background: var(--primary, #1e3c72);
                color: white;
                border: none;
                padding: 0.8rem 1.5rem;
                font-size: 1rem;
                font-weight: 600;
                border-radius: var(--radius-sm, 6px);
                cursor: pointer;
                margin-bottom: 1.5rem;
            }
            .admin-action-button:hover {
                background: var(--primary-dark, #162b50);
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
            document.addEventListener('DOMContentLoaded', function() {
                const tabButtons = document.querySelectorAll('.tab-button');
                const tabPanes = document.querySelectorAll('.tab-pane');

                // Manejar clics en pestañas
                tabButtons.forEach(button => {
                    button.addEventListener('click', () => {
                        // Remover activo de todos
                        tabButtons.forEach(btn => btn.classList.remove('active'));
                        tabPanes.forEach(pane => pane.classList.remove('active'));
                        // Añadir activo al clickeado
                        button.classList.add('active');
                        const targetPaneId = 'tab-' + button.dataset.tab;
                        document.getElementById(targetPaneId).classList.add('active');
                        // Opcional: Cargar contenido específico para la pestaña activada
                        loadTabContent(button.dataset.tab);
                    });
                });

                // Función para cargar contenido de pestaña (simulado por ahora)
                function loadTabContent(tabName) {
                    console.log("Cargando contenido para pestaña:", tabName);
                    // Aquí se llamaría a la API o se renderizaría contenido dinámico
                    // Por ejemplo, para la pestaña de vehículos:
                    if (tabName === 'vehicles') {
                        loadVehiclesList();
                    } else if (tabName === 'bids') {
                        loadBidsList();
                    } else if (tabName === 'sales') {
                        loadSalesList();
                    } else if (tabName === 'users') {
                         loadUsersList();
                    } else if (tabName === 'dashboard') {
                         loadDashboardStats();
                    }
                }

                // Simular carga de estadísticas en el dashboard
                function loadDashboardStats() {
                     // Estos valores se obtendrían de la API
                     document.getElementById('stat-vehicles-count').textContent = '150';
                     document.getElementById('stat-bids-today').textContent = '23';
                     document.getElementById('stat-sales-today').textContent = '5';
                     document.getElementById('stat-users-count').textContent = '1200';
                }

                // Simular carga de listas
                function loadVehiclesList() {
                     const container = document.getElementById('vehicle-list-container');
                     container.innerHTML = '<p>Lista de vehículos cargada dinámicamente aquí...</p>';
                     // fetch, renderizado, etc.
                }

                function loadBidsList() {
                     const container = document.getElementById('bids-list-container');
                     container.innerHTML = '<p>Lista de pujas cargada dinámicamente aquí...</p>';
                     // fetch, renderizado, etc.
                }

                function loadSalesList() {
                     const container = document.getElementById('sales-list-container');
                     container.innerHTML = '<p>Lista de ventas cargada dinámicamente aquí...</p>';
                     // fetch, renderizado, etc.
                }

                 function loadUsersList() {
                     const container = document.getElementById('users-list-container');
                     container.innerHTML = '<p>Lista de usuarios cargada dinámicamente aquí...</p>';
                     // fetch, renderizado, etc.
                }

                // Cargar contenido inicial del dashboard
                loadDashboardStats();

            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}