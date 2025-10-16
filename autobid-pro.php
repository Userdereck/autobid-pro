<?php
/**
 * Plugin Name: AutoBid Pro
 * Description: Plataforma de subastas y ventas de vehículos en WordPress.
 * Version: 1.0
 * Author: Tu Nombre
 */

defined('ABSPATH') or die('Acceso directo no permitido.');

require_once plugin_dir_path(__FILE__) . 'includes/class-post-types.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auction-cron.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auth.php';

new AutoBid_Post_Types();
new AutoBid_API();
new AutoBid_Auction_Cron();
new AutoBid_Shortcodes();
new AutoBid_Auth();

function autobid_create_required_pages() {
    // Ventas directas
    $sales_page = get_page_by_title('Ventas Directas');
    if (!$sales_page) {
        $sales_id = wp_insert_post([
            'post_title'   => 'Ventas Directas',
            'post_content' => '[autobid_catalog type="venta"]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_name'    => 'ventas'
        ]);
    } else {
        $sales_id = $sales_page->ID;
    }

    // Subastas
    $auctions_page = get_page_by_title('Subastas');
    if (!$auctions_page) {
        $auctions_id = wp_insert_post([
            'post_title'   => 'Subastas',
            'post_content' => '[autobid_catalog type="subasta"]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_name'    => 'subastas'
        ]);
    } else {
        $auctions_id = $auctions_page->ID;
    }

    // Detalle
    $detail_page = get_page_by_title('Detalle Vehículo');
    if (!$detail_page) {
        $detail_id = wp_insert_post([
            'post_title'   => 'Detalle Vehículo',
            'post_content' => '<div id="vehicle-detail"></div>',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_name'    => 'vehiculo'
        ]);
    } else {
        $detail_id = $detail_page->ID;
    }

    update_option('autobid_sales_page_id', $sales_id);
    update_option('autobid_auctions_page_id', $auctions_id);
    update_option('autobid_detail_page_id', $detail_id);
}
register_activation_hook(__FILE__, 'autobid_create_required_pages');

function autobid_create_bids_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'autobid_bids';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        vehicle_id bigint(20) NOT NULL,
        user_id bigint(20) NOT NULL,
        bid_amount decimal(10,2) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY vehicle_id (vehicle_id),
        KEY user_id (user_id)
    ) $charset;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'autobid_create_bids_table');

// Añadir página de ajustes al menú
function autobid_add_settings_page() {
    add_submenu_page(
        'edit.php?post_type=vehicle',
        'AutoBid Pro - Ajustes',
        'Ajustes',
        'manage_options',
        'autobid-settings',
        'autobid_render_settings_page'
    );
}
add_action('admin_menu', 'autobid_add_settings_page');

// Renderizar página de ajustes
function autobid_render_settings_page() {
    if ($_POST) {
        update_option('autobid_label_sale', sanitize_text_field($_POST['label_sale'] ?? 'Venta'));
        update_option('autobid_label_auction', sanitize_text_field($_POST['label_auction'] ?? 'Subasta'));
        update_option('autobid_color_primary', sanitize_hex_color($_POST['color_primary'] ?? '#1e3c72'));
        update_option('autobid_color_accent', sanitize_hex_color($_POST['color_accent'] ?? '#e74c3c'));
        update_option('autobid_bg_color', sanitize_hex_color($_POST['bg_color'] ?? '#ffffff'));
        update_option('autobid_slider_delay', (int) ($_POST['slider_delay'] ?? 4000));
        update_option('autobid_slider_speed', (int) ($_POST['slider_speed'] ?? 600));
    }
    $labels = [
        'sale' => get_option('autobid_label_sale', 'Venta'),
        'auction' => get_option('autobid_label_auction', 'Subasta')
    ];
    $colors = [
        'primary' => get_option('autobid_color_primary', '#1e3c72'),
        'accent' => get_option('autobid_color_accent', '#e74c3c'),
        'bg' => get_option('autobid_bg_color', '#ffffff')
    ];
    $slider_delay = get_option('autobid_slider_delay', 4000);
    $slider_speed = get_option('autobid_slider_speed', 600);
    ?>
    <div class="wrap">
        <h1>Ajustes de AutoBid Pro</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>Etiqueta para ventas</th>
                    <td><input type="text" name="label_sale" value="<?php echo esc_attr($labels['sale']); ?>"></td>
                </tr>
                <tr>
                    <th>Etiqueta para subastas</th>
                    <td><input type="text" name="label_auction" value="<?php echo esc_attr($labels['auction']); ?>"></td>
                </tr>
                <tr>
                    <th>Color primario</th>
                    <td><input type="color" name="color_primary" value="<?php echo esc_attr($colors['primary']); ?>"></td>
                </tr>
                <tr>
                    <th>Color de acento</th>
                    <td><input type="color" name="color_accent" value="<?php echo esc_attr($colors['accent']); ?>"></td>
                </tr>
                <tr>
                    <th>Fondo</th>
                    <td><input type="color" name="bg_color" value="<?php echo esc_attr($colors['bg']); ?>"></td>
                </tr>
                <tr>
                    <th>Tiempo entre slides (ms)</th>
                    <td><input type="number" name="slider_delay" value="<?php echo esc_attr($slider_delay); ?>" min="1000" max="10000"></td>
                </tr>
                <tr>
                    <th>Duración de transición (ms)</th>
                    <td><input type="number" name="slider_speed" value="<?php echo esc_attr($slider_speed); ?>" min="200" max="2000"></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Inyectar CSS personalizado en el frontend
function autobid_custom_styles() {
    $primary = get_option('autobid_color_primary', '#1e3c72');
    $accent = get_option('autobid_color_accent', '#e74c3c');
    $bg = get_option('autobid_bg_color', '#ffffff');
    echo "<style>
        :root {
            --primary: {$primary};
            --accent: {$accent};
            --light: {$bg};
        }
        .vehicle-type-badge[data-status='upcoming'] { background: #3498db; color: white; }
        .vehicle-type-badge[data-status='live'] { background: {$accent}; color: white; }
        .vehicle-type-badge[data-status='closed'] { background: #95a5a6; color: white; }
    </style>";
}
add_action('wp_head', 'autobid_custom_styles');

function autobid_enqueue_frontend() {
    $sales_id = get_option('autobid_sales_page_id');
    $auctions_id = get_option('autobid_auctions_page_id');
    $detail_id = get_option('autobid_detail_page_id');
    $login_id = get_option('autobid_login_page_id');
    $register_id = get_option('autobid_register_page_id');
    $profile_id = get_option('autobid_profile_page_id');

    $is_sales = is_page($sales_id);
    $is_auctions = is_page($auctions_id);
    $is_detail = is_page($detail_id);
    $is_login = is_page($login_id);
    $is_register = is_page($register_id);
    $is_profile = is_page($profile_id);

    if (is_page() && !$is_sales && !$is_auctions && !$is_detail) {
        $post = get_post();
        if ($post && has_shortcode($post->post_content, 'autobid_catalog')) {
            $is_sales = strpos($post->post_content, 'type="venta"') !== false;
            $is_auctions = strpos($post->post_content, 'type="subasta"') !== false;
        }
    }

    if (!$is_sales && !$is_auctions && !$is_detail) return;

    wp_enqueue_style('autobid-main', plugin_dir_url(__FILE__) . 'public/css/main.css', [], '1.6');

     if ($is_login || $is_register || $is_profile) {
        wp_enqueue_style('autobid-auth', plugin_dir_url(__FILE__) . 'public/css/auth.css', [], '1.0');
    }
    if ($is_sales || $is_auctions) {
        wp_enqueue_script('autobid-catalog', plugin_dir_url(__FILE__) . 'public/js/catalog.js', ['wp-i18n'], '1.5', true);
    }
    
    if ($is_detail) {
        wp_enqueue_script('autobid-detail', plugin_dir_url(__FILE__) . 'public/js/detail.js', ['wp-i18n'], '1.5', true);
    }

    if ($is_login || $is_register || $is_profile) {
        $data = [
            'api_url' => rest_url('autobid/v1/vehicles'),
            'nonce' => wp_create_nonce('wp_rest'),
            'login_url' => get_permalink($login_id) ?: home_url('/login/'),
            'register_url' => get_permalink($register_id) ?: home_url('/register/'),
            'profile_url' => get_permalink($profile_id) ?: home_url('/profile/'),
            'dashboard_url' => admin_url('admin.php?page=autobid-settings'), // Opcional: enlace al panel de admin
            'current_user_id' => get_current_user_id(),
        ];
        wp_localize_script('autobid-catalog', 'autobid_auth_vars', $data); // Usar el script catalog como base para vars de auth
    }

    // Obtener etiquetas personalizadas
    $label_sale = get_option('autobid_label_sale', 'Venta');
    $label_auction = get_option('autobid_label_auction', 'Subasta');

    $data = [
        'api_url' => rest_url('autobid/v1/vehicles'),
        'nonce' => wp_create_nonce('wp_rest'),
        'sales_page_url' => get_permalink($sales_id) ?: home_url('/ventas/'),
        'auctions_page_url' => get_permalink($auctions_id) ?: home_url('/subastas/'),
        'detail_page_url' => get_permalink($detail_id) ?: '#',
        'current_user_id' => get_current_user_id(),
        'label_sale' => $label_sale,
        'label_auction' => $label_auction
    ];

    if ($is_sales || $is_auctions) {
        wp_localize_script('autobid-catalog', 'autobid_vars', $data);
    }
    if ($is_detail) {
        wp_localize_script('autobid-detail', 'autobid_vars', $data);
    }
}
add_action('wp_enqueue_scripts', 'autobid_enqueue_frontend');