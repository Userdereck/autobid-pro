<?php
// includes/class-auth.php

if (!defined('ABSPATH')) {
    exit; // Salir si se accede directamente
}

class AutoBid_Auth {

    public function __construct() {
        
        add_action('init', [$this, 'handle_auth_requests']);
       
        add_shortcode('autobid_login', [$this, 'render_login_form']);
        add_shortcode('autobid_register', [$this, 'render_register_form']);
        add_shortcode('autobid_profile', [$this, 'render_profile']);
    }

    // --- Crear rol personalizado ---
    public function create_vehicle_user_role() {
        // Solo crear el rol si no existe
        if (get_role('vehicle_user')) {
            return;
        }

        // Obtener capacidades base de 'subscriber'
        $subscriber_caps = get_role('subscriber')->capabilities;

        // Definir capacidades específicas para 'vehicle_user'
        $vehicle_user_caps = $subscriber_caps; // Heredar capacidades de 'subscriber'
        $vehicle_user_caps['read'] = true; // Puede leer
        // Añadir capacidades personalizadas si es necesario
        $vehicle_user_caps['place_bid'] = true; // Ejemplo de capacidad personalizada
        $vehicle_user_caps['view_vehicle_details'] = true; // Otro ejemplo

        add_role(
            'vehicle_user',
            'Vehicle User',
            $vehicle_user_caps
        );
    }
    // --- Fin Crear rol personalizado ---

    public function create_auth_pages() {
        $pages = [
            [
                'title' => 'Login',
                'slug' => 'login',
                'content' => '[autobid_login]',
                'option' => 'autobid_login_page_id'
            ],
            [
                'title' => 'Registro',
                'slug' => 'register',
                'content' => '[autobid_register]',
                'option' => 'autobid_register_page_id'
            ],
            [
                'title' => 'Mi Perfil',
                'slug' => 'profile',
                'content' => '[autobid_profile]',
                'option' => 'autobid_profile_page_id'
            ]
        ];

        foreach ($pages as $p) {
            // Buscar por slug primero (forma correcta)
            $page = get_page_by_path($p['slug']);

            // Si no la encuentra, intentar por título
            if (!$page) {
                $page_by_title = get_page_by_title($p['title'], OBJECT, 'page');
                if ($page_by_title) {
                    $page = $page_by_title;
                }
            }

            // Crear solo si no existe
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

            // Guardar en las opciones
            if (!is_wp_error($page_id) && $page_id) {
                update_option($p['option'], $page_id);
            }
        }
    }


    public function handle_auth_requests() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['autobid_login_nonce']) && wp_verify_nonce($_POST['autobid_login_nonce'], 'autobid_login_action')) {
                $this->process_login();
            } elseif (isset($_POST['autobid_register_nonce']) && wp_verify_nonce($_POST['autobid_register_nonce'], 'autobid_register_action')) {
                $this->process_register();
            } elseif (isset($_POST['autobid_update_profile_nonce']) && wp_verify_nonce($_POST['autobid_update_profile_nonce'], 'autobid_update_profile_action')) {
                $this->process_update_profile();
            }
        }
    }

    private function process_login() {
        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']) ? true : false;

        $user = wp_signon([
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember
        ], false);

        if (is_wp_error($user)) {
            $error_message = $user->get_error_message();
            wp_redirect(add_query_arg('login_error', urlencode($error_message), wp_get_referer()));
            exit;
        } else {
            // Opcional: Asignar rol 'vehicle_user' si no lo tiene
            // Esto podría hacerse aquí o en el registro.
            $this->ensure_user_role($user->ID);
            wp_redirect(home_url('/profile/'));
            exit;
        }
    }

    private function process_register() {
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $phone = sanitize_text_field($_POST['phone']); // <-- Nuevo

        if (empty($username) || empty($email) || empty($password) || empty($phone)) {
            wp_redirect(add_query_arg('register_error', 'Todos los campos son obligatorios.', wp_get_referer()));
            exit;
        }
        if (username_exists($username)) {
            wp_redirect(add_query_arg('register_error', 'Nombre de usuario ya existe.', wp_get_referer()));
            exit;
        }
        if (email_exists($email)) {
            wp_redirect(add_query_arg('register_error', 'Correo electrónico ya registrado.', wp_get_referer()));
            exit;
        }

        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            wp_redirect(add_query_arg('register_error', $user_id->get_error_message(), wp_get_referer()));
            exit;
        }

        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'phone', $phone); // <-- Guardar teléfono

        $this->assign_vehicle_user_role($user_id);
        wp_new_user_notification($user_id, null, 'admin');
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        wp_redirect(home_url('/profile/'));
        exit;
    }

    private function process_update_profile() {
        if (!is_user_logged_in()) {
            wp_redirect(home_url('/login/'));
            exit;
        }

        $user_id = get_current_user_id();
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $phone = sanitize_text_field($_POST['phone']);
        $address = sanitize_textarea_field($_POST['address']);

        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'phone', $phone);
        update_user_meta($user_id, 'address', $address);

        if (!empty($_POST['email']) && $_POST['email'] !== wp_get_current_user()->user_email) {
            $new_email = sanitize_email($_POST['email']);
            if (email_exists($new_email) && $new_email !== wp_get_current_user()->user_email) {
                wp_redirect(add_query_arg('profile_error', 'El nuevo correo electrónico ya está en uso.', wp_get_referer()));
                exit;
            }
            wp_update_user(['ID' => $user_id, 'user_email' => $new_email]);
        }

        if (!empty($_POST['new_password'])) {
            $old_password = $_POST['old_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            if ($new_password !== $confirm_password) {
                wp_redirect(add_query_arg('profile_error', 'La nueva contraseña y la confirmación no coinciden.', wp_get_referer()));
                exit;
            }

            $user = wp_get_current_user();
            if (!wp_check_password($old_password, $user->user_pass, $user->ID)) {
                wp_redirect(add_query_arg('profile_error', 'La contraseña actual es incorrecta.', wp_get_referer()));
                exit;
            }

            wp_set_password($new_password, $user_id);
        }

        wp_redirect(add_query_arg('profile_updated', 'true', wp_get_referer()));
        exit;
    }

    // --- Asignar rol 'vehicle_user' ---
    private function assign_vehicle_user_role($user_id) {
        $user = new WP_User($user_id);
        $user->set_role('vehicle_user');
    }

    // --- Asegurar rol 'vehicle_user' ---
    private function ensure_user_role($user_id) {
        $user = new WP_User($user_id);
        if (empty($user->roles) || !in_array('vehicle_user', $user->roles)) {
            $user->add_role('vehicle_user');
        }
    }
    // --- Fin Asignar/Asegurar rol ---

    public function render_login_form($atts) {
        if (is_user_logged_in()) {
            wp_redirect(home_url('/profile/'));
            exit;
        }

        $atts = shortcode_atts(['redirect' => home_url('/profile/')], $atts);
        $error_message = isset($_GET['login_error']) ? urldecode($_GET['login_error']) : '';

        ob_start();
        ?>
        <div class="autobid-auth-container">
            <h2>Iniciar Sesión</h2>
            <?php if ($error_message): ?>
                <div class="autobid-auth-message error"><?php echo esc_html($error_message); ?></div>
            <?php endif; ?>
            <form method="post" class="autobid-auth-form">
                <?php wp_nonce_field('autobid_login_action', 'autobid_login_nonce'); ?>
                <div class="autobid-form-group">
                    <label for="username">Nombre de Usuario o Correo Electrónico</label>
                    <input type="text" name="username" id="username" required>
                </div>
                <div class="autobid-form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <div class="autobid-form-group">
                    <label>
                        <input type="checkbox" name="remember" id="remember"> Recordarme
                    </label>
                </div>
                <button type="submit" class="autobid-auth-button">Iniciar Sesión</button>
            </form>
            <p class="autobid-auth-link">¿No tienes una cuenta? <a href="<?php echo esc_url(home_url('/register/')); ?>">Regístrate aquí</a></p>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_register_form($atts) {
        if (is_user_logged_in()) {
            wp_redirect(home_url('/profile/'));
            exit;
        }

        $atts = shortcode_atts([], $atts);
        $error_message = isset($_GET['register_error']) ? $_GET['register_error'] : '';

        ob_start();
        ?>
        <div class="autobid-auth-container">
            <h2>Crear Cuenta</h2>
            <?php if ($error_message): ?>
                <div class="autobid-auth-message error"><?php echo esc_html($error_message); ?></div>
            <?php endif; ?>
            <form method="post" class="autobid-auth-form">
                <?php wp_nonce_field('autobid_register_action', 'autobid_register_nonce'); ?>
                <div class="autobid-form-group">
                    <label for="username">Nombre de Usuario</label>
                    <input type="text" name="username" id="username" required>
                </div>
                <div class="autobid-form-group">
                    <label for="email">Correo Electrónico</label>
                    <input type="email" name="email" id="email" required>
                </div>
                <div class="autobid-form-group">
                    <label for="first_name">Nombre</label>
                    <input type="text" name="first_name" id="first_name">
                </div>
                <div class="autobid-form-group">
                    <label for="last_name">Apellido</label>
                    <input type="text" name="last_name" id="last_name">
                </div>
                <div class="autobid-form-group">
                    <label for="phone">Teléfono (con código de país, ej: +18291234567) <span style="color:#e74c3c;">*</span></label>
                    <input type="tel" name="phone" id="phone" required placeholder="+18291234567">
                </div>
                <div class="autobid-form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <button type="submit" class="autobid-auth-button">Registrarse</button>
            </form>
            <p class="autobid-auth-link">¿Ya tienes una cuenta? <a href="<?php echo esc_url(home_url('/login/')); ?>">Inicia sesión aquí</a></p>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_profile($atts) {
        if (!is_user_logged_in()) {
            wp_redirect(home_url('/login/'));
            exit;
        }
        $current_user = wp_get_current_user();

        ob_start();
        ?>
        <div class="autobid-auth-container autobid-profile-container">
            <h2>Mi Cuenta</h2>

            <div class="autobid-user-tabs">
                <button type="button" class="tab-btn active" data-tab="profile">Mi Perfil</button>
                <button type="button" class="tab-btn" data-tab="bids">Mis Pujas</button>
            </div>

            <div class="autobid-tab-content">
                <!-- Perfil -->
                <div id="tab-profile" class="tab-pane active">
                    <div class="autobid-profile-card">
                        <h3>Información Personal</h3>
                        <div class="profile-grid">
                            <div><strong>Usuario:</strong> <?php echo esc_html($current_user->user_login); ?></div>
                            <div><strong>Nombre:</strong> <?php echo esc_html($current_user->first_name . ' ' . $current_user->last_name); ?></div>
                            <div><strong>Email:</strong> <?php echo esc_html($current_user->user_email); ?></div>
                            <div><strong>Teléfono:</strong> <?php echo esc_html(get_user_meta($current_user->ID, 'phone', true)); ?></div>
                            <div><strong>Dirección:</strong> <?php echo esc_html(get_user_meta($current_user->ID, 'address', true)); ?></div>
                            <div><strong>Rol:</strong> <?php echo esc_html(implode(', ', $current_user->roles)); ?></div>
                            <div><strong>Registrado:</strong> <?php echo esc_html(wp_date('d/m/Y', strtotime($current_user->user_registered))); ?></div>
                        </div>
                    </div>

                    <form method="post" class="autobid-auth-form autobid-profile-form">
                        <?php wp_nonce_field('autobid_update_profile_action', 'autobid_update_profile_nonce'); ?>
                        <h3>Editar Perfil</h3>
                        <div class="autobid-form-group">
                            <label for="first_name">Nombre</label>
                            <input type="text" name="first_name" id="first_name" value="<?php echo esc_attr($current_user->first_name); ?>">
                        </div>
                        <div class="autobid-form-group">
                            <label for="last_name">Apellido</label>
                            <input type="text" name="last_name" id="last_name" value="<?php echo esc_attr($current_user->last_name); ?>">
                        </div>
                        <div class="autobid-form-group">
                            <label for="email">Correo Electrónico</label>
                            <input type="email" name="email" id="email" value="<?php echo esc_attr($current_user->user_email); ?>">
                        </div>
                        <div class="autobid-form-group">
                            <label for="phone">Teléfono</label>
                            <input type="text" name="phone" id="phone" value="<?php echo esc_attr(get_user_meta($current_user->ID, 'phone', true)); ?>">
                        </div>
                        <div class="autobid-form-group">
                            <label for="address">Dirección</label>
                            <textarea name="address" id="address"><?php echo esc_textarea(get_user_meta($current_user->ID, 'address', true)); ?></textarea>
                        </div>
                        <button type="submit" class="autobid-auth-button">Actualizar Perfil</button>
                    </form>

                    <form method="post" action="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="autobid-logout-form">
                        <button type="submit" class="autobid-auth-button logout">Cerrar Sesión</button>
                    </form>
                </div>

                <!-- Mis Pujas -->
                <div id="tab-bids" class="tab-pane">
                    <h3>Mis Pujas</h3>
                    <div id="user-bids-container">
                        <p class="loading-text">Cargando tus pujas…</p>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Pestañas
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
                    btn.classList.add('active');
                    const tabId = 'tab-' + btn.dataset.tab;
                    document.getElementById(tabId).classList.add('active');

                    if (btn.dataset.tab === 'bids' && !window.bidsLoaded) {
                        loadUserBids();
                        window.bidsLoaded = true;
                    }
                });
            });

            // Cargar pujas
            function loadUserBids() {
                const container = document.getElementById('user-bids-container');
                container.innerHTML = '<p class="loading-text">Cargando…</p>';
                
                fetch('<?php echo esc_url(rest_url('autobid/v1/my-bids')); ?>', {
                    headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' }
                })
                .then(res => res.json())
                .then(bids => {
                    if (bids.length === 0) {
                        container.innerHTML = '<p class="no-bids">Aún no has realizado ninguna puja.</p>';
                        return;
                    }
                    let html = `<div class="bids-grid">`;
                    bids.forEach(bid => {
                        let statusIcon = '❌', statusText = 'Superada', statusClass = 'bid-lost';
                        if (bid.status === 'ganadora') {
                            statusIcon = '✅'; statusText = 'Ganadora'; statusClass = 'bid-won';
                        } else if (bid.status === 'líder') {
                            statusIcon = '👑'; statusText = 'Líder'; statusClass = 'bid-leading';
                        } else if (bid.status === 'compra') {
                            statusIcon = '🛒'; statusText = 'Compra'; statusClass = 'bid-purchase';
                        }

                        html += `
                            <div class="bid-card">
                                <div class="bid-vehicle">
                                    <strong>${bid.vehicle_title}</strong>
                                    <small>ID: ${bid.vehicle_id}</small>
                                </div>
                                <div class="bid-amount">
                                    ${parseFloat(bid.bid_amount).toLocaleString('es-ES', { style: 'currency', currency: 'USD' })}
                                </div>
                                <div class="bid-date">
                                    ${new Date(bid.created_at).toLocaleDateString('es-ES')}
                                </div>
                                <div class="bid-status ${statusClass}">
                                    ${statusIcon} ${statusText}
                                </div>
                            </div>`;
                    });
                    html += `</div>`;
                    container.innerHTML = html;
                })
                .catch(err => {
                    console.error('Error:', err);
                    container.innerHTML = '<p class="error">No se pudieron cargar tus pujas.</p>';
                });
            }
        });
        </script>

        <style>
        .autobid-profile-container { max-width: 900px; margin: 0 auto; }
        .autobid-user-tabs {
            display: flex;
            margin: 1.5rem 0;
            border-bottom: 2px solid #e9ecef;
        }
        .tab-btn {
            padding: 0.8rem 1.5rem;
            background: none;
            border: none;
            font-weight: 600;
            color: #6c757d;
            cursor: pointer;
            position: relative;
        }
        .tab-btn.active {
            color: var(--primary);
        }
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary);
        }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        .autobid-profile-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 1px solid #e9ecef;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .bids-grid {
            display: grid;
            gap: 1rem;
            margin-top: 1rem;
        }
        .bid-card {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            align-items: center;
            border: 1px solid #eee;
        }
        .bid-vehicle strong { font-size: 1.05rem; color: var(--primary); }
        .bid-amount { font-weight: 700; color: var(--secondary); font-size: 1.1rem; }
        .bid-status { font-weight: 600; text-align: right; }
        .bid-won { color: #27ae60; }
        .bid-leading { color: #f39c12; }
        .bid-lost { color: #e74c3c; }
        .bid-purchase { color: #3498db; }

        .loading-text, .no-bids, .error {
            text-align: center;
            padding: 1.5rem;
            color: #6c757d;
        }
        .error { color: #e74c3c; }

        @media (max-width: 768px) {
            .bid-card {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .bid-status { text-align: center; }
        }
        </style>
        <?php
        return ob_get_clean();
    }
}