<?php
// includes/class-frontend-auth.php

class AutoBid_Frontend_Auth {

    public function __construct() {
        add_action('init', [$this, 'handle_auth_requests']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_auth_scripts']);
        add_shortcode('autobid_login', [$this, 'login_shortcode']);
        add_shortcode('autobid_register', [$this, 'register_shortcode']);
        add_shortcode('autobid_logout', [$this, 'logout_shortcode']);
        add_action('wp_ajax_nopriv_check_username', [$this, 'ajax_check_username']);
        add_action('wp_ajax_check_username', [$this, 'ajax_check_username']); // También para usuarios logueados
    }

    public function enqueue_auth_scripts() {
        // Solo en páginas que contengan shortcodes de autenticación
        if (is_page() && (has_shortcode(get_post()->post_content, 'autobid_login') ||
                          has_shortcode(get_post()->post_content, 'autobid_register'))) {
            wp_enqueue_script('autobid-frontend-auth', plugin_dir_url(dirname(__FILE__)) . 'public/js/frontend-auth.js', ['jquery'], '1.0', true);
            wp_localize_script('autobid-frontend-auth', 'autobid_auth_vars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('autobid_auth_nonce'),
                'login_url' => wp_login_url(),
                'home_url' => home_url()
            ]);
        }
    }

    public function handle_auth_requests() {
        if (isset($_POST['autobid_action']) && isset($_POST['autobid_nonce'])) {
            if (!wp_verify_nonce($_POST['autobid_nonce'], 'autobid_auth_nonce')) {
                wp_die('No autorizado.');
            }

            if ($_POST['autobid_action'] === 'login' && !is_user_logged_in()) {
                $this->process_login();
            } elseif ($_POST['autobid_action'] === 'register' && !is_user_logged_in()) {
                $this->process_registration();
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
            wp_redirect(add_query_arg('login_error', urlencode($user->get_error_message()), wp_get_referer()));
        } else {
            $redirect_to = $_POST['redirect_to'] ?? home_url();
            wp_redirect($redirect_to);
        }
        exit;
    }

    private function process_registration() {
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];

        if (username_exists($username)) {
            wp_redirect(add_query_arg('reg_error', urlencode('El nombre de usuario ya existe.'), wp_get_referer()));
            exit;
        }

        if (email_exists($email)) {
            wp_redirect(add_query_arg('reg_error', urlencode('La dirección de correo electrónico ya está registrada.'), wp_get_referer()));
            exit;
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_redirect(add_query_arg('reg_error', urlencode($user_id->get_error_message()), wp_get_referer()));
            exit;
        }

        // Opcional: Enviar correo de bienvenida
        wp_new_user_notification($user_id, null, 'both');

        // Opcional: Auto-login después del registro
        $user = wp_signon(['user_login' => $username, 'user_password' => $password], false);
        if (!is_wp_error($user)) {
            $redirect_to = $_POST['redirect_to'] ?? home_url();
            wp_redirect($redirect_to);
        } else {
             // Si auto-login falla, redirigir al login
             wp_redirect(add_query_arg('login_error', urlencode('Registro exitoso, pero error al iniciar sesión.'), wp_login_url()));
        }
        exit;
    }

    public function login_shortcode($atts) {
        $atts = shortcode_atts([
            'redirect' => home_url()
        ], $atts);

        $error_message = '';
        if (isset($_GET['login_error'])) {
            $error_message = '<div class="autobid-auth-message error">' . esc_html($_GET['login_error']) . '</div>';
        }

        if (is_user_logged_in()) {
            return '<div class="autobid-auth-message info">Ya has iniciado sesión.</div>';
        }

        ob_start();
        ?>
        <div class="autobid-auth-container">
            <h3>Iniciar Sesión</h3>
            <?php echo $error_message; ?>
            <form method="post">
                <input type="hidden" name="autobid_action" value="login">
                <input type="hidden" name="autobid_nonce" value="<?php echo wp_create_nonce('autobid_auth_nonce'); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($atts['redirect']); ?>">
                <p>
                    <label for="autobid_login_username">Usuario o Email</label>
                    <input type="text" name="username" id="autobid_login_username" required>
                </p>
                <p>
                    <label for="autobid_login_password">Contraseña</label>
                    <input type="password" name="password" id="autobid_login_password" required>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="remember"> Recordarme
                    </label>
                </p>
                <p>
                    <button type="submit">Iniciar Sesión</button>
                </p>
            </form>
            <p><a href="<?php echo wp_lostpassword_url(); ?>">¿Olvidaste tu contraseña?</a></p>
            <p>¿No tienes una cuenta? <a href="<?php echo get_permalink(get_option('autobid_register_page_id')); ?>">Regístrate aquí</a>.</p>
        </div>
        <?php
        return ob_get_clean();
    }

    public function register_shortcode($atts) {
        $atts = shortcode_atts([
            'redirect' => home_url()
        ], $atts);

        $error_message = '';
        if (isset($_GET['reg_error'])) {
            $error_message = '<div class="autobid-auth-message error">' . esc_html($_GET['reg_error']) . '</div>';
        }

        if (is_user_logged_in()) {
            return '<div class="autobid-auth-message info">Ya has iniciado sesión.</div>';
        }

        ob_start();
        ?>
        <div class="autobid-auth-container">
            <h3>Registrarse</h3>
            <?php echo $error_message; ?>
            <form method="post" id="autobid-register-form">
                <input type="hidden" name="autobid_action" value="register">
                <input type="hidden" name="autobid_nonce" value="<?php echo wp_create_nonce('autobid_auth_nonce'); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($atts['redirect']); ?>">
                <p>
                    <label for="autobid_reg_username">Usuario *</label>
                    <input type="text" name="username" id="autobid_reg_username" required>
                    <span class="username-check-result" id="username-check-result"></span>
                </p>
                <p>
                    <label for="autobid_reg_email">Email *</label>
                    <input type="email" name="email" id="autobid_reg_email" required>
                </p>
                <p>
                    <label for="autobid_reg_password">Contraseña *</label>
                    <input type="password" name="password" id="autobid_reg_password" required>
                </p>
                <p>
                    <button type="submit">Registrarse</button>
                </p>
            </form>
            <p>¿Ya tienes una cuenta? <a href="<?php echo get_permalink(get_option('autobid_login_page_id')); ?>">Inicia sesión aquí</a>.</p>
        </div>
        <?php
        return ob_get_clean();
    }

    public function logout_shortcode($atts) {
        $atts = shortcode_atts([
            'redirect' => home_url()
        ], $atts);

        if (!is_user_logged_in()) {
            return '<div class="autobid-auth-message info">No has iniciado sesión.</div>';
        }

        $logout_url = wp_logout_url($atts['redirect']);
        return '<a href="' . esc_url($logout_url) . '">Cerrar Sesión</a>';
    }

    public function ajax_check_username() {
        check_ajax_referer('autobid_auth_nonce', 'nonce');

        $username = sanitize_user($_POST['username']);

        $response = ['valid' => true, 'message' => ''];

        if (empty($username)) {
            $response['valid'] = false;
            $response['message'] = 'El nombre de usuario es obligatorio.';
        } elseif (strlen($username) < 4) {
            $response['valid'] = false;
            $response['message'] = 'El nombre de usuario debe tener al menos 4 caracteres.';
        } elseif (username_exists($username)) {
            $response['valid'] = false;
            $response['message'] = 'El nombre de usuario ya está en uso.';
        }

        wp_send_json($response);
    }
}