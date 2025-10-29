<?php
// includes/class-api.php
if (!defined('ABSPATH')) exit;

class AutoBid_API {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('autobid/v1', '/vehicles', [
            'methods' => 'GET',
            'callback' => [$this, 'get_vehicles'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('autobid/v1', '/vehicles/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_dashboard_stats'],
            'permission_callback' => [$this, 'check_admin_access']
        ]);

        register_rest_route('autobid/v1', '/vehicles', [
            'methods' => 'POST',
            'callback' => [$this, 'create_vehicle'],
            'permission_callback' => [$this, 'check_admin_access']
        ]);

        register_rest_route('autobid/v1', '/vehicles/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_vehicle'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('autobid/v1', '/vehicles/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_vehicle'],
            'permission_callback' => [$this, 'check_admin_access']
        ]);

        register_rest_route('autobid/v1', '/vehicles/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_vehicle'],
            'permission_callback' => [$this, 'check_admin_access']
        ]);

        register_rest_route('autobid/v1', '/vehicles/(?P<id>\d+)/bid', [
            'methods' => 'POST',
            'callback' => [$this, 'place_bid'],
            'permission_callback' => [$this, 'check_user_logged_in_and_authorized']
        ]);

        register_rest_route('autobid/v1', '/bids', [
            'methods' => 'GET',
            'callback' => [$this, 'get_all_bids'],
            'permission_callback' => [$this, 'check_admin_access']
        ]);

        register_rest_route('autobid/v1', '/sales', [
            'methods' => 'GET',
            'callback' => [$this, 'get_all_sales'],
            'permission_callback' => [$this, 'check_admin_access']
        ]);

        register_rest_route('autobid/v1', '/vehicles/(?P<id>\d+)/purchase', [
            'methods' => 'POST',
            'callback' => [$this, 'purchase_vehicle'],
            'permission_callback' => [$this, 'check_user_logged_in_and_authorized']
        ]);

        register_rest_route('autobid/v1', '/my-bids', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_bids'],
            'permission_callback' => [$this, 'check_user_logged_in_and_authorized']
        ]);

        register_rest_route('autobid/v1', '/vehicles/(?P<id>\d+)/watch', [
            'methods' => 'POST',
            'callback' => [$this, 'add_to_watchlist'],
            'permission_callback' => [$this, 'check_user_logged_in_and_authorized']
        ]);
    }

    /* ---------- Utilities & helpers ---------- */

    private function get_admin_email() {
        $admin_email = get_option('admin_email');
        return $admin_email ?: get_bloginfo('admin_email');
    }

    // Envía correo HTML con headers correctos
    private function send_email_html($to, $subject, $html_body) {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . $this->get_admin_email() . '>'
        ];
        return wp_mail($to, $subject, $html_body, $headers);
    }

    // Plantilla HTML simple y profesional (puedes personalizar)
    private function build_email_html_template($title, $intro_html, $body_html, $cta_url = '', $cta_text = '') {
        $site_name = get_bloginfo('name');
        $logo = get_option('autobid_email_logo_url', ''); // opción que puedes definir en ajustes
        $primary_color = get_option('autobid_email_primary_color', '#1e3c72');

        $logo_html = $logo ? "<img src=\"" . esc_url($logo) . "\" alt=\"" . esc_attr($site_name) . "\" style=\"max-height:60px;\">"
                           : "<h2 style=\"margin:0;color:{$primary_color}\">" . esc_html($site_name) . "</h2>";

        $cta = '';
        if ($cta_url && $cta_text) {
            $cta = "<p style=\"text-align:center;margin:20px 0;\"><a href=\"" . esc_url($cta_url) . "\" style=\"background:{$primary_color};color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;\">"
                 . esc_html($cta_text) . "</a></p>";
        }

        $html = '
        <div style="font-family:Arial,Helvetica,sans-serif;max-width:700px;margin:0 auto;border:1px solid #e6e9ee;border-radius:8px;overflow:hidden;">
            <div style="padding:18px 24px;background:#fff;border-bottom:4px solid '.$primary_color.';display:flex;align-items:center;gap:12px;">
                '.$logo_html.'
                <div style="margin-left:auto;color:#6b7280;font-size:14px;">'.$site_name.'</div>
            </div>
            <div style="padding:24px;background:#fff;color:#111;">
                <h1 style="font-size:20px;margin:0 0 12px 0;color:'.$primary_color.';">'.esc_html($title).'</h1>
                <div style="font-size:14px;color:#374151;margin-bottom:12px;">'.$intro_html.'</div>
                <div style="font-size:14px;color:#111;line-height:1.5;">'.$body_html.'</div>
                '.$cta.'
            </div>
            <div style="padding:14px 24px;background:#f8fafc;color:#6b7280;font-size:12px;">
                <div>Saludos,<br><strong>'.esc_html($site_name).'</strong></div>
                <div style="margin-top:6px;">Este mensaje fue enviado automáticamente por el sistema de notificaciones.</div>
            </div>
        </div>';
        return $html;
    }

    /* ---------- Routes helpers ---------- */

    public function add_to_watchlist($request) {
        $vehicle_id = (int) $request['id'];
        $user_id = get_current_user_id();
        $vehicle = get_post($vehicle_id);
        if (!$vehicle || $vehicle->post_type !== 'vehicle') {
            return new WP_Error('invalid_vehicle', 'Vehículo no válido.', ['status' => 400]);
        }
        $type = get_post_meta($vehicle_id, '_type', true);
        if ($type !== 'subasta') {
            return new WP_Error('invalid_type', 'Solo se puede vigilar subastas.', ['status' => 400]);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'autobid_auction_watchlist';
        $existing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE vehicle_id = %d AND user_id = %d", $vehicle_id, $user_id));
        if ($existing) {
            return new WP_REST_Response(['success' => true, 'message' => 'Ya estás en la lista de seguimiento.'], 200);
        }
        $result = $wpdb->insert($table, [
            'vehicle_id' => $vehicle_id,
            'user_id'    => $user_id,
            'notified'   => 0,
            'created_at' => current_time('mysql')
        ], ['%d','%d','%d','%s']);

        if ($result === false) {
            return new WP_Error('db_error', 'Error al registrar tu interés.', ['status' => 500]);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => '¡Te notificaremos cuando la subasta comience!'
        ], 200);
    }

    public function check_admin_access() {
        return current_user_can('administrator');
    }

    public function get_user_bids($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('unauthorized', 'Usuario no autenticado.', ['status' => 401]);
        }

        global $wpdb;
        $bids = $wpdb->get_results($wpdb->prepare("
            SELECT b.*, v.post_title as vehicle_title, v.ID as vehicle_id
            FROM {$wpdb->prefix}autobid_bids b
            LEFT JOIN {$wpdb->posts} v ON b.vehicle_id = v.ID
            WHERE b.user_id = %d
            ORDER BY b.created_at DESC
        ", $user_id));

        $enriched = array_map(function($bid) use ($user_id) {
            $vehicle_id = $bid->vehicle_id;
            $type = get_post_meta($vehicle_id, '_type', true);

            if ($type !== 'subasta') {
                return array_merge((array)$bid, ['status' => 'compra']);
            }

            $highest_bidder = get_post_meta($vehicle_id, '_highest_bidder', true);
            $auction_status = get_post_meta($vehicle_id, '_auction_status', true);
            error_log("AutoBid: Puja ID {$bid->id}, Usuario: {$user_id}, Highest: {$highest_bidder}, Status: {$auction_status}");

            $is_highest = ((string)$highest_bidder === (string)$user_id);
            if ($auction_status === 'closed') {
                $status = $is_highest ? 'ganadora' : 'perdedora';
            } else {
                $status = $is_highest ? 'líder' : 'superada';
            }

            return array_merge((array)$bid, ['status' => $status]);
        }, $bids);

        return new WP_REST_Response($enriched, 200);
    }

    public function purchase_vehicle($request) {
        $vehicle_id = (int) $request['id'];
        $user_id = get_current_user_id();
        $vehicle = get_post($vehicle_id);
        if (!$vehicle || $vehicle->post_type !== 'vehicle') {
            return new WP_Error('invalid_vehicle', 'Vehículo no válido.', ['status' => 400]);
        }

        $type = get_post_meta($vehicle_id, '_type', true);
        if ($type !== 'venta') {
            return new WP_Error('invalid_type', 'La acción "Comprar ahora" solo está disponible para ventas directas.', ['status' => 400]);
        }

        // Build WhatsApp URL for admin and optionally for buyer (we return URLs for frontend to open)
        $admin_whatsapp = get_option('autobid_whatsapp_number', '');
        $admin_whatsapp_url = null;
        $user_whatsapp_url = null;

        $user = get_userdata($user_id);
        $vehicle_title = $vehicle->post_title;
        $vehicle_url = get_permalink($vehicle_id);
        $site_name = get_bloginfo('name');

        // Admin URL
        if (!empty($admin_whatsapp)) {
            $msg_admin = "📩 *Solicitud de compra en {$site_name}*\n\n" .
                         "Vehículo: *{$vehicle_title}* (ID: {$vehicle_id})\n" .
                         "Comprador: *{$user->display_name}* (ID: {$user_id})\n" .
                         "Email: {$user->user_email}\n" .
                         "Ver: " . $vehicle_url;
            $admin_whatsapp_url = "https://wa.me/".rawurlencode($admin_whatsapp)."?text=".rawurlencode($msg_admin);
        }

        // User URL (optional: admin number as recipient so user can send directly)
        $admin_number_for_user = $admin_whatsapp;
        if (!empty($admin_number_for_user)) {
            $msg_user = "Hola, soy {$user->display_name}. Estoy interesado en comprar el vehículo \"{$vehicle_title}\" (ID: {$vehicle_id}) publicado en {$site_name}.";
            $user_whatsapp_url = "https://wa.me/".rawurlencode($admin_number_for_user)."?text=".rawurlencode($msg_user);
        }

        // Send emails: admin and user
        $admin_email = $this->get_admin_email();
        $subject_admin = "Nueva solicitud de compra: {$vehicle_title} (ID {$vehicle_id})";
        $intro_admin = "Se ha generado una nueva solicitud de compra desde el frontend.";
        $body_admin = "<p><strong>Vehículo:</strong> {$vehicle_title} (ID: {$vehicle_id})</p>";
        $body_admin .= "<p><strong>Comprador:</strong> {$user->display_name} (ID: {$user_id})</p>";
        $body_admin .= "<p><strong>Email:</strong> {$user->user_email}</p>";
        $body_admin .= "<p><strong>Enlace:</strong> <a href=\"{$vehicle_url}\">Ver vehículo</a></p>";
        if ($admin_whatsapp_url) {
            $body_admin .= "<p>WhatsApp: <a href=\"{$admin_whatsapp_url}\" target=\"_blank\">Abrir chat</a></p>";
        }
        $html_admin = $this->build_email_html_template($subject_admin, $intro_admin, $body_admin, $vehicle_url, 'Ver vehículo');
        $this->send_email_html($admin_email, $subject_admin, $html_admin);

        // Email to buyer
        $subject_user = "Solicitud de compra recibida — {$vehicle_title}";
        $intro_user = "Hemos recibido tu solicitud para comprar el vehículo. Nuestro equipo se contactará contigo a la brevedad.";
        $body_user = "<p><strong>Vehículo:</strong> {$vehicle_title} (ID: {$vehicle_id})</p>";
        $body_user .= "<p><strong>Precio:</strong> " . (float) get_post_meta($vehicle_id, '_price', true) . "</p>";
        $body_user .= "<p><strong>Enlace:</strong> <a href=\"{$vehicle_url}\">Ver vehículo</a></p>";
        if ($user_whatsapp_url) {
            $body_user .= "<p>Contactar por WhatsApp al vendedor/administrador: <a href=\"{$user_whatsapp_url}\" target=\"_blank\">Abrir chat</a></p>";
        }
        $html_user = $this->build_email_html_template($subject_user, $intro_user, $body_user, $vehicle_url, 'Ver vehículo');
        $this->send_email_html($user->user_email, $subject_user, $html_user);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Solicitud de compra registrada. Se ha notificado por correo.',
            'whatsapp_admin_url' => $admin_whatsapp_url,
            'whatsapp_user_url'  => $user_whatsapp_url
        ], 200);
    }

    /* ---------- create_vehicle / update_vehicle / delete_vehicle (idénticas a su lógica previa, omitidas aquí por brevedad) ---------- */
    /* Para no romper nada, incluyo las funciones completas a continuación.
       (He conservado exactamente tu lógica de create/update/delete tal como la tenías,
        solo asegurándome que no existan llamadas obsoletas a wp_remote_get). */

    public function create_vehicle($request) {
        if (!current_user_can('administrator')) {
            return new WP_Error('forbidden', 'Acceso denegado. Requiere permisos de administrador.', ['status' => 403]);
        }
        $params = $request->get_params();
        $title = sanitize_text_field($params['title'] ?? '');
        $content = sanitize_textarea_field($params['content'] ?? '');
        $type = sanitize_text_field($params['type'] ?? 'venta');
        $price = floatval($params['price'] ?? 0);
        $currency = sanitize_text_field($params['currency'] ?? 'USD');
        $brand = sanitize_text_field($params['brand'] ?? '');
        $model = sanitize_text_field($params['model'] ?? '');
        $year = intval($params['year'] ?? 0);
        $color = sanitize_text_field($params['color'] ?? '');
        $condition = sanitize_text_field($params['condition'] ?? 'usado');
        $location = sanitize_text_field($params['location'] ?? '');
        $featured = isset($params['featured']) ? '1' : '0';
        $start_time = sanitize_text_field($params['start_time'] ?? '');
        $end_time = sanitize_text_field($params['end_time'] ?? '');

        if (empty($title)) {
            return new WP_Error('invalid_data', 'El título es obligatorio.', ['status' => 400]);
        }

        if ($type === 'subasta') {
            if (empty($end_time)) {
                return new WP_Error('invalid_data', 'La fecha de fin es obligatoria para subastas.', ['status' => 400]);
            }
            if ($start_time) {
                $dt_start = DateTime::createFromFormat('Y-m-d\TH:i', $start_time);
                if (!$dt_start) {
                    return new WP_Error('invalid_data', 'Formato de fecha de inicio inválido.', ['status' => 400]);
                }
            }
            $dt_end = DateTime::createFromFormat('Y-m-d\TH:i', $end_time);
            if (!$dt_end) {
                return new WP_Error('invalid_data', 'Formato de fecha de fin inválido.', ['status' => 400]);
            }
        }

        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_type' => 'vehicle',
            'post_status' => 'publish'
        ]);

        if (is_wp_error($post_id)) {
            return new WP_Error('db_error', 'Error al crear el vehículo en la base de datos: ' . $post_id->get_error_message(), ['status' => 500]);
        }

        update_post_meta($post_id, '_type', $type);
        if ($price > 0) update_post_meta($post_id, '_price', $price);
        update_post_meta($post_id, '_currency', $currency);
        if ($brand) update_post_meta($post_id, '_brand', $brand);
        if ($model) update_post_meta($post_id, '_model', $model);
        if ($year > 0) update_post_meta($post_id, '_year', $year);
        if ($color) update_post_meta($post_id, '_color', $color);
        update_post_meta($post_id, '_condition', $condition);
        if ($location) update_post_meta($post_id, '_location', $location);
        update_post_meta($post_id, '_featured', $featured);

        if ($type === 'subasta') {
            if ($start_time) {
                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $start_time);
                if ($dt) update_post_meta($post_id, '_start_time', $dt->format('Y-m-d H:i:s'));
            }
            if ($end_time) {
                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $end_time);
                if ($dt) update_post_meta($post_id, '_end_time', $dt->format('Y-m-d H:i:s'));
            }
            $current_time = current_time('Y-m-d H:i:s');
            $start_time_obj = $start_time ? new DateTime($start_time) : new DateTime('1970-01-01 00:00:00');
            $end_time_obj = $end_time ? new DateTime($end_time) : new DateTime('2038-01-19 03:14:07');
            $current_time_obj = new DateTime($current_time);

            if ($current_time_obj < $start_time_obj) {
                $status = 'upcoming';
            } elseif ($current_time_obj > $end_time_obj) {
                $status = 'closed';
            } else {
                $status = 'live';
            }
            update_post_meta($post_id, '_auction_status', $status);
        }

        // Gallery handling (same logic you had)
        if (isset($_FILES['gallery']) && !empty($_FILES['gallery']['name'][0])) {
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            $gallery_ids = [];
            $upload_errors = [];
            $file_count = count($_FILES['gallery']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['gallery']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_to_upload = [
                        'name'     => $_FILES['gallery']['name'][$i],
                        'type'     => $_FILES['gallery']['type'][$i],
                        'tmp_name' => $_FILES['gallery']['tmp_name'][$i],
                        'error'    => $_FILES['gallery']['error'][$i],
                        'size'     => $_FILES['gallery']['size'][$i],
                    ];
                    $upload = wp_handle_upload($file_to_upload, ['test_form' => false]);
                    if (!isset($upload['error']) && isset($upload['file'])) {
                        $filename = $upload['file'];
                        $wp_filetype = wp_check_filetype(basename($filename), null);
                        $attachment = [
                            'post_mime_type' => $wp_filetype['type'],
                            'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
                            'post_content' => '',
                            'post_status' => 'inherit'
                        ];
                        $attach_id = wp_insert_attachment($attachment, $filename, $post_id);
                        if (!is_wp_error($attach_id)) {
                            if (!function_exists('wp_generate_attachment_metadata')) {
                                require_once(ABSPATH . 'wp-admin/includes/image.php');
                            }
                            $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
                            wp_update_attachment_metadata($attach_id, $attach_data);
                            $gallery_ids[] = $attach_id;
                        } else {
                            $upload_errors[] = "Error al adjuntar imagen " . $_FILES['gallery']['name'][$i] . ": " . $attach_id->get_error_message();
                        }
                    } else {
                        $upload_errors[] = "Error al subir imagen " . $_FILES['gallery']['name'][$i] . ": " . ($upload['error'] ?? 'Error desconocido');
                    }
                } else {
                    $error_code = $_FILES['gallery']['error'][$i];
                    $error_message = "Error desconocido";
                    switch ($error_code) {
                        case UPLOAD_ERR_INI_SIZE: $error_message = "El archivo excede el tamaño máximo permitido por upload_max_filesize en php.ini."; break;
                        case UPLOAD_ERR_FORM_SIZE: $error_message = "El archivo excede el tamaño máximo permitido por MAX_FILE_SIZE en el formulario."; break;
                        case UPLOAD_ERR_PARTIAL: $error_message = "El archivo fue solo parcialmente subido."; break;
                        case UPLOAD_ERR_NO_FILE: $error_message = "No se subió ningún archivo."; break;
                        case UPLOAD_ERR_NO_TMP_DIR: $error_message = "Falta la carpeta temporal."; break;
                        case UPLOAD_ERR_CANT_WRITE: $error_message = "Falló la escritura del archivo en disco."; break;
                        case UPLOAD_ERR_EXTENSION: $error_message = "Una extensión de PHP detuvo la subida del archivo."; break;
                    }
                    $upload_errors[] = "Error de archivo " . $_FILES['gallery']['name'][$i] . " (Código: $error_code): " . $error_message;
                }
            }
            if (!empty($gallery_ids)) {
                update_post_meta($post_id, '_vehicle_gallery', implode(',', $gallery_ids));
            }
            if (!empty($upload_errors)) {
                error_log("AutoBid Pro: Errores de subida de galería para vehículo ID {$post_id}: " . implode(', ', $upload_errors));
            }
        }

        $new_vehicle = $this->format_vehicle(get_post($post_id));
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Vehículo creado exitosamente.',
            'vehicle' => $new_vehicle
        ], 201);
    }

    public function update_vehicle($request) {
        if (!current_user_can('administrator')) {
            return new WP_Error('forbidden', 'Acceso denegado. Requiere permisos de administrador.', ['status' => 403]);
        }

        $id = (int) $request['id'];
        $post = get_post($id);
        if (!$post || $post->post_type !== 'vehicle') {
            return new WP_Error('not_found', 'Vehículo no encontrado.', ['status' => 404]);
        }

        $params = $request->get_params();
        error_log("AutoBid Pro API (update_vehicle): Parámetros RAW recibidos para ID {$id}: " . print_r($params, true));

        $title = sanitize_text_field($params['title'] ?? $post->post_title);
        $content = sanitize_textarea_field($params['content'] ?? $post->post_content);
        $type = sanitize_text_field($params['type'] ?? get_post_meta($id, '_type', true));
        $price = floatval($params['price'] ?? get_post_meta($id, '_price', true));
        $currency = sanitize_text_field($params['currency'] ?? get_post_meta($id, '_currency', true));
        $brand = sanitize_text_field($params['brand'] ?? get_post_meta($id, '_brand', true));
        $model = sanitize_text_field($params['model'] ?? get_post_meta($id, '_model', true));
        $year = intval($params['year'] ?? get_post_meta($id, '_year', true));
        $color = sanitize_text_field($params['color'] ?? get_post_meta($id, '_color', true));
        $condition = sanitize_text_field($params['condition'] ?? get_post_meta($id, '_condition', true));
        $location = sanitize_text_field($params['location'] ?? get_post_meta($id, '_location', true));
        $featured = isset($params['featured']) ? '1' : '0';

        $raw_start_time = $params['start_time'] ?? get_post_meta($id, '_start_time', true);
        $raw_end_time   = $params['end_time'] ?? get_post_meta($id, '_end_time', true);
        error_log("AutoBid Pro API (update_vehicle): Valores crudos de fechas para ID {$id}: start_time='{$raw_start_time}', end_time='{$raw_end_time}'");

        $start_time = null;
        $end_time = null;

        if ($type === 'subasta') {
            if (empty($raw_end_time)) {
                return new WP_Error('invalid_data', 'La fecha de fin es obligatoria para subastas.', ['status' => 400]);
            }
            $validate_and_format_datetime = function($datetime_str, $field_key, $expected_label) {
                if (empty($datetime_str)) return ['valid' => true, 'value' => null, 'field_key' => $field_key];
                $clean = trim(strval($datetime_str));
                if (empty($clean)) return ['valid' => true, 'value' => null, 'field_key' => $field_key];
                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $clean);
                if (!$dt) $dt = DateTime::createFromFormat('Y-m-d H:i:s', $clean);
                if (!$dt) $dt = DateTime::createFromFormat('Y-m-d H:i', $clean);
                if (!$dt) {
                    error_log("AutoBid Pro API (update_vehicle): Error al validar {$expected_label}. Valor: '{$clean}'");
                    return ['valid' => false, 'value' => null, 'field_key' => $field_key, 'error' => "Formato de {$expected_label} inválido. Use YYYY-MM-DDTHH:MM o YYYY-MM-DD HH:MM."];
                }
                return ['valid' => true, 'value' => $dt->format('Y-m-d H:i:s'), 'field_key' => $field_key];
            };

            $end_validation = $validate_and_format_datetime($raw_end_time, 'end_time', 'fecha de fin');
            if (!$end_validation['valid']) {
                return new WP_Error('invalid_data', $end_validation['error'], ['status' => 400]);
            }
            $end_time = $end_validation['value'];

            $start_validation = $validate_and_format_datetime($raw_start_time, 'start_time', 'fecha de inicio');
            if (!$start_validation['valid']) {
                return new WP_Error('invalid_data', $start_validation['error'], ['status' => 400]);
            }
            $start_time = $start_validation['value'];
        } else {
            $raw_start_time = null;
            $raw_end_time = null;
            $start_time = null;
            $end_time = null;
        }

        error_log("AutoBid Pro API (update_vehicle): Parámetros sanitizados para ID {$id}: title='{$title}', type='{$type}', price={$price}");

        $updated_post_id = wp_update_post([
            'ID' => $id,
            'post_title' => $title,
            'post_content' => $content,
        ], true);

        if (is_wp_error($updated_post_id)) {
            error_log("AutoBid Pro API (update_vehicle): Error al actualizar post ID {$id}: " . $updated_post_id->get_error_message());
            return new WP_Error('db_error', 'Error al actualizar el vehículo: ' . $updated_post_id->get_error_message(), ['status' => 500]);
        }

        // Reset metas to avoid duplicates
        delete_post_meta($id, '_type');
        delete_post_meta($id, '_price');
        delete_post_meta($id, '_currency');
        delete_post_meta($id, '_brand');
        delete_post_meta($id, '_model');
        delete_post_meta($id, '_year');
        delete_post_meta($id, '_color');
        delete_post_meta($id, '_condition');
        delete_post_meta($id, '_location');
        delete_post_meta($id, '_featured');
        delete_post_meta($id, '_start_time');
        delete_post_meta($id, '_end_time');
        delete_post_meta($id, '_auction_status');

        update_post_meta($id, '_type', $type);
        if ($price > 0) update_post_meta($id, '_price', $price);
        update_post_meta($id, '_currency', $currency);
        if ($brand) update_post_meta($id, '_brand', $brand);
        if ($model) update_post_meta($id, '_model', $model);
        if ($year > 0) update_post_meta($id, '_year', $year);
        if ($color) update_post_meta($id, '_color', $color);
        update_post_meta($id, '_condition', $condition);
        if ($location) update_post_meta($id, '_location', $location);
        update_post_meta($id, '_featured', $featured);

        if ($type === 'subasta') {
            if ($start_time !== null) {
                update_post_meta($id, '_start_time', $start_time);
            } else {
                delete_post_meta($id, '_start_time');
            }
            if ($end_time !== null) {
                update_post_meta($id, '_end_time', $end_time);
            } else {
                delete_post_meta($id, '_end_time');
            }

            $current_time_str = current_time('Y-m-d H:i:s');
            $current_time = new DateTime($current_time_str);
            $start_time_obj = $start_time ? new DateTime($start_time) : new DateTime('1970-01-01 00:00:00');
            $end_time_obj = $end_time ? new DateTime($end_time) : new DateTime('2038-01-19 03:14:07');

            if ($current_time < $start_time_obj) {
                $status = 'upcoming';
            } elseif ($current_time > $end_time_obj) {
                $status = 'closed';
            } else {
                $status = 'live';
            }
            update_post_meta($id, '_auction_status', $status);
        } else {
            delete_post_meta($id, '_start_time');
            delete_post_meta($id, '_end_time');
            delete_post_meta($id, '_auction_status');
        }

        // gallery upload for update (same as create)
        if (isset($_FILES['gallery']) && !empty($_FILES['gallery']['name'][0])) {
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            $gallery_ids = [];
            $upload_errors = [];
            $file_count = count($_FILES['gallery']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['gallery']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_to_upload = [
                        'name'     => $_FILES['gallery']['name'][$i],
                        'type'     => $_FILES['gallery']['type'][$i],
                        'tmp_name' => $_FILES['gallery']['tmp_name'][$i],
                        'error'    => $_FILES['gallery']['error'][$i],
                        'size'     => $_FILES['gallery']['size'][$i],
                    ];
                    $upload = wp_handle_upload($file_to_upload, ['test_form' => false]);
                    if (!isset($upload['error']) && isset($upload['file'])) {
                        $filename = $upload['file'];
                        $wp_filetype = wp_check_filetype(basename($filename), null);
                        $attachment = [
                            'post_mime_type' => $wp_filetype['type'],
                            'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
                            'post_content' => '',
                            'post_status' => 'inherit'
                        ];
                        $attach_id = wp_insert_attachment($attachment, $filename, $id);
                        if (!is_wp_error($attach_id)) {
                            if (!function_exists('wp_generate_attachment_metadata')) {
                                require_once(ABSPATH . 'wp-admin/includes/image.php');
                            }
                            $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
                            wp_update_attachment_metadata($attach_id, $attach_data);
                            $gallery_ids[] = $attach_id;
                        } else {
                            $upload_errors[] = "Error al adjuntar imagen " . $_FILES['gallery']['name'][$i] . ": " . $attach_id->get_error_message();
                        }
                    } else {
                        $upload_errors[] = "Error al subir imagen " . $_FILES['gallery']['name'][$i] . ": " . ($upload['error'] ?? 'Error desconocido');
                    }
                } else {
                    $error_code = $_FILES['gallery']['error'][$i];
                    $error_message = "Error desconocido";
                    switch ($error_code) {
                        case UPLOAD_ERR_INI_SIZE: $error_message = "El archivo excede el tamaño máximo permitido por upload_max_filesize en php.ini."; break;
                        case UPLOAD_ERR_FORM_SIZE: $error_message = "El archivo excede el tamaño máximo permitido por MAX_FILE_SIZE en el formulario."; break;
                        case UPLOAD_ERR_PARTIAL: $error_message = "El archivo fue solo parcialmente subido."; break;
                        case UPLOAD_ERR_NO_FILE: $error_message = "No se subió ningún archivo."; break;
                        case UPLOAD_ERR_NO_TMP_DIR: $error_message = "Falta la carpeta temporal."; break;
                        case UPLOAD_ERR_CANT_WRITE: $error_message = "Falló la escritura del archivo en disco."; break;
                        case UPLOAD_ERR_EXTENSION: $error_message = "Una extensión de PHP detuvo la subida del archivo."; break;
                    }
                    $upload_errors[] = "Error de archivo " . $_FILES['gallery']['name'][$i] . " (Código: $error_code): " . $error_message;
                }
            }
            if (!empty($gallery_ids)) {
                $current_gallery = get_post_meta($id, '_vehicle_gallery', true);
                $current_ids = $current_gallery ? explode(',', $current_gallery) : [];
                $all_ids = array_merge($current_ids, $gallery_ids);
                update_post_meta($id, '_vehicle_gallery', implode(',', $all_ids));
            }
            if (!empty($upload_errors)) {
                error_log("AutoBid Pro: Errores de subida de galería para vehículo ID {$id}: " . implode(', ', $upload_errors));
            }
        }

        clean_post_cache($id);
        $freshly_updated_post = get_post($id);
        if (!$freshly_updated_post || $freshly_updated_post->ID !== $id) {
            $freshly_updated_post = $post;
        }

        $updated_vehicle = $this->format_vehicle($freshly_updated_post);
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Vehículo actualizado exitosamente.',
            'vehicle' => $updated_vehicle
        ], 200);
    }

    public function delete_vehicle($request) {
        if (!current_user_can('administrator')) {
            return new WP_Error('forbidden', 'Acceso denegado. Requiere permisos de administrador.', ['status' => 403]);
        }
        $id = (int) $request['id'];
        $post = get_post($id);
        if (!$post || $post->post_type !== 'vehicle') {
            return new WP_Error('not_found', 'Vehículo no encontrado.', ['status' => 404]);
        }
        $result = wp_delete_post($id, true);
        if ($result) {
            return new WP_REST_Response(['success' => true, 'message' => 'Vehículo eliminado exitosamente.'], 200);
        } else {
            return new WP_Error('db_error', 'Error al eliminar el vehículo de la base de datos.', ['status' => 500]);
        }
    }

    public function check_user_logged_in_and_authorized() {
        if (!is_user_logged_in()) return false;
        $user = wp_get_current_user();
        $allowed_roles = ['vehicle_user', 'administrator', 'editor'];
        foreach ($allowed_roles as $role) {
            if (in_array($role, (array) $user->roles)) return true;
        }
        return false;
    }

    /* ---------- get_vehicles / get_vehicle ---------- */

    public function get_vehicles($request = null) {
        error_log("AutoBid Pro API: get_vehicles called.");
        $type = null;
        if ($request) {
            $type = $request->get_param('type');
        }

        if (!$type || $type === 'all') {
            $meta_query = [
                'relation' => 'OR',
                ['key' => '_type', 'value' => 'venta'],
                ['key' => '_type', 'value' => 'subasta']
            ];
        } elseif ($type === 'venta' || $type === 'subasta') {
            $meta_query = [['key' => '_type', 'value' => $type]];
        } else {
            $meta_query = [['key' => '_type', 'value' => 'invalid_placeholder_to_force_empty_result']];
        }

        $posts = get_posts([
            'post_type' => 'vehicle',
            'meta_query' => $meta_query,
            'numberposts' => -1,
            'post_status' => 'publish'
        ]);
        error_log("AutoBid Pro API: Number of vehicles found: " . count($posts));
        return new WP_REST_Response(array_map([$this, 'format_vehicle'], $posts));
    }

    public function get_vehicle($request) {
        $id = (int) $request['id'];
        $post = get_post($id);
        if (!$post || $post->post_type !== 'vehicle') {
            return new WP_Error('not_found', 'Vehículo no encontrado', ['status' => 404]);
        }
        return new WP_REST_Response($this->format_vehicle($post));
    }

    /* ---------- place_bid (mejorado: correo + whatsapp URL) ---------- */

    public function place_bid($request) {
        $vehicle_id = (int) $request['id'];
        // Accept JSON or form param 'bid_amount'
        $params = $request->get_params();
        $bid_amount = isset($params['bid_amount']) ? (float)$params['bid_amount'] : 0;
        $user_id = get_current_user_id();
        $vehicle = get_post($vehicle_id);
        if (!$vehicle || $vehicle->post_type !== 'vehicle') {
            return new WP_Error('invalid_vehicle', 'Vehículo no válido.', ['status' => 400]);
        }

        $auction_status = get_post_meta($vehicle_id, '_auction_status', true);
        if ($auction_status === 'closed') {
            return new WP_Error('auction_closed', 'Esta subasta ya ha finalizado.', ['status' => 400]);
        }

        $end_time = get_post_meta($vehicle_id, '_end_time', true);
        if ($end_time && $end_time !== '0000-00-00 00:00:00') {
            $current_time = current_time('Y-m-d H:i:s');
            if (strtotime($end_time) <= strtotime($current_time)) {
                update_post_meta($vehicle_id, '_auction_status', 'closed');
                return new WP_Error('auction_closed', 'Esta subasta ya ha finalizado.', ['status' => 400]);
            }
        }

        if ($bid_amount <= 0) {
            return new WP_Error('invalid_bid', 'Monto de puja inválido.', ['status' => 400]);
        }

        $current_bid = (float) get_post_meta($vehicle_id, '_current_bid', true);
        if ($bid_amount <= $current_bid) {
            return new WP_Error('low_bid', 'Tu puja debe ser mayor que la actual.', ['status' => 400]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'autobid_bids';
        $inserted = $wpdb->insert($table, [
            'vehicle_id' => $vehicle_id,
            'user_id'    => $user_id,
            'bid_amount' => $bid_amount,
            'created_at' => current_time('mysql')
        ], ['%d','%d','%f','%s']);

        if (!$inserted) {
            return new WP_Error('db_error', 'Error al registrar la puja en la base de datos.', ['status' => 500]);
        }

        // Update current highest
        update_post_meta($vehicle_id, '_current_bid', $bid_amount);
        update_post_meta($vehicle_id, '_highest_bidder', $user_id);

        // Prepare WhatsApp URLs (returned to frontend so it can open them)
        $admin_whatsapp = get_option('autobid_whatsapp_number', '');
        $admin_whatsapp_url = null;
        if (!empty($admin_whatsapp)) {
            $user = get_userdata($user_id);
            $site_name = get_bloginfo('name');
            $user_phone = get_user_meta($user_id, 'phone', true);
            $user_contact = $user_phone ? "Tel: {$user_phone}" : "Email: {$user->user_email}";

            $message = "🔔 Nueva puja en {$site_name}\n\nVehículo: {$vehicle->post_title} (ID: {$vehicle_id})\nUsuario: {$user->display_name} (ID: {$user_id})\n{$user_contact}\nPuja: $" . number_format($bid_amount, 2) . "\nVer: " . get_permalink($vehicle_id);
            $admin_whatsapp_url = "https://wa.me/".rawurlencode($admin_whatsapp)."?text=".rawurlencode($message);
        }

        // WhatsApp URL for user (if user phone exists pointing to admin)
        $user_whatsapp_url = null;
        $user_phone = get_user_meta($user_id, 'phone', true);
        if (!empty($user_phone)) {
            $clean_phone = preg_replace('/[^0-9+]/', '', $user_phone);
            if (!empty($clean_phone)) {
                $user_message = "✅ Tu puja fue registrada en ".get_bloginfo('name')." para el vehículo {$vehicle->post_title}. Monto: $" . number_format($bid_amount, 2);
                $user_whatsapp_url = "https://wa.me/".rawurlencode($clean_phone)."?text=".rawurlencode($user_message);
            }
        }

        // Send email notifications: admin and bidder
        try {
            $user = get_userdata($user_id);
            $vehicle_url = get_permalink($vehicle_id);
            $site_name = get_bloginfo('name');

            // Email admin
            $admin_email = $this->get_admin_email();
            $subject_admin = "🔔 Nueva puja: {$vehicle->post_title} (ID {$vehicle_id})";
            $intro_admin = "Se ha registrado una nueva puja en el sitio.";
            $body_admin = "<p><strong>Vehículo:</strong> {$vehicle->post_title} (ID: {$vehicle_id})</p>";
            $body_admin .= "<p><strong>Usuario:</strong> {$user->display_name} (ID: {$user_id})</p>";
            $body_admin .= "<p><strong>Email:</strong> {$user->user_email}</p>";
            $body_admin .= "<p><strong>Teléfono:</strong> " . esc_html(get_user_meta($user_id, 'phone', true)) . "</p>";
            $body_admin .= "<p><strong>Puja:</strong> $" . number_format($bid_amount, 2) . "</p>";
            $body_admin .= "<p><a href=\"{$vehicle_url}\">Ver vehículo</a></p>";
            if ($admin_whatsapp_url) {
                $body_admin .= "<p>WhatsApp: <a href=\"" . esc_url($admin_whatsapp_url) . "\">Abrir chat</a></p>";
            }
            $html_admin = $this->build_email_html_template($subject_admin, $intro_admin, $body_admin, $vehicle_url, 'Ver vehículo');
            $this->send_email_html($admin_email, $subject_admin, $html_admin);

            // Email bidder
            $subject_user = "✅ Tu puja para {$vehicle->post_title} ha sido registrada";
            $intro_user = "Gracias por participar en la subasta. Tu puja ha sido registrada correctamente.";
            $body_user = "<p><strong>Vehículo:</strong> {$vehicle->post_title}</p>";
            $body_user .= "<p><strong>Puja registrada:</strong> $" . number_format($bid_amount, 2) . "</p>";
            $body_user .= "<p><a href=\"{$vehicle_url}\">Ver vehículo y estado</a></p>";
            if ($user_whatsapp_url) {
                $body_user .= "<p>WhatsApp: <a href=\"" . esc_url($user_whatsapp_url) . "\">Abrir chat</a></p>";
            }
            $html_user = $this->build_email_html_template($subject_user, $intro_user, $body_user, $vehicle_url, 'Ver vehículo');
            $this->send_email_html($user->user_email, $subject_user, $html_user);
        } catch (Exception $e) {
            error_log("AutoBid Pro: Error sending bid notification emails: " . $e->getMessage());
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Puja registrada exitosamente.',
            'current_bid' => $bid_amount,
            'admin_whatsapp_url' => $admin_whatsapp_url,
            'user_whatsapp_url' => $user_whatsapp_url
        ], 200);
    }

    /* ---------- formatting + other endpoints ---------- */

    private function send_bid_notification($vehicle_id, $user_id, $bid_amount) {
        // Left for backward compatibility if you want to call it from elsewhere.
        // Now place_bid handles notifications itself.
    }

    private function format_vehicle($post) {
        $gallery_ids_raw = get_post_meta($post->ID, '_vehicle_gallery', true);
        $gallery = [];
        if ($gallery_ids_raw) {
            $ids = is_array($gallery_ids_raw) ? $gallery_ids_raw : explode(',', $gallery_ids_raw);
            foreach ($ids as $id) {
                $url = wp_get_attachment_image_url($id, 'large');
                if ($url) $gallery[] = $url;
            }
        }
        $type = get_post_meta($post->ID, '_type', true) ?: 'venta';
        $price = (float) get_post_meta($post->ID, '_price', true);
        $current_bid = (float) get_post_meta($post->ID, '_current_bid', true);
        if ($current_bid <= 0 && $price > 0) $current_bid = $price;
        $currency = get_post_meta($post->ID, '_currency', true) ?: 'USD';
        $raw_start_time = get_post_meta($post->ID, '_start_time', true);
        $raw_end_time = get_post_meta($post->ID, '_end_time', true);
        $start_time_str = $raw_start_time ?: '0000-00-00 00:00:00';
        $end_time_str = $raw_end_time ?: '0000-00-00 00:00:00';
        $status = 'active';
        if ($type === 'subasta') {
            $current_time_str = current_time('Y-m-d H:i:s');
            $current_time = new DateTime($current_time_str);
            $start_time_obj = $start_time_str && $start_time_str !== '0000-00-00 00:00:00' ? new DateTime($start_time_str) : new DateTime('1970-01-01 00:00:00');
            $end_time_obj = $end_time_str && $end_time_str !== '0000-00-00 00:00:00' ? new DateTime($end_time_str) : new DateTime('2038-01-19 03:14:07');
            if ($current_time < $start_time_obj) $status = 'upcoming';
            elseif ($current_time > $end_time_obj) $status = 'closed';
            else $status = 'live';
        }
        return [
            'id' => $post->ID,
            'name' => $post->post_title,
            'description' => $post->post_content,
            'type' => $type,
            'price' => $price,
            'current_bid' => $current_bid,
            'currency' => $currency,
            'brand' => get_post_meta($post->ID, '_brand', true) ?: '',
            'model' => get_post_meta($post->ID, '_model', true) ?: '',
            'year' => get_post_meta($post->ID, '_year', true) ?: '',
            'color' => get_post_meta($post->ID, '_color', true) ?: '',
            'condition' => get_post_meta($post->ID, '_condition', true) ?: 'usado',
            'location' => get_post_meta($post->ID, '_location', true) ?: '',
            'start_time' => $start_time_str !== '0000-00-00 00:00:00' ? $start_time_str : null,
            'end_time' => $end_time_str !== '0000-00-00 00:00:00' ? $end_time_str : null,
            'auction_status' => $status,
            'featured' => get_post_meta($post->ID, '_featured', true) ?: '0',
            'image' => get_the_post_thumbnail_url($post->ID, 'large') ?: ($gallery[0] ?? 'https://placehold.co/600x400'),
            'gallery' => $gallery ?: [get_the_post_thumbnail_url($post->ID, 'large') ?: 'https://placehold.co/600x400']
        ];
    }

    public function get_dashboard_stats($request) {
        global $wpdb;
        $total_vehicles = wp_count_posts('vehicle');
        $total_vehicles_count = $total_vehicles->publish;
        $today_start = current_time('Y-m-d 00:00:00');
        $today_end = current_time('Y-m-d 23:59:59');
        $bids_today_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}autobid_bids
            WHERE created_at >= %s AND created_at <= %s
        ", $today_start, $today_end));
        $sales_today_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts}
            WHERE post_type = 'vehicle' AND post_status = 'publish'
              AND post_date >= %s AND post_date <= %s
              AND ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_type' AND meta_value = 'venta')
        ", $today_start, $today_end));
        $user_count = count_users();
        $total_users_count = $user_count['total_users'];

        return new WP_REST_Response([
            'total_vehicles' => $total_vehicles_count,
            'bids_today' => $bids_today_count,
            'sales_today' => $sales_today_count,
            'total_users' => $total_users_count
        ], 200);
    }

    public function get_all_bids($request) {
        if (!current_user_can('administrator')) {
            return new WP_Error('forbidden', 'Acceso denegado. Requiere permisos de administrador.', ['status' => 403]);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'autobid_bids';
        $bids = $wpdb->get_results("
            SELECT b.id, b.vehicle_id, b.user_id, b.bid_amount, b.created_at, v.post_title as vehicle_name, u.display_name as user_name, u.user_email as user_email
            FROM $table b
            LEFT JOIN {$wpdb->posts} v ON b.vehicle_id = v.ID
            LEFT JOIN {$wpdb->users} u ON b.user_id = u.ID
            ORDER BY b.created_at DESC
        ");
        if (is_wp_error($bids)) {
            return new WP_Error('db_error', 'Error al obtener pujas de la base de datos: ' . $bids->get_error_message(), ['status' => 500]);
        }
        return new WP_REST_Response($bids, 200);
    }

    public function get_all_sales($request) {
        if (!current_user_can('administrator')) {
            return new WP_Error('forbidden', 'Acceso denegado. Requiere permisos de administrador.', ['status' => 403]);
        }
        $sold_auctions = get_posts([
            'post_type' => 'vehicle',
            'meta_query' => [
                ['key' => '_type', 'value' => 'subasta'],
                ['key' => '_auction_status', 'value' => 'closed'],
                ['key' => '_highest_bidder', 'compare' => 'EXISTS']
            ],
            'numberposts' => -1,
            'post_status' => 'publish',
        ]);
        $direct_sales = get_posts([
            'post_type' => 'vehicle',
            'meta_query' => [['key' => '_type', 'value' => 'venta']],
            'numberposts' => -1,
            'post_status' => 'publish',
        ]);
        $sales_data = [
            'sold_auctions' => array_map([$this, 'format_vehicle_for_sales'], $sold_auctions),
            'direct_sales' => array_map([$this, 'format_vehicle_for_sales'], $direct_sales)
        ];
        return new WP_REST_Response($sales_data, 200);
    }

    private function format_vehicle_for_sales($post) {
        $vehicle_data = $this->format_vehicle($post);
        if ($vehicle_data['type'] === 'subasta') {
            $winner_id = get_post_meta($post->ID, '_highest_bidder', true);
            $winner_user = $winner_id ? get_userdata($winner_id) : null;
            $vehicle_data['winner'] = $winner_user ? [
                'id' => $winner_user->ID,
                'name' => $winner_user->display_name,
                'email' => $winner_user->user_email
            ] : null;
            $vehicle_data['final_bid'] = (float) get_post_meta($post->ID, '_current_bid', true);
        } else {
            $vehicle_data['winner'] = null;
            $vehicle_data['final_bid'] = null;
        }
        return $vehicle_data;
    }
}
