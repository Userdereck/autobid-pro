<?php
// includes/class-auth.php

if (!defined('ABSPATH')) {
    exit; // Salir si se accede directamente
}

class AutoBid_Auth {

    public function __construct() {
        add_action('init', [$this, 'create_auth_pages']);
        add_action('init', [$this, 'handle_auth_requests']);
        add_action('init', [$this, 'create_vehicle_user_role']); // <-- Nuevo hook para crear rol
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
        // Página de Login
        $login_page = get_page_by_title('Login');
        if (!$login_page) {
            $login_id = wp_insert_post([
                'post_title'   => 'Login',
                'post_content' => '[autobid_login]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => 'login'
            ]);
        } else {
            $login_id = $login_page->ID;
        }

        // Página de Registro
        $register_page = get_page_by_title('Registro');
        if (!$register_page) {
            $register_id = wp_insert_post([
                'post_title'   => 'Registro',
                'post_content' => '[autobid_register]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => 'register'
            ]);
        } else {
            $register_id = $register_page->ID;
        }

        // Página de Perfil
        $profile_page = get_page_by_title('Mi Perfil');
        if (!$profile_page) {
            $profile_id = wp_insert_post([
                'post_title'   => 'Mi Perfil',
                'post_content' => '[autobid_profile]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => 'profile'
            ]);
        } else {
            $profile_id = $profile_page->ID;
        }

        update_option('autobid_login_page_id', $login_id);
        update_option('autobid_register_page_id', $register_id);
        update_option('autobid_profile_page_id', $profile_id);
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

        if (empty($username) || empty($email) || empty($password)) {
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

        // Asignar rol 'vehicle_user' al nuevo usuario
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
        $profile_updated = isset($_GET['profile_updated']) && $_GET['profile_updated'] === 'true';
        $profile_error = isset($_GET['profile_error']) ? $_GET['profile_error'] : '';

        ob_start();
        ?>
        <div class="autobid-auth-container autobid-profile-container">
            <h2>Mi Perfil</h2>
            <?php if ($profile_updated): ?>
                <div class="autobid-auth-message success">Perfil actualizado correctamente.</div>
            <?php endif; ?>
            <?php if ($profile_error): ?>
                <div class="autobid-auth-message error"><?php echo esc_html($profile_error); ?></div>
            <?php endif; ?>

            <div class="autobid-profile-info">
                <h3>Información del Usuario</h3>
                <p><strong>Nombre de Usuario:</strong> <?php echo esc_html($current_user->user_login); ?></p>
                <p><strong>Correo Electrónico:</strong> <?php echo esc_html($current_user->user_email); ?></p>
                <p><strong>Nombre:</strong> <?php echo esc_html($current_user->first_name); ?></p>
                <p><strong>Apellido:</strong> <?php echo esc_html($current_user->last_name); ?></p>
                <p><strong>Fecha de Registro:</strong> <?php echo esc_html($current_user->user_registered); ?></p>
                <p><strong>Rol:</strong> <?php echo esc_html(implode(', ', $current_user->roles)); ?></p> <!-- Mostrar rol -->
            </div>

            <form method="post" class="autobid-auth-form autobid-profile-form">
                <?php wp_nonce_field('autobid_update_profile_action', 'autobid_update_profile_nonce'); ?>
                <h3>Actualizar Información</h3>
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

            <form method="post" action="<?php echo esc_url(wp_logout_url(home_url('/login/'))); ?>" class="autobid-logout-form">
                <button type="submit" class="autobid-auth-button logout">Cerrar Sesión</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}