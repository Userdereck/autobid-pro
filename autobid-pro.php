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
// Renderizar página de ajustes (VERSIÓN EXTENDIDA Y CORREGIDA)
function autobid_render_settings_page() {
    // Verificar si el usuario está logueado y tiene permisos
    if (!is_user_logged_in() || !current_user_can('administrator')) {
        wp_die('Acceso denegado. Requiere permisos de administrador.');
    }

    // Guardar ajustes si hay envío
    if ($_POST && check_admin_referer('autobid_save_settings', 'autobid_nonce')) { // <-- Verificar nonce
        // Colores principales
        update_option('autobid_color_primary', sanitize_hex_color($_POST['color_primary'] ?? '#1e3c72'));
        update_option('autobid_color_accent', sanitize_hex_color($_POST['color_accent'] ?? '#e74c3c'));
        update_option('autobid_bg_color', sanitize_hex_color($_POST['bg_color'] ?? '#ffffff'));
        update_option('autobid_font_family', sanitize_text_field($_POST['font_family'] ?? 'Poppins, sans-serif'));

        // Nuevo grupo de colores
        update_option('autobid_text_color', sanitize_hex_color($_POST['text_color'] ?? '#212529'));
        update_option('autobid_label_color', sanitize_hex_color($_POST['label_color'] ?? '#333333'));
        update_option('autobid_button_text_color', sanitize_hex_color($_POST['button_text_color'] ?? '#ffffff'));
        update_option('autobid_button_bg_color', sanitize_hex_color($_POST['button_bg_color'] ?? '#1e3c72'));
        update_option('autobid_button_hover_bg', sanitize_hex_color($_POST['button_hover_bg'] ?? '#162b50'));
        update_option('autobid_input_bg_color', sanitize_hex_color($_POST['input_bg_color'] ?? '#ffffff'));
        update_option('autobid_input_border_color', sanitize_hex_color($_POST['input_border_color'] ?? '#ced4da'));

        // Colores de login
        update_option('autobid_login_bg', sanitize_hex_color($_POST['login_bg'] ?? '#f8f9fa'));
        update_option('autobid_login_card_bg', sanitize_hex_color($_POST['login_card_bg'] ?? '#ffffff'));
        update_option('autobid_login_label_color', sanitize_hex_color($_POST['login_label_color'] ?? '#212529'));
        update_option('autobid_login_button_bg', sanitize_hex_color($_POST['login_button_bg'] ?? '#1e3c72'));
        update_option('autobid_login_button_text', sanitize_hex_color($_POST['login_button_text'] ?? '#ffffff'));

        // Otros ajustes
        update_option('autobid_dark_mode', isset($_POST['dark_mode']) ? 1 : 0);
        update_option('autobid_logo_url', esc_url_raw($_POST['logo_url'] ?? ''));
        update_option('autobid_footer_text', wp_kses_post($_POST['footer_text'] ?? '© AutoBid Pro'));
        update_option('autobid_label_sale', sanitize_text_field($_POST['label_sale'] ?? 'Venta'));
        update_option('autobid_label_auction', sanitize_text_field($_POST['label_auction'] ?? 'Subasta'));
        // --- NUEVO: Guardar número de WhatsApp ---
        update_option('autobid_whatsapp_number', sanitize_text_field($_POST['whatsapp_number'] ?? '')); // <-- Añadido
        // --- FIN NUEVO ---

        echo '<div class="updated"><p><strong>✅ Ajustes visuales guardados correctamente.</strong></p></div>';
    }

    // Recuperar valores
    $color_primary = get_option('autobid_color_primary', '#1e3c72');
    $color_accent = get_option('autobid_color_accent', '#e74c3c');
    $bg_color = get_option('autobid_bg_color', '#ffffff');
    $font_family = get_option('autobid_font_family', 'Poppins, sans-serif');

    $text_color = get_option('autobid_text_color', '#212529');
    $label_color = get_option('autobid_label_color', '#333333');
    $button_text_color = get_option('autobid_button_text_color', '#ffffff');
    $button_bg_color = get_option('autobid_button_bg_color', '#1e3c72');
    $button_hover_bg = get_option('autobid_button_hover_bg', '#162b50');
    $input_bg_color = get_option('autobid_input_bg_color', '#ffffff');
    $input_border_color = get_option('autobid_input_border_color', '#ced4da');

    $login_bg = get_option('autobid_login_bg', '#f8f9fa');
    $login_card_bg = get_option('autobid_login_card_bg', '#ffffff');
    $login_label_color = get_option('autobid_login_label_color', '#212529');
    $login_button_bg = get_option('autobid_login_button_bg', '#1e3c72');
    $login_button_text = get_option('autobid_login_button_text', '#ffffff');

    $dark_mode = get_option('autobid_dark_mode', 0);
    $logo_url = get_option('autobid_logo_url', '');
    $footer_text = get_option('autobid_footer_text', '© AutoBid Pro');
    $label_sale = get_option('autobid_label_sale', 'Venta');
    $label_auction = get_option('autobid_label_auction', 'Subasta');
    // --- NUEVO: Recuperar número de WhatsApp ---
    $whatsapp_number = get_option('autobid_whatsapp_number', ''); // <-- Añadido
    // --- FIN NUEVO ---
    ?>

    <div class="wrap autobid-settings-page">
        <h1>🎨 Ajustes Visuales de AutoBid Pro</h1>
        <form method="post">
            <?php wp_nonce_field('autobid_save_settings', 'autobid_nonce'); ?> <!-- Añadir nonce -->

            <!-- Identidad Visual -->
            <h2>⚙️ Identidad y Tema</h2>
            <table class="form-table">
                <tr><th>Logo</th>
                    <td>
                        <input type="url" name="logo_url" id="autobid_logo_url" value="<?php echo esc_attr($logo_url); ?>" style="width:70%;">
                        <button type="button" class="button autobid-upload-btn" data-target="#autobid_logo_url">Subir Imagen</button>
                        <?php if ($logo_url): ?><div style="margin-top:10px;"><img src="<?php echo esc_url($logo_url); ?>" style="max-height:80px;"></div><?php endif; ?>
                    </td>
                </tr>
                <tr><th>Color Primario</th><td><input type="color" name="color_primary" value="<?php echo esc_attr($color_primary); ?>"></td></tr>
                <tr><th>Color de Acento</th><td><input type="color" name="color_accent" value="<?php echo esc_attr($color_accent); ?>"></td></tr>
                <tr><th>Color de Fondo</th><td><input type="color" name="bg_color" value="<?php echo esc_attr($bg_color); ?>"></td></tr>
                <tr><th>Fuente Base</th><td><input type="text" name="font_family" value="<?php echo esc_attr($font_family); ?>" placeholder="Ej: Poppins, sans-serif"></td></tr>
                <tr><th>Modo Oscuro</th><td><input type="checkbox" name="dark_mode" value="1" <?php checked($dark_mode, 1); ?>> Activar</td></tr>
            </table>

            <!-- Colores de texto, labels y botones -->
            <h2>🖋️ Colores de Texto y Formularios</h2>
            <table class="form-table">
                <tr><th>Color de Texto</th><td><input type="color" name="text_color" value="<?php echo esc_attr($text_color); ?>"></td></tr>
                <tr><th>Color de Etiquetas (labels)</th><td><input type="color" name="label_color" value="<?php echo esc_attr($label_color); ?>"></td></tr>
                <tr><th>Color Fondo Inputs</th><td><input type="color" name="input_bg_color" value="<?php echo esc_attr($input_bg_color); ?>"></td></tr>
                <tr><th>Color Borde Inputs</th><td><input type="color" name="input_border_color" value="<?php echo esc_attr($input_border_color); ?>"></td></tr>
                <tr><th>Color Botón (fondo)</th><td><input type="color" name="button_bg_color" value="<?php echo esc_attr($button_bg_color); ?>"></td></tr>
                <tr><th>Color Botón (texto)</th><td><input type="color" name="button_text_color" value="<?php echo esc_attr($button_text_color); ?>"></td></tr>
                <tr><th>Color Hover del Botón</th><td><input type="color" name="button_hover_bg" value="<?php echo esc_attr($button_hover_bg); ?>"></td></tr>
            </table>

            <!-- Colores del login -->
            <h2>🔐 Colores del Login Frontend</h2>
            <table class="form-table">
                <tr><th>Fondo General</th><td><input type="color" name="login_bg" value="<?php echo esc_attr($login_bg); ?>"></td></tr>
                <tr><th>Fondo del Contenedor</th><td><input type="color" name="login_card_bg" value="<?php echo esc_attr($login_card_bg); ?>"></td></tr>
                <tr><th>Color de Etiquetas</th><td><input type="color" name="login_label_color" value="<?php echo esc_attr($login_label_color); ?>"></td></tr>
                <tr><th>Botón de Login (Fondo)</th><td><input type="color" name="login_button_bg" value="<?php echo esc_attr($login_button_bg); ?>"></td></tr>
                <tr><th>Botón de Login (Texto)</th><td><input type="color" name="login_button_text" value="<?php echo esc_attr($login_button_text); ?>"></td></tr>
            </table>

            <!-- Otros -->
            <h2>💬 Textos Globales</h2>
            <table class="form-table">
                <tr><th>Etiqueta para Ventas</th><td><input type="text" name="label_sale" value="<?php echo esc_attr($label_sale); ?>"></td></tr>
                <tr><th>Etiqueta para Subastas</th><td><input type="text" name="label_auction" value="<?php echo esc_attr($label_auction); ?>"></td></tr>
                <tr><th>Texto Pie de Página</th><td><textarea name="footer_text" rows="3" cols="50"><?php echo esc_textarea($footer_text); ?></textarea></td></tr>
                <!-- --- NUEVO CAMPO: Número de WhatsApp --- -->
                <tr>
                    <th>Número de WhatsApp para Compras</th>
                    <td>
                        <input type="text" name="whatsapp_number" value="<?php echo esc_attr($whatsapp_number); ?>" placeholder="+1234567890">
                        <p class="description">Ingresa el número de WhatsApp (con código de país) al que se enviarán las solicitudes de compra. Ej: +1234567890</p>
                    </td>
                </tr>
                <!-- --- FIN NUEVO CAMPO --- -->
            </table>

            <?php submit_button('Guardar Ajustes'); ?>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($){
        // Subida de logo
        $('.autobid-upload-btn').on('click', function(e){
            e.preventDefault();
            const target = $($(this).data('target'));
            const frame = wp.media({
                title: 'Seleccionar Imagen',
                button: { text: 'Usar esta imagen' },
                multiple: false
            });
            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                target.val(attachment.url);
            });
            frame.open();
        });
    });
    </script>

    <style>
        .autobid-settings-page h1 { color: #1e3c72; }
        .autobid-settings-page h2 { color: #2a5298; margin-top: 2rem; }
        .autobid-settings-page .form-table th { width: 280px; text-align: left; vertical-align: top; }
        .autobid-settings-page .form-table td { padding-bottom: 1rem; }
        .description {
             font-size: 0.85rem;
             color: #6c757d;
             margin-top: 0.2rem;
             font-style: italic;
        }
    </style>
    <?php
}
// --- FIN CORREGIDO ---

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
        wp_enqueue_script('autobid-detail', plugin_dir_url(__FILE__) . 'public/js/detail.js', ['wp-i18n'], '1.5', true);
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
// --- FIN NUEVO ---