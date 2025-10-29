<?php
// includes/class-settings.php
if (!defined('ABSPATH')) {
    exit;
}

class AutoBid_Settings {
    private $option_group = 'autobid_settings_group';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_plugin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // filtros correo nativo
        add_filter('wp_mail_from', [$this, 'filter_mail_from']);
        add_filter('wp_mail_from_name', [$this, 'filter_mail_from_name']);

        // Configurar SMTP automáticamente
        add_action('phpmailer_init', [$this, 'configure_phpmailer']);
    }

    /* ---------- Panel de Ajustes ---------- */
    public function add_plugin_menu() {
        add_submenu_page(
            'edit.php?post_type=vehicle',
            'Ajustes AutoBid Pro',
            'Ajustes',
            'manage_options',
            'autobid-pro-settings',
            [$this, 'render_settings_page_html']
        );
    }


    public function render_settings_page_html() {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_die('Acceso denegado.');
        }

        // Guardar ajustes
        if ($_POST && check_admin_referer('autobid_save_settings', 'autobid_nonce')) {
            // 🟢 COLORES
            update_option('autobid_color_title', sanitize_hex_color($_POST['color_title'] ?? '#1e3c72'));
            update_option('autobid_color_label', sanitize_hex_color($_POST['color_label'] ?? '#333333'));
            update_option('autobid_color_button', sanitize_hex_color($_POST['color_button'] ?? '#1e3c72'));
            update_option('autobid_color_button_hover', sanitize_hex_color($_POST['color_button_hover'] ?? '#162b50'));
            update_option('autobid_color_bg', sanitize_hex_color($_POST['color_bg'] ?? '#ffffff'));
            update_option('autobid_color_input_bg', sanitize_hex_color($_POST['color_input_bg'] ?? '#ffffff'));
            update_option('autobid_color_input_border', sanitize_hex_color($_POST['color_input_border'] ?? '#ced4da'));

            // 🟢 ETIQUETAS Y TEXTOS
            update_option('autobid_label_sale', sanitize_text_field($_POST['label_sale'] ?? 'Venta'));
            update_option('autobid_label_auction', sanitize_text_field($_POST['label_auction'] ?? 'Subasta'));
            update_option('autobid_text_comprar', sanitize_text_field($_POST['text_comprar'] ?? 'Comprar ahora'));
            update_option('autobid_text_pujar', sanitize_text_field($_POST['text_pujar'] ?? 'Pujar ahora'));
            update_option('autobid_text_login_required', sanitize_text_field($_POST['text_login_required'] ?? 'Debes iniciar sesión para continuar.'));

            // 🟢 WHATSAPP
            $whatsapp = preg_replace('/[^0-9+]/', '', sanitize_text_field($_POST['whatsapp_number'] ?? ''));
            update_option('autobid_whatsapp_number', $whatsapp);
            update_option('autobid_whatsapp_message_purchase', sanitize_textarea_field($_POST['whatsapp_message_purchase'] ?? ''));
            update_option('autobid_whatsapp_message_auction', sanitize_textarea_field($_POST['whatsapp_message_auction'] ?? ''));

            // 🟢 CORREO (REMITENTE)
            update_option('autobid_mail_from_email', sanitize_email($_POST['mail_from_email'] ?? ''));
            update_option('autobid_mail_from_name', sanitize_text_field($_POST['mail_from_name'] ?? ''));

            
            // --- SMTP ---
            update_option('autobid_smtp_host', sanitize_text_field($_POST['autobid_smtp_host'] ?? ''));
            update_option('autobid_smtp_port', intval($_POST['autobid_smtp_port'] ?? 587));
            update_option('autobid_smtp_secure', sanitize_text_field($_POST['autobid_smtp_secure'] ?? 'tls'));
            update_option('autobid_smtp_user', sanitize_text_field($_POST['autobid_smtp_user'] ?? ''));
            update_option('autobid_smtp_pass', sanitize_text_field($_POST['autobid_smtp_pass'] ?? ''));
            update_option('autobid_sender_name', sanitize_text_field($_POST['autobid_sender_name'] ?? get_bloginfo('name')));
            update_option('autobid_sender_email', sanitize_email($_POST['autobid_sender_email'] ?? get_option('admin_email')));


            echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Ajustes guardados correctamente.</strong></p></div>';
        }

        // Recuperar valores
        $color_title = get_option('autobid_color_title', '#1e3c72');
        $color_label = get_option('autobid_color_label', '#333333');
        $color_button = get_option('autobid_color_button', '#1e3c72');
        $color_button_hover = get_option('autobid_color_button_hover', '#162b50');
        $color_bg = get_option('autobid_color_bg', '#ffffff');
        $color_input_bg = get_option('autobid_color_input_bg', '#ffffff');
        $color_input_border = get_option('autobid_color_input_border', '#ced4da');
        $label_sale = get_option('autobid_label_sale', 'Venta');
        $label_auction = get_option('autobid_label_auction', 'Subasta');
        $text_comprar = get_option('autobid_text_comprar', 'Comprar ahora');
        $text_pujar = get_option('autobid_text_pujar', 'Pujar ahora');
        $text_login_required = get_option('autobid_text_login_required', 'Debes iniciar sesión para continuar.');
        $whatsapp_number = get_option('autobid_whatsapp_number', '');
        $whatsapp_message_purchase = get_option('autobid_whatsapp_message_purchase', '');
        $whatsapp_message_auction = get_option('autobid_whatsapp_message_auction', '');
        $mail_from_email = get_option('autobid_mail_from_email', get_option('admin_email'));
        $mail_from_name = get_option('autobid_mail_from_name', get_bloginfo('name'));
        $smtp_host = get_option('autobid_smtp_host', '');
        $smtp_port = get_option('autobid_smtp_port', 587);
        $smtp_secure = get_option('autobid_smtp_secure', 'tls');
        $smtp_user = get_option('autobid_smtp_user', '');
        $smtp_pass = get_option('autobid_smtp_pass', '');

        ?>
        <div class="wrap">
            <h1>⚙️ Ajustes AutoBid Pro</h1>
            <form method="post" action="">
                <?php wp_nonce_field('autobid_save_settings', 'autobid_nonce'); ?>

                <!-- Apariencia -->
                <h2>🎨 Apariencia</h2>
                <table class="form-table">
                    <tr><th>Color de Títulos</th><td><input type="color" name="color_title" value="<?php echo esc_attr($color_title); ?>"></td></tr>
                    <tr><th>Color de Etiquetas</th><td><input type="color" name="color_label" value="<?php echo esc_attr($color_label); ?>"></td></tr>
                    <tr><th>Color de Botón</th><td><input type="color" name="color_button" value="<?php echo esc_attr($color_button); ?>"></td></tr>
                    <tr><th>Color Hover Botón</th><td><input type="color" name="color_button_hover" value="<?php echo esc_attr($color_button_hover); ?>"></td></tr>
                </table>

                <h2>🏷️ Etiquetas y Textos</h2>
                <table class="form-table">
                    <tr><th>Etiqueta Venta</th><td><input type="text" name="label_sale" value="<?php echo esc_attr($label_sale); ?>" class="regular-text"></td></tr>
                    <tr><th>Etiqueta Subasta</th><td><input type="text" name="label_auction" value="<?php echo esc_attr($label_auction); ?>" class="regular-text"></td></tr>
                    <tr><th>Texto Comprar</th><td><input type="text" name="text_comprar" value="<?php echo esc_attr($text_comprar); ?>" class="regular-text"></td></tr>
                    <tr><th>Texto Pujar</th><td><input type="text" name="text_pujar" value="<?php echo esc_attr($text_pujar); ?>" class="regular-text"></td></tr>
                </table>

                <h2>💬 WhatsApp</h2>
                <table class="form-table">
                    <tr><th>Número WhatsApp</th><td><input type="text" name="whatsapp_number" value="<?php echo esc_attr($whatsapp_number); ?>" class="regular-text"></td></tr>
                    <tr><th>Mensaje Compra</th><td><textarea name="whatsapp_message_purchase" rows="3" class="large-text"><?php echo esc_textarea($whatsapp_message_purchase); ?></textarea></td></tr>
                    <tr><th>Mensaje Subasta</th><td><textarea name="whatsapp_message_auction" rows="3" class="large-text"><?php echo esc_textarea($whatsapp_message_auction); ?></textarea></td></tr>
                </table>

                <h2>📧 Correo / SMTP</h2>
                <table class="form-table">
                    <tr><th>Remitente (Email)</th><td><input type="email" name="mail_from_email" value="<?php echo esc_attr($mail_from_email); ?>" class="regular-text"></td></tr>
                    <tr><th>Remitente (Nombre)</th><td><input type="text" name="mail_from_name" value="<?php echo esc_attr($mail_from_name); ?>" class="regular-text"></td></tr>
                    <tr><th>SMTP Host</th><td><input type="text" name="smtp_host" value="<?php echo esc_attr($smtp_host); ?>" class="regular-text"></td></tr>
                    <tr><th>SMTP Puerto</th><td><input type="number" name="smtp_port" value="<?php echo esc_attr($smtp_port); ?>" class="small-text"></td></tr>
                    <tr><th>Seguridad (tls/ssl)</th><td><input type="text" name="smtp_secure" value="<?php echo esc_attr($smtp_secure); ?>" class="small-text"></td></tr>
                    <tr><th>Usuario SMTP</th><td><input type="text" name="smtp_user" value="<?php echo esc_attr($smtp_user); ?>" class="regular-text"></td></tr>
                    <tr><th>Contraseña SMTP</th><td><input type="password" name="smtp_pass" value="<?php echo esc_attr($smtp_pass); ?>" class="regular-text"></td></tr>
                </table>

                <h2>📧 Configuración SMTP</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Servidor SMTP (Host)</th>
                        <td><input type="text" name="autobid_smtp_host" value="<?php echo esc_attr(get_option('autobid_smtp_host', 'smtp.tuservidor.com')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Puerto</th>
                        <td><input type="number" name="autobid_smtp_port" value="<?php echo esc_attr(get_option('autobid_smtp_port', 587)); ?>" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Seguridad</th>
                        <td>
                            <select name="autobid_smtp_secure">
                                <?php
                                $selected = get_option('autobid_smtp_secure', 'tls');
                                ?>
                                <option value="tls" <?php selected($selected, 'tls'); ?>>TLS</option>
                                <option value="ssl" <?php selected($selected, 'ssl'); ?>>SSL</option>
                                <option value="">Ninguna</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Usuario SMTP</th>
                        <td><input type="text" name="autobid_smtp_user" value="<?php echo esc_attr(get_option('autobid_smtp_user', '')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Contraseña SMTP</th>
                        <td><input type="password" name="autobid_smtp_pass" value="<?php echo esc_attr(get_option('autobid_smtp_pass', '')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Nombre del Remitente</th>
                        <td><input type="text" name="autobid_sender_name" value="<?php echo esc_attr(get_option('autobid_sender_name', get_bloginfo('name'))); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Correo del Remitente</th>
                        <td><input type="email" name="autobid_sender_email" value="<?php echo esc_attr(get_option('autobid_sender_email', get_option('admin_email'))); ?>" class="regular-text"></td>
                    </tr>
                </table>


                <?php submit_button('Guardar Ajustes'); ?>
            </form>
        </div>
        <?php
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_autobid-pro-settings' && $hook !== 'vehicle_page_autobid-pro-settings') return;
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }

    /* ---------- Filtros correo ---------- */
    public function filter_mail_from($default) {
        $mail = get_option('autobid_mail_from_email', '');
        return (is_email($mail) ? $mail : $default);
    }

    public function filter_mail_from_name($default) {
        $name = get_option('autobid_mail_from_name', '');
        return ($name ? sanitize_text_field($name) : $default);
    }

    /* ---------- Configurar SMTP ---------- */
    public function configure_phpmailer($phpmailer) {
        $host = get_option('autobid_smtp_host', '');
        $port = get_option('autobid_smtp_port', '');
        $secure = get_option('autobid_smtp_secure', 'tls');
        $user = get_option('autobid_smtp_user', '');
        $pass = get_option('autobid_smtp_pass', '');

        if ($host && $user && $pass) {
            $phpmailer->isSMTP();
            $phpmailer->Host = $host;
            $phpmailer->Port = intval($port ?: 587);
            $phpmailer->SMTPSecure = $secure ?: 'tls';
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $user;
            $phpmailer->Password = $pass;
            $phpmailer->setFrom(
                get_option('autobid_mail_from_email', $user),
                get_option('autobid_mail_from_name', get_bloginfo('name'))
            );
        }
    }

    /* ---------- Plantilla de correo corporativa ---------- */
    public static function autobid_build_email($subject, $body_html) {
        $logo_url = get_option('autobid_logo_url', get_site_icon_url());
        $primary = get_option('autobid_color_button', '#1e3c72');
        $accent = get_option('autobid_color_button_hover', '#162b50');
        $footer = "© " . date('Y') . ' ' . get_bloginfo('name') . '. Todos los derechos reservados.';

        return "
        <html><body style='font-family:Arial,sans-serif;background:#f5f5f5;padding:20px;'>
        <table align='center' width='600' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);'>
        <tr><td style='background:{$primary};padding:15px;text-align:center;'>
        " . ($logo_url ? "<img src='{$logo_url}' alt='Logo' style='max-height:60px;'/>" : "<h2 style='color:white;margin:0;'>".get_bloginfo('name')."</h2>") . "
        </td></tr>
        <tr><td style='padding:20px;color:#333;'>{$body_html}</td></tr>
        <tr><td style='background:#f1f1f1;padding:10px;text-align:center;color:#666;font-size:13px;'>{$footer}</td></tr>
        </table></body></html>";
    }
}

// Instanciar clase
new AutoBid_Settings();

// Helper global
if (!function_exists('autobid_build_email')) {
    function autobid_build_email($subject, $body_html) {
        return AutoBid_Settings::autobid_build_email($subject, $body_html);
    }
}
