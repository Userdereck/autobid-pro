// functions.php (CORREGIDO - Versión Final)
<?php
// === Activación ===
function autobid_create_required_pages() {
    $pages_to_create = [
        'sales' => [
            'title' => 'Ventas Directas',
            'content' => '[autobid_catalog type="venta"]',
            'slug' => 'ventas'
        ],
        'auctions' => [
            'title' => 'Subastas',
            'content' => '[autobid_catalog type="subasta"]',
            'slug' => 'subastas'
        ],
        'detail' => [
            'title' => 'Detalle Vehículo',
            'content' => '<div id="vehicle-detail"></div>',
            'slug' => 'vehiculo'
        ],
        'login' => [
            'title' => 'Iniciar Sesión',
            'content' => '[autobid_login]',
            'slug' => 'login'
        ],
        'register' => [
            'title' => 'Registrarse',
            'content' => '[autobid_register]',
            'slug' => 'register'
        ],
        // 'admin' => [ // Opcional: Si decides manejarlo aquí también
        //     'title' => 'Panel Administrativo AutoBid',
        //     'content' => '[autobid_admin_panel]',
        //     'slug' => 'admin-autobid'
        // ]
    ];

    foreach ($pages_to_create as $key => $page_data) {
        // Usar WP_Query en lugar de get_page_by_title
        $query = new WP_Query([
            'post_type' => 'page',
            'post_status' => 'any', // Incluir 'publish', 'draft', etc.
            'name' => $page_data['slug'], // Buscar por slug
            'posts_per_page' => 1,
            'no_found_rows' => 1,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        if ($query->have_posts()) {
            // La página ya existe, obtenemos su ID
            $page_id = $query->posts[0]->ID;
            // Opcional: Actualizar el contenido si ha cambiado
            // if ($query->posts[0]->post_content !== $page_data['content']) {
            //     wp_update_post([
            //         'ID' => $page_id,
            //         'post_content' => $page_data['content']
            //     ]);
            // }
        } else {
            // La página no existe, la creamos
            $page_id = wp_insert_post([
                'post_title'   => $page_data['title'],
                'post_content' => $page_data['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => $page_data['slug']
            ]);

            // Verificar si la creación fue exitosa
            if (is_wp_error($page_id)) {
                error_log("AutoBid Pro: Error al crear la página '{$page_data['title']}': " . $page_id->get_error_message());
                continue; // Saltar a la siguiente página
            }
        }

        // Actualizar la opción correspondiente con el ID de la página
        $option_name = 'autobid_' . $key . '_page_id';
        update_option($option_name, $page_id);
    }
}

// La función autobid_create_bids_table() sigue aquí, pero el hook se registra en autobid-pro.php
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
// NO se registra el hook aquí en functions.php
// register_activation_hook(AUTOBID_PLUGIN_FILE, 'autobid_create_bids_table'); // <-- ELIMINADA CORRECTAMENTE

// === Enqueue ===
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