<?php
/**
 * Plugin Name: AutoBid Pro
 * Description: Plataforma de subastas y ventas de vehículos en WordPress.
 * Version: 1.0
 * Author: Alexander Mejia
 */

defined('ABSPATH') or die('Acceso directo no permitido.');

require_once plugin_dir_path(__FILE__) . 'includes/class-post-types.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auction-cron.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-shortcodes.php';
// --- Nuevos archivos añadidos ---
require_once plugin_dir_path(__FILE__) . 'includes/class-auth.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-frontend-admin.php'; // <-- Asegurar que se incluye
// --- Fin nuevos archivos ---

new AutoBid_Post_Types();
new AutoBid_API();
new AutoBid_Auction_Cron();
new AutoBid_Shortcodes();
// --- Nuevas instancias ---
new AutoBid_Auth();
new AutoBid_Frontend_Admin(); // <-- Asegurar que se instancia
// --- Fin nuevas instancias ---

function autobid_create_required_pages() {
    // Ventas directas
    $sales_page = get_page_by_path('Ventas Directas');
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
    $auctions_page = get_page_by_path('Subastas');
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
    $detail_page = get_page_by_path('Detalle Vehículo');
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

// --- CORREGIDO: Añadir página de ajustes al menú ---
// Añadir página de ajustes al menú
function autobid_add_settings_page() {
    // --- CORREGIDO: Usar WP_Query en lugar de get_page_by_title ---
    $settings_page_query = new WP_Query([
        'post_type' => 'page',
        'title' => 'Ajustes de AutoBid Pro',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'no_found_rows' => true,
    ]);
    if ($settings_page_query->have_posts()) {
        $settings_page_id = $settings_page_query->posts[0]->ID;
    } else {
        $settings_page_id = wp_insert_post([
            'post_title'   => 'Ajustes de AutoBid Pro',
            'post_content' => '[autobid_settings]', // Shortcode de ajustes
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_name'    => 'autobid-settings' // Slug amigable
        ]);
    }
    wp_reset_postdata(); // Importante resetear

    add_submenu_page(
        'edit.php?post_type=vehicle',
        'AutoBid Pro - Ajustes',
        'Ajustes',
        'manage_options',
        'autobid-settings', // Slug del menú
        'autobid_render_settings_page' // Función de renderizado
    );
}
add_action('admin_menu', 'autobid_add_settings_page');
// --- FIN CORREGIDO ---

// --- CORREGIDO: Renderizar página de ajustes ---
function autobid_render_settings_page() {
    if (!is_user_logged_in() || !current_user_can('administrator')) {
        wp_die('Acceso denegado.');
    }

    // Guardar ajustes
    if ($_POST && check_admin_referer('autobid_save_settings', 'autobid_nonce')) {
        // --- Colores esenciales ---
        update_option('autobid_color_title', sanitize_hex_color($_POST['color_title'] ?? '#1e3c72'));
        update_option('autobid_color_label', sanitize_hex_color($_POST['color_label'] ?? '#333333'));
        update_option('autobid_color_button', sanitize_hex_color($_POST['color_button'] ?? '#1e3c72'));
        update_option('autobid_color_button_hover', sanitize_hex_color($_POST['color_button_hover'] ?? '#162b50'));
        update_option('autobid_color_bg', sanitize_hex_color($_POST['color_bg'] ?? '#ffffff'));
        update_option('autobid_color_input_bg', sanitize_hex_color($_POST['color_input_bg'] ?? '#ffffff'));
        update_option('autobid_color_input_border', sanitize_hex_color($_POST['color_input_border'] ?? '#ced4da'));

        // --- Textos personalizables ---
        update_option('autobid_text_comprar', sanitize_text_field($_POST['text_comprar'] ?? 'Comprar ahora'));
        update_option('autobid_text_pujar', sanitize_text_field($_POST['text_pujar'] ?? 'Pujar ahora'));
        update_option('autobid_text_login_required', sanitize_text_field($_POST['text_login_required'] ?? 'Debes iniciar sesión para continuar.'));

        // --- WhatsApp ---
        $whatsapp = preg_replace('/[^0-9+]/', '', sanitize_text_field($_POST['whatsapp_number'] ?? ''));
        update_option('autobid_whatsapp_number', $whatsapp);
        update_option('autobid_whatsapp_message_purchase', sanitize_textarea_field($_POST['whatsapp_message_purchase'] ?? ''));

        echo '<div class="updated"><p><strong>✅ Ajustes guardados.</strong></p></div>';
    }

    // Recuperar valores
    $color_title = get_option('autobid_color_title', '#1e3c72');
    $color_label = get_option('autobid_color_label', '#333333');
    $color_button = get_option('autobid_color_button', '#1e3c72');
    $color_button_hover = get_option('autobid_color_button_hover', '#162b50');
    $color_bg = get_option('autobid_color_bg', '#ffffff');
    $color_input_bg = get_option('autobid_color_input_bg', '#ffffff');
    $color_input_border = get_option('autobid_color_input_border', '#ced4da');
    $whatsapp_number = get_option('autobid_whatsapp_number', '');
    $default_msg = "Hola, soy {user_name} (ID: {user_id}). Estoy interesado en comprar el vehículo \"{vehicle_title}\" (ID: {vehicle_id}). Puedes verlo aquí: {vehicle_url}. Gracias por tu atención en {site_name}.";
    $saved_msg = get_option('autobid_whatsapp_message_purchase', $default_msg);
    $whatsapp_message_purchase = stripslashes($saved_msg); // ← Corrección clave    
   
   ?>
    <div class="wrap">
        <h1>⚙️ Ajustes Esenciales - AutoBid Pro</h1>
        <form method="post">
            <?php wp_nonce_field('autobid_save_settings', 'autobid_nonce'); ?>

            <h2>🎨 Colores</h2>
            <table class="form-table">
                <tr><th>Títulos (h1, h2, h3)</th><td><input type="color" name="color_title" value="<?php echo esc_attr($color_title); ?>"></td></tr>
                <tr><th>Etiquetas (labels, filtros, formularios)</th><td><input type="color" name="color_label" value="<?php echo esc_attr($color_label); ?>"></td></tr>
                <tr><th>Botón (fondo)</th><td><input type="color" name="color_button" value="<?php echo esc_attr($color_button); ?>"></td></tr>
                <tr><th>Botón Hover</th><td><input type="color" name="color_button_hover" value="<?php echo esc_attr($color_button_hover); ?>"></td></tr>
                <tr><th>Fondo General</th><td><input type="color" name="color_bg" value="<?php echo esc_attr($color_bg); ?>"></td></tr>
                <tr><th>Fondo Inputs</th><td><input type="color" name="color_input_bg" value="<?php echo esc_attr($color_input_bg); ?>"></td></tr>
                <tr><th>Borde Inputs</th><td><input type="color" name="color_input_border" value="<?php echo esc_attr($color_input_border); ?>"></td></tr>
                <tr>
                <th>Texto: Botón Comprar</th>
                <td><input type="text" name="text_comprar" value="<?php echo esc_attr(get_option('autobid_text_comprar', 'Comprar ahora')); ?>"></td>
                </tr>
                <tr>
                <th>Texto: Botón Pujar</th>
                <td><input type="text" name="text_pujar" value="<?php echo esc_attr(get_option('autobid_text_pujar', 'Pujar ahora')); ?>"></td>
                </tr>
                <tr>
                <th>Mensaje: Login requerido</th>
                <td><input type="text" name="text_login_required" value="<?php echo esc_attr(get_option('autobid_text_login_required', 'Debes iniciar sesión para continuar.')); ?>"></td>
                </tr>
            </table>

            <h2>💬 WhatsApp - Comprar Ahora</h2>
            <table class="form-table">
                <tr>
                    <th>Número de WhatsApp</th>
                    <td>
                        <input type="text" name="whatsapp_number" value="<?php echo esc_attr($whatsapp_number); ?>" placeholder="+1234567890">
                        <p class="description">Solo dígitos y +. Ej: +18291234567</p>
                    </td>
                </tr>
                <tr>
                    <th>Mensaje al Comprar</th>
                    <td>
                        <textarea name="whatsapp_message_purchase" rows="4" style="width:100%;"><?php echo esc_textarea($whatsapp_message_purchase); ?></textarea>
                        <p class="description">Usa: {user_name}, {user_id}, {vehicle_title}, {vehicle_id}, {vehicle_url}, {site_name}</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Guardar Ajustes'); ?>
        </form>
    </div>
    <?php
}
// --- FIN CORREGIDO ---

// Inyectar CSS personalizado en el frontend
function autobid_custom_styles() {
    $title = get_option('autobid_color_title', '#1e3c72');
    $label = get_option('autobid_color_label', '#333333');
    $button = get_option('autobid_color_button', '#1e3c72');
    $button_hover = get_option('autobid_color_button_hover', '#162b50');
    $bg = get_option('autobid_color_bg', '#ffffff');
    $input_bg = get_option('autobid_color_input_bg', '#ffffff');
    $input_border = get_option('autobid_color_input_border', '#ced4da');

    echo "<style>
        /* Títulos */
        h1, h2, h3, h4, .vehicle-title, .autobid-auth-container h2, .autobid-auth-container h3 {
            color: {$title} !important;
        }

        /* Etiquetas en filtros, login, perfil, formularios */
        .autobid-filters label,
        .autobid-form-group label,
        .specs-grid .spec-label,
        .autobid-auth-container label,
        .autobid-profile-form label {
            color: {$label} !important;
        }

        /* Fondos */
        body,
        .autobid-catalog,
        .autobid-auth-container {
            background-color: {$bg} !important;
        }

        /* Inputs */
        .autobid-filters input,
        .autobid-filters select,
        .autobid-form-group input,
        .autobid-form-group textarea,
        .autobid-profile-form input,
        .autobid-profile-form textarea {
            background-color: {$input_bg} !important;
            border-color: {$input_border} !important;
        }

        /* Botones */
        .btn-view-detail,
        .action-button,
        .autobid-auth-button,
        .filter-actions .btn-apply {
            background-color: {$button} !important;
            color: white !important;
        }
        .btn-view-detail:hover,
        .action-button:hover,
        .autobid-auth-button:hover,
        .filter-actions .btn-apply:hover {
            background-color: {$button_hover} !important;
        }
    </style>";
}
add_action('wp_head', 'autobid_custom_styles');


// ... (resto del código permanece igual) ...

function autobid_enqueue_frontend() {
    $sales_id = get_option('autobid_sales_page_id');
    $auctions_id = get_option('autobid_auctions_page_id');
    $detail_id = get_option('autobid_detail_page_id');
    // --- Obtener IDs de páginas de autenticación ---
    $login_id = get_option('autobid_login_page_id');
    $register_id = get_option('autobid_register_page_id');
    $profile_id = get_option('autobid_profile_page_id');
    // --- Fin nuevas páginas ---

    $is_sales = is_page($sales_id);
    $is_auctions = is_page($auctions_id);
    $is_detail = is_page($detail_id);
    // --- Verificar si es página de autenticación ---
    $is_login = is_page($login_id);
    $is_register = is_page($register_id);
    $is_profile = is_page($profile_id);
    // --- Fin verificación nuevas páginas ---

    // Verificar si es página de catálogo genérico con shortcode
    if (is_page() && !$is_sales && !$is_auctions && !$is_detail && !$is_login && !$is_register && !$is_profile) { // --- Añadir nuevas páginas ---
        $post = get_post();
        if ($post && has_shortcode($post->post_content, 'autobid_catalog')) {
            $is_sales = strpos($post->post_content, 'type="venta"') !== false;
            $is_auctions = strpos($post->post_content, 'type="subasta"') !== false;
        }
    }

    // --- Comprobar si es alguna de las páginas relevantes ---
    $is_relevant_page = ($is_sales || $is_auctions || $is_detail || $is_login || $is_register || $is_profile); // --- Añadir nuevas páginas ---

    if (!$is_relevant_page) return; // --- Salir si no es ninguna página relevante ---

    // --- Cargar estilos principales ---
    wp_enqueue_style('autobid-main', plugin_dir_url(__FILE__) . 'public/css/main.css', [], '1.7'); // Incrementar versión

    // --- Cargar estilos de autenticación si es una página de autenticación ---
    if ($is_login || $is_register || $is_profile) {
        wp_enqueue_style('autobid-auth', plugin_dir_url(__FILE__) . 'public/css/auth.css', ['autobid-main'], '1.1'); // Añadir dependencia de main.css
    }

    // --- Cargar scripts y localizar variables ---
    if ($is_sales || $is_auctions) {
        wp_enqueue_script('autobid-catalog', plugin_dir_url(__FILE__) . 'public/js/catalog.js', ['wp-i18n'], '1.5', true);
    }
    if ($is_detail) {
        wp_enqueue_script('autobid-detail', plugin_dir_url(__FILE__) . 'public/js/detail.js', ['wp-i18n'], '1.6', true);

        // --- NUEVO: Localizar textos personalizables ---
        $texts = [
            'buy_button' => get_option('autobid_text_comprar', 'Comprar ahora'),
            'bid_button' => get_option('autobid_text_pujar', 'Pujar ahora'),
            'login_required' => get_option('autobid_text_login_required', 'Debes iniciar sesión para continuar.')
        ];
        wp_localize_script('autobid-detail', 'autobid_texts', $texts);
        // --- FIN NUEVO ---
    }

    // --- Datos localizados para autenticación ---
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
        'label_auction' => $label_auction,
        // --- Nuevas URLs para autenticación ---
        'login_url' => get_permalink($login_id) ?: home_url('/login/'),
        'register_url' => get_permalink($register_id) ?: home_url('/register/'),
        'profile_url' => get_permalink($profile_id) ?: home_url('/profile/'),
        'dashboard_url' => admin_url('admin.php?page=autobid-settings'), // Opcional
    ];

    if ($is_sales || $is_auctions) {
        wp_localize_script('autobid-catalog', 'autobid_vars', $data);
    }
    if ($is_detail) {
        wp_localize_script('autobid-detail', 'autobid_vars', $data);
    }
    // --- Localizar variables para autenticación en páginas relevantes ---
    if ($is_login || $is_register || $is_profile) {
        // Podemos usar cualquiera de los scripts ya cargados o crear uno genérico si es necesario.
        // Usamos catalog como base para las variables de autenticación si no se carga otro script JS específico en estas páginas.
        // Si no se carga catalog ni detail, necesitamos un script base para wp_localize_script.
        // Opción 1: Cargar un script base ligero si no hay otro JS específico en auth pages.
        // Opción 2: Asumir que catalog o detail se carga o que usamos el de detail si está disponible.
        // La forma más limpia es crear un script base para variables globales si no hay otro JS específico.
        // Pero para evitar duplicar código, podemos usar el script que sí se carga, o forzar la carga de uno base si no.
        // Dado que en las páginas de auth no se carga catalog ni detail, creamos un script base ligero si es necesario.
        // wp_register_script('autobid-auth-base', '', [], '1.0', true); // Script vacío
        // wp_enqueue_script('autobid-auth-base');
        // wp_localize_script('autobid-auth-base', 'autobid_vars', $data);

        // Opción más simple: Reutilizar las variables ya definidas en $data y asegurar que estén disponibles.
        // Si ya hay un script JS cargado (como catalog o detail), usamos wp_localize_script con ese.
        // Si no, creamos un script base.
        // Dado que no están cargados, usamos el script base.
        wp_register_script('autobid-auth-base', '', [], '1.0', true);
        wp_enqueue_script('autobid-auth-base');
        wp_localize_script('autobid-auth-base', 'autobid_auth_vars', $data);
    }
    // --- FIN Localizar variables para autenticación en páginas relevantes ---
}
add_action('wp_enqueue_scripts', 'autobid_enqueue_frontend');

function autobid_create_watchlist_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'autobid_auction_watchlist';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        vehicle_id bigint(20) NOT NULL,
        user_id bigint(20) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        notified tinyint(1) DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY unique_watch (vehicle_id, user_id)
    ) $charset;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'autobid_create_watchlist_table');

// --- NUEVO: Registrar regla de reescritura ---
function autobid_add_rewrite_rule() {
    add_rewrite_rule(
        '^detalle/([0-9]+)/?$',
        'index.php?autobid_view=detail&autobid_vehicle_id=$matches[1]',
        'top'
    );
    // Opcional: También capturar ?view=detail&id=XX
    // Esto no necesita add_rewrite_rule, pero sí manejarlo en la lógica de carga de scripts.
}
add_action('init', 'autobid_add_rewrite_rule');

// --- NUEVO: Agregar variables de consulta ---
function autobid_query_vars($vars) {
    $vars[] = 'autobid_view';
    $vars[] = 'autobid_vehicle_id';
    return $vars;
}
add_action('query_vars', 'autobid_query_vars');
// --- FIN NUEVO ---

// --- NUEVO: Renderizar contenido de detalle si es la vista correcta ---
function autobid_render_detail_template() {
    if (get_query_var('autobid_view') === 'detail' || (isset($_GET['view']) && $_GET['view'] === 'detail')) {
        $vehicle_id = get_query_var('autobid_vehicle_id');
        if (!$vehicle_id) {
            $vehicle_id = intval($_GET['id'] ?? 0);
        }
        if ($vehicle_id > 0) {
            // Opcional: Validar que el ID sea de un vehículo real
            $vehicle_post = get_post($vehicle_id);
            if ($vehicle_post && $vehicle_post->post_type === 'vehicle') {
                // Cargar la plantilla de detalle directamente
                // Puedes crear un archivo template-detail.php o renderizar inline como sigue:
                ?>
                <!DOCTYPE html>
                <html <?php language_attributes(); ?>>
                <head>
                    <meta charset="<?php bloginfo('charset'); ?>">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <?php wp_head(); ?>
                </head>
                <body <?php body_class(); ?>>
                    <div id="page" class="site">
                        <div id="content" class="site-content">
                            <div id="vehicle-detail"></div> <!-- Contenedor para detail.js -->
                        </div>
                    </div>
                    <?php wp_footer(); ?>
                </body>
                </html>
                <?php
                exit; // Detener la ejecución normal de la plantilla
            }
        }
        // Si no es una vista válida o ID inválido, mostrar error o redirigir
        // wp_die('Vehículo no encontrado.', '404 Not Found');
        // O redirigir
        wp_redirect(home_url('/ventas/')); // O a donde corresponda
        exit;
    }
}
add_action('template_redirect', 'autobid_render_detail_template');

