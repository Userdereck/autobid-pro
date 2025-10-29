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
require_once plugin_dir_path(__FILE__) . 'includes/email-template.php';

require_once plugin_dir_path(__FILE__) . 'includes/class-settings.php';

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

new AutoBid_settings();
new AutoBid_Auth();
new AutoBid_Frontend_Admin(); // <-- Asegurar que se instancia
// --- Fin nuevas instancias ---

// Reemplazar la función existente por esta versión
function autobid_create_required_pages() {
    $pages = [
        [
            'title' => 'Ventas Directas',
            'content' => '[autobid_catalog type="venta"]',
            'slug' => 'ventas',
            'option' => 'autobid_sales_page_id'
        ],
        [
            'title' => 'Subastas',
            'content' => '[autobid_catalog type="subasta"]',
            'slug' => 'subastas',
            'option' => 'autobid_auctions_page_id'
        ],
        [
            'title' => 'Detalle Vehículo',
            'content' => '<div id="vehicle-detail"></div>',
            'slug' => 'vehiculo',
            'option' => 'autobid_detail_page_id'
        ],
    ];

    foreach ($pages as $p) {
        // Buscar por slug primero (ruta correcta para get_page_by_path)
        $page = get_page_by_path($p['slug']);
        if (!$page) {
            // Si no existe por slug, intentar por título (por si se creó con otro slug)
            $page_by_title = get_page_by_title($p['title'], OBJECT, 'page');
            if ($page_by_title) {
                $page = $page_by_title;
            }
        }

        if ($page) {
            $page_id = $page->ID;
        } else {
            $page_id = wp_insert_post([
                'post_title'   => $p['title'],
                'post_content' => $p['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => $p['slug']
            ]);
        }

        if (!is_wp_error($page_id) && $page_id) {
            update_option($p['option'], $page_id);
        }
    }
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


register_activation_hook(__FILE__, function() {
    $auth = new AutoBid_Auth();
    $auth->create_auth_pages();
    $auth->create_vehicle_user_role();
});

register_activation_hook(__FILE__, function() {
    $frontend_admin = new AutoBid_Frontend_Admin();
    $frontend_admin->create_admin_panel_page();
});

// --- REGISTRAR RUTA AMIGABLE PARA DETALLES DE VEHÍCULO ---
function autobid_register_vehicle_rewrite() {
    // Crea regla: /vehiculo/nombre-del-vehiculo/
    add_rewrite_rule(
        '^vehiculo/([^/]+)/?',
        'index.php?post_type=vehicle&name=$matches[1]',
        'top'
    );
}
add_action('init', 'autobid_register_vehicle_rewrite');

/**
 * ==========================================================
 * AUTO-BID PRO: Página de detalle /vehiculo/?id=285 (compatible con detail.js)
 * ==========================================================
 *
 * Este bloque:
 *  - Crea la página /vehiculo/ si no existe.
 *  - Evita duplicados en cada actualización.
 *  - Inserta un contenedor #vehicle-detail para que detail.js pueda renderizar.
 *  - Hace flush de enlaces permanentes al activar el plugin.
 */

// ---------------------------------------------------------
// 1️⃣  Crear (si no existe) la página base /vehiculo/
// ---------------------------------------------------------
function autobid_register_vehicle_page() {
    $page_slug = 'vehiculo';
    $page_title = 'Vehículo';

    $existing = get_page_by_path($page_slug);
    if (!$existing) {
        $page_id = wp_insert_post([
            'post_title'   => $page_title,
            'post_name'    => $page_slug,
            'post_content' => '<div id="vehicle-detail"></div>',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);

        if (!is_wp_error($page_id)) {
            update_option('autobid_vehicle_page_id', $page_id);
            error_log("AutoBid Pro: Página '/vehiculo' creada con ID {$page_id}");
        } else {
            error_log("AutoBid Pro ERROR: No se pudo crear la página '/vehiculo'.");
        }
    } else {
        update_option('autobid_vehicle_page_id', $existing->ID);
    }
}
add_action('init', 'autobid_register_vehicle_page');

// ---------------------------------------------------------
// 2️⃣  Flush de reglas al activar/desactivar el plugin
// ---------------------------------------------------------
register_activation_hook(__FILE__, function() {
    autobid_register_vehicle_page();
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

// ---------------------------------------------------------
// 3️⃣  Asegurar variables JS globales para detail.js
// ---------------------------------------------------------
add_action('wp_enqueue_scripts', function() {
    // Solo cargar en la página /vehiculo/
    if (is_page('vehiculo')) {
        wp_enqueue_script('autobid-detail', plugins_url('public/js/detail.js', __FILE__), ['jquery'], '1.0', true);

        wp_localize_script('autobid-detail', 'autobid_vars', [
            'api_url'           => esc_url(rest_url('autobid/v1/vehicles')),
            'nonce'             => wp_create_nonce('wp_rest'),
            'current_user_id'   => get_current_user_id(),
            'sales_page_url'    => esc_url(site_url('/ventas/')),
            'auctions_page_url' => esc_url(site_url('/subastas/')),
        ]);
    }
});



