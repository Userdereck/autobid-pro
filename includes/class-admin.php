<?php
// includes/class-admin.php

if (!defined('ABSPATH')) {
    exit; // Salir si se accede directamente
}

class AutoBid_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_head', [$this, 'admin_styles']);
    }

    public function add_admin_menu() {
        // Menú principal
        add_menu_page(
            'AutoBid Pro', // Título de la página
            'AutoBid Pro', // Título del menú
            'manage_options', // Capacidad requerida
            'autobid-dashboard', // Slug del menú
            [$this, 'render_dashboard_page'], // Función de renderizado
            'dashicons-car', // Icono
            30 // Posición (menor número = más arriba)
        );

        // Submenú - Gestionar Vehículos
        add_submenu_page(
            'autobid-dashboard',
            'Gestionar Vehículos',
            'Vehículos',
            'manage_options', // Ajustar si se quiere un rol específico
            'autobid-vehicles',
            [$this, 'render_vehicles_page']
        );

        // Submenú - Gestionar Usuarios
        add_submenu_page(
            'autobid-dashboard',
            'Gestionar Usuarios',
            'Usuarios',
            'manage_options', // Ajustar si se quiere un rol específico
            'autobid-users',
            [$this, 'render_users_page']
        );

        // Submenú - Gestionar Pujas
        add_submenu_page(
            'autobid-dashboard',
            'Gestionar Pujas',
            'Pujas',
            'manage_options', // Ajustar si se quiere un rol específico
            'autobid-bids',
            [$this, 'render_bids_page']
        );

        // Submenú - Gestionar Ventas (Vehículos vendidos o subastas finalizadas)
        add_submenu_page(
            'autobid-dashboard',
            'Gestionar Ventas',
            'Ventas',
            'manage_options', // Ajustar si se quiere un rol específico
            'autobid-sales',
            [$this, 'render_sales_page']
        );

        // Submenú - Ajustes (mover desde vehicle post type)
        remove_submenu_page('edit.php?post_type=vehicle', 'autobid-settings'); // Quitar del anterior lugar
        add_submenu_page(
            'autobid-dashboard',
            'Ajustes de AutoBid Pro',
            'Ajustes',
            'manage_options',
            'autobid-settings',
            [$this, 'render_settings_page'] // Reutilizamos la función de ajustes de autobid-pro.php
        );
    }

    public function render_dashboard_page() {
        echo '<div class="wrap autobid-admin-wrap">';
        echo '<h1>AutoBid Pro - Panel de Administración</h1>';
        echo '<p>Bienvenido al panel de administración de AutoBid Pro. Utiliza los enlaces del menú lateral para gestionar vehículos, usuarios, pujas y ventas.</p>';
        echo '<div class="autobid-dashboard-cards">';
        echo '<div class="autobid-card">';
        echo '<h3>Vehículos</h3>';
        $vehicle_count = wp_count_posts('vehicle');
        echo '<p>Total: ' . $vehicle_count->publish . '</p>';
        echo '<a href="' . admin_url('admin.php?page=autobid-vehicles') . '" class="button button-primary">Gestionar</a>';
        echo '</div>';
        echo '<div class="autobid-card">';
        echo '<h3>Usuarios</h3>';
        $user_count = count_users();
        echo '<p>Total: ' . $user_count['total_users'] . '</p>';
        echo '<a href="' . admin_url('admin.php?page=autobid-users') . '" class="button button-primary">Gestionar</a>';
        echo '</div>';
        echo '<div class="autobid-card">';
        echo '<h3>Pujas</h3>';
        global $wpdb;
        $bid_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}autobid_bids");
        echo '<p>Total: ' . $bid_count . '</p>';
        echo '<a href="' . admin_url('admin.php?page=autobid-bids') . '" class="button button-primary">Ver Pujas</a>';
        echo '</div>';
        echo '<div class="autobid-card">';
        echo '<h3>Ventas</h3>';
        // Contar vehículos vendidos (tipo venta o subasta cerrada con ganador)
        $sold_auctions = get_posts([
            'post_type' => 'vehicle',
            'meta_query' => [
                [
                    'key' => '_type',
                    'value' => 'subasta',
                ],
                [
                    'key' => '_auction_status',
                    'value' => 'closed', // Solo subastas finalizadas con ganador
                ],
            ],
            'numberposts' => -1,
            'post_status' => 'publish',
        ]);
        $direct_sales = get_posts([
            'post_type' => 'vehicle',
            'meta_query' => [
                [
                    'key' => '_type',
                    'value' => 'venta',
                ],
            ],
            'numberposts' => -1,
            'post_status' => 'publish',
        ]);
        $total_sold = count($sold_auctions) + count($direct_sales);
        echo '<p>Total: ' . $total_sold . '</p>';
        echo '<a href="' . admin_url('admin.php?page=autobid-sales') . '" class="button button-primary">Ver Ventas</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function render_vehicles_page() {
        echo '<div class="wrap autobid-admin-wrap">';
        echo '<h1>Gestionar Vehículos</h1>';
        echo '<p>Administra los vehículos aquí. Puedes <a href="' . admin_url('post-new.php?post_type=vehicle') . '">crear uno nuevo</a> o editar uno existente.</p>';
        // Opcional: Usar WP_List_Table para una tabla personalizada
        // Por ahora, enlazamos a la vista estándar
        echo '<a href="' . admin_url('edit.php?post_type=vehicle') . '" class="button button-primary">Ver Vehículos en WP</a>';
        echo '</div>';
    }

    public function render_users_page() {
        echo '<div class="wrap autobid-admin-wrap">';
        echo '<h1>Gestionar Usuarios</h1>';
        echo '<p>Administra los usuarios del sistema.</p>';
        // Opcional: Usar WP_List_Table para una tabla personalizada
        // Por ahora, enlazamos a la vista estándar de usuarios
        echo '<a href="' . admin_url('users.php') . '" class="button button-primary">Ver Usuarios en WP</a>';
        echo '</div>';
    }

    public function render_bids_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'autobid_bids';

        // Obtener pujas con información del vehículo y usuario
        $bids = $wpdb->get_results("
            SELECT b.id, b.bid_amount, b.created_at, v.post_title as vehicle_name, u.display_name as user_name
            FROM $table_name b
            LEFT JOIN {$wpdb->posts} v ON b.vehicle_id = v.ID
            LEFT JOIN {$wpdb->users} u ON b.user_id = u.ID
            ORDER BY b.created_at DESC
        ");

        echo '<div class="wrap autobid-admin-wrap">';
        echo '<h1>Gestionar Pujas</h1>';
        if ($bids) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>ID</th><th>Vehículo</th><th>Usuario</th><th>Monto</th><th>Fecha</th></tr></thead>';
            echo '<tbody>';
            foreach ($bids as $bid) {
                echo '<tr>';
                echo '<td>' . esc_html($bid->id) . '</td>';
                echo '<td>' . esc_html($bid->vehicle_name) . '</td>';
                echo '<td>' . esc_html($bid->user_name) . '</td>';
                echo '<td>' . esc_html($bid->bid_amount) . '</td>';
                echo '<td>' . esc_html($bid->created_at) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>No hay pujas registradas.</p>';
        }
        echo '</div>';
    }

    public function render_sales_page() {
        // Buscar vehículos vendidos (tipo venta o subasta cerrada con ganador)
        $sold_auctions = get_posts([
            'post_type' => 'vehicle',
            'meta_query' => [
                [
                    'key' => '_type',
                    'value' => 'subasta',
                ],
                [
                    'key' => '_auction_status',
                    'value' => 'closed', // Solo subastas finalizadas con ganador
                ],
            ],
            'numberposts' => -1,
            'post_status' => 'publish',
        ]);

        $direct_sales = get_posts([
            'post_type' => 'vehicle',
            'meta_query' => [
                [
                    'key' => '_type',
                    'value' => 'venta',
                ],
            ],
            'numberposts' => -1,
            'post_status' => 'publish',
        ]);

        echo '<div class="wrap autobid-admin-wrap">';
        echo '<h1>Gestionar Ventas</h1>';

        echo '<h2>Subastas Finalizadas (Vehículos Vendidos)</h2>';
        if ($sold_auctions) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>ID</th><th>Nombre</th><th>Puja Ganadora</th><th>Ganador</th><th>Fecha Fin</th></tr></thead>';
            echo '<tbody>';
            foreach ($sold_auctions as $vehicle) {
                $winner_id = get_post_meta($vehicle->ID, '_winner_user_id', true);
                $winner_user = $winner_id ? get_userdata($winner_id) : null;
                $current_bid = get_post_meta($vehicle->ID, '_current_bid', true);
                $end_time = get_post_meta($vehicle->ID, '_end_time', true);
                echo '<tr>';
                echo '<td>' . esc_html($vehicle->ID) . '</td>';
                echo '<td>' . esc_html($vehicle->post_title) . '</td>';
                echo '<td>' . esc_html($current_bid) . '</td>';
                echo '<td>' . ($winner_user ? esc_html($winner_user->display_name) : 'N/A') . '</td>';
                echo '<td>' . esc_html($end_time) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>No hay subastas finalizadas con ganador.</p>';
        }

        echo '<h2>Ventas Directas</h2>';
        if ($direct_sales) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Moneda</th><th>Fecha</th></tr></thead>';
            echo '<tbody>';
            foreach ($direct_sales as $vehicle) {
                $price = get_post_meta($vehicle->ID, '_price', true);
                $currency = get_post_meta($vehicle->ID, '_currency', true);
                echo '<tr>';
                echo '<td>' . esc_html($vehicle->ID) . '</td>';
                echo '<td>' . esc_html($vehicle->post_title) . '</td>';
                echo '<td>' . esc_html($price) . '</td>';
                echo '<td>' . esc_html($currency) . '</td>';
                echo '<td>' . esc_html($vehicle->post_date) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>No hay ventas directas registradas.</p>';
        }

        echo '</div>';
    }

    // Reutilizamos la función de ajustes de autobid-pro.php
    public function render_settings_page() {
        // Llamamos a la función global que debería estar definida en autobid-pro.php
        if (function_exists('autobid_render_settings_page')) {
            autobid_render_settings_page();
        } else {
            echo '<div class="wrap"><p>Error: La función de ajustes no está disponible.</p></div>';
        }
    }

    // --- CSS para el panel de administración ---
    public function admin_styles() {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'autobid') !== false) { // Solo en páginas de autobid
            echo '<style>
                .autobid-admin-wrap { max-width: 1200px; margin: 20px auto; }
                .autobid-dashboard-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
                .autobid-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
                .autobid-card h3 { margin-top: 0; color: #23282d; }
                .autobid-card p { margin: 10px 0; font-size: 1.5em; font-weight: bold; color: #555; }
                .autobid-card a.button { display: inline-block; margin-top: 10px; }
            </style>';
        }
    }
    // --- Fin CSS para el panel de administración ---
}