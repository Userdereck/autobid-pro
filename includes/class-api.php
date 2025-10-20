<?php
class AutoBid_API {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    public function register_routes() {
        register_rest_route('autobid/v1', '/vehicles', [
            'methods' => 'GET',
            'callback' => [$this, 'get_vehicles'],
            'permission_callback' => '__return_true' // Público para lectura
        ]);

        // --- NUEVO ENDPOINT: Obtener estadísticas del dashboard ---
        register_rest_route('autobid/v1', '/vehicles/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_dashboard_stats'],
            'permission_callback' => [$this, 'check_admin_access'] // Solo admin
        ]);
        // --- FIN NUEVO ENDPOINT ---

        // --- Nuevo endpoint: Crear vehículo ---
        register_rest_route('autobid/v1', '/vehicles', [
            'methods' => 'POST',
            'callback' => [$this, 'create_vehicle'],
            'permission_callback' => [$this, 'check_admin_access'] // Solo admin
        ]);
        // --- Fin Nuevo endpoint ---

        register_rest_route('autobid/v1', '/vehicles/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_vehicle'],
            'permission_callback' => '__return_true' // Público para lectura
        ]);

        // --- Nuevo endpoint: Actualizar vehículo ---
        register_rest_route('autobid/v1', '/vehicles/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_vehicle'],
            'permission_callback' => [$this, 'check_admin_access'] // Solo admin
        ]);
        // --- Fin Nuevo endpoint ---

        // --- Nuevo endpoint: Eliminar vehículo ---
        register_rest_route('autobid/v1', '/vehicles/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_vehicle'],
            'permission_callback' => [$this, 'check_admin_access'] // Solo admin
        ]);
        // --- Fin Nuevo endpoint ---

        register_rest_route('autobid/v1', '/vehicles/(?P<id>\d+)/bid', [
            'methods' => 'POST',
            'callback' => [$this, 'place_bid'],
            'permission_callback' => [$this, 'check_user_logged_in_and_authorized'] // Verificado previamente
        ]);

        // --- NUEVO ENDPOINT: Obtener todas las pujas ---
        register_rest_route('autobid/v1', '/bids', [
            'methods' => 'GET',
            'callback' => [$this, 'get_all_bids'],
            'permission_callback' => [$this, 'check_admin_access'] // Solo admin
        ]);
        // --- FIN NUEVO ENDPOINT ---

        // --- NUEVO ENDPOINT: Obtener todas las ventas ---
        register_rest_route('autobid/v1', '/sales', [
            'methods' => 'GET',
            'callback' => [$this, 'get_all_sales'],
            'permission_callback' => [$this, 'check_admin_access'] // Solo admin
        ]);
        
        // --- NUEVA RUTA: Comprar ahora (para ventas directas) ---
        register_rest_route('autobid/v1', '/vehicles/(?P<id>\d+)/purchase', [
            'methods' => 'POST',
            'callback' => [$this, 'purchase_vehicle'],
            'permission_callback' => [$this, 'check_user_logged_in_and_authorized'] // Verificar que esté logueado
        ]);
        // --- FIN NUEVA RUTA ---
    }

    // --- Nueva función de verificación de permisos de admin ---
    public function check_admin_access() {
        return current_user_can('administrator');
    }
    // --- Fin Nueva función ---

     // --- Nueva función: Comprar vehículo (venta directa) ---
    public function purchase_vehicle($request) {
        // La verificación de rol ya se hizo en 'permission_callback'
        $vehicle_id = (int) $request['id'];
        $user_id = get_current_user_id(); // El usuario ya está logueado y autorizado

        $vehicle = get_post($vehicle_id);
        if (!$vehicle || $vehicle->post_type !== 'vehicle') {
            return new WP_Error('invalid_vehicle', 'Vehículo no válido.', ['status' => 400]);
        }

        $type = get_post_meta($vehicle_id, '_type', true);
        if ($type !== 'venta') {
            return new WP_Error('invalid_type', 'La acción "Comprar ahora" solo está disponible para ventas directas.', ['status' => 400]);
        }

        // --- NUEVA LÓGICA: Enviar mensaje por WhatsApp ---
        $whatsapp_number = get_option('autobid_whatsapp_number', ''); // Obtener número de WhatsApp de los ajustes
        if (empty($whatsapp_number)) {
             return new WP_Error('config_error', 'Número de WhatsApp no configurado. Contacte al administrador.', ['status' => 500]);
        }

        $user = get_userdata($user_id);
        $vehicle_title = $vehicle->post_title;
        $vehicle_url = get_permalink($vehicle_id); // URL del vehículo en el frontend
        $site_name = get_bloginfo('name');

        // Crear el mensaje de WhatsApp
        $whatsapp_message = "Hola, soy {$user->display_name} (ID: {$user_id}). Estoy interesado en comprar el vehículo \"{$vehicle_title}\" (ID: {$vehicle_id}). Puedes verlo aquí: {$vehicle_url}. Gracias por tu atención en {$site_name}.";

        // Codificar el mensaje para la URL
        $encoded_message = urlencode($whatsapp_message);

        // Construir la URL de WhatsApp
        $whatsapp_url = "https://wa.me/{$whatsapp_number}?text={$encoded_message}";

        // Registrar intento de envío (opcional, para depuración)
        error_log("AutoBid Pro API (purchase_vehicle): Intentando enviar mensaje de compra por WhatsApp. Vehículo ID: {$vehicle_id}, Usuario ID: {$user_id}, Número: {$whatsapp_number}, Mensaje: {$whatsapp_message}, URL: {$whatsapp_url}");

        // --- FIN NUEVA LÓGICA ---

        // --- NUEVA LÓGICA: Marcar vehículo como vendido (opcional) ---
        // Puedes añadir lógica aquí para marcar el vehículo como vendido, por ejemplo:
        // update_post_meta($vehicle_id, '_sold', '1');
        // update_post_meta($vehicle_id, '_sold_to', $user_id);
        // update_post_meta($vehicle_id, '_sold_date', current_time('Y-m-d H:i:s'));
        // --- FIN NUEVA LÓGICA ---

        // Devolver la URL de WhatsApp para que el frontend pueda redirigir
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Solicitud de compra enviada. Serás redirigido a WhatsApp.',
            'whatsapp_url' => $whatsapp_url // Pasar la URL de WhatsApp al frontend
        ], 200);
    }
    // --- Fin Nueva función ---
    

    // --- Nueva función: Crear vehículo ---
        // --- Nueva función: Crear vehículo ---
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

        // Validación específica para subastas
        if ($type === 'subasta') {
            if (empty($end_time)) {
                return new WP_Error('invalid_data', 'La fecha de fin es obligatoria para subastas.', ['status' => 400]);
            }
            // Validar fechas si se envían
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

        // Crear el post
        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_type' => 'vehicle',
            'post_status' => 'publish'
        ]);

        if (is_wp_error($post_id)) {
            return new WP_Error('db_error', 'Error al crear el vehículo en la base de datos: ' . $post_id->get_error_message(), ['status' => 500]);
        }

        // Guardar campos meta
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
            // Inicializar estado de subasta
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

        // --- CORREGIDO: Manejar la galería de imágenes ---
        if (isset($_FILES['gallery']) && !empty($_FILES['gallery']['name'][0])) {
            // Asegurar que se incluye el archivo necesario
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }

            $gallery_ids = [];
            $upload_errors = []; // Para registrar errores de subida

            // Iterar sobre los archivos correctamente
            $file_count = count($_FILES['gallery']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['gallery']['error'][$i] === UPLOAD_ERR_OK) {
                    // Crear un array simulando $_FILES para un solo archivo
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
                            // Asegurar que se incluye la librería de imágenes
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
                    // Registrar errores específicos del archivo individual
                    $error_code = $_FILES['gallery']['error'][$i];
                    $error_message = "Error desconocido";
                    switch ($error_code) {
                        case UPLOAD_ERR_INI_SIZE:
                            $error_message = "El archivo excede el tamaño máximo permitido por upload_max_filesize en php.ini.";
                            break;
                        case UPLOAD_ERR_FORM_SIZE:
                            $error_message = "El archivo excede el tamaño máximo permitido por MAX_FILE_SIZE en el formulario.";
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error_message = "El archivo fue solo parcialmente subido.";
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            $error_message = "No se subió ningún archivo.";
                            break;
                        case UPLOAD_ERR_NO_TMP_DIR:
                            $error_message = "Falta la carpeta temporal.";
                            break;
                        case UPLOAD_ERR_CANT_WRITE:
                            $error_message = "Falló la escritura del archivo en disco.";
                            break;
                        case UPLOAD_ERR_EXTENSION:
                            $error_message = "Una extensión de PHP detuvo la subida del archivo.";
                            break;
                    }
                    $upload_errors[] = "Error de archivo " . $_FILES['gallery']['name'][$i] . " (Código: $error_code): " . $error_message;
                }
            }
            if (!empty($gallery_ids)) {
                update_post_meta($post_id, '_vehicle_gallery', implode(',', $gallery_ids));
            }
            // Opcional: Devolver errores de subida
            if (!empty($upload_errors)) {
                error_log("AutoBid Pro: Errores de subida de galería para vehículo ID {$post_id}: " . implode(', ', $upload_errors));
                // Podríamos incluir esto en la respuesta, pero para no romper la lógica principal, lo registramos.
                // return new WP_Error('upload_error', 'Errores durante la subida: ' . implode(', ', $upload_errors), ['status' => 400]);
            }
        }
        // --- FIN CORREGIDO ---

        $new_vehicle = $this->format_vehicle(get_post($post_id));
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Vehículo creado exitosamente.',
            'vehicle' => $new_vehicle
        ], 201);
    }
    // --- Fin Nueva función ---
   


    // --- Nueva función: Actualizar vehículo ---

  // --- NUEVA VERSIÓN COMPLETA CON FIX DE META DUPLICADOS Y CACHE ---
       // --- Nueva función: Actualizar vehículo ---
    // Reemplaza tu función update_vehicle existente con esta
    public function update_vehicle($request) {
        if (!current_user_can('administrator')) {
            return new WP_Error('forbidden', 'Acceso denegado. Requiere permisos de administrador.', ['status' => 403]);
        }

        $id = (int) $request['id'];
        $post = get_post($id);
        if (!$post || $post->post_type !== 'vehicle') {
            return new WP_Error('not_found', 'Vehículo no encontrado.', ['status' => 404]);
        }

        // --- CORREGIDO Y REFORZADO: Obtener y sanitizar parámetros de forma explícita ---
        $params = $request->get_params();
        error_log("AutoBid Pro API (update_vehicle): Parámetros RAW recibidos para ID {$id}: " . print_r($params, true)); // Log para depuración

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
        $featured = isset($params['featured']) ? '1' : '0'; // Checkbox: si existe, es '1', si no, '0'

        // --- CORREGIDO Y REFORZADO: Obtener fechas crudas de forma explícita ---
        // Registrar valores crudos para depuración
        $raw_start_time = $params['start_time'] ?? get_post_meta($id, '_start_time', true);
        $raw_end_time = $params['end_time'] ?? get_post_meta($id, '_end_time', true);
        error_log("AutoBid Pro API (update_vehicle): Valores crudos de fechas para ID {$id}: start_time='{$raw_start_time}', end_time='{$raw_end_time}'"); // Log

        // Inicializar variables de fecha como null
        $start_time = null;
        $end_time = null;

        // --- CORREGIDO Y REFORZADO: Validación y formateo de fechas SOLO para subastas ---
        if ($type === 'subasta') {
            if (empty($raw_end_time)) {
                return new WP_Error('invalid_data', 'La fecha de fin es obligatoria para subastas.', ['status' => 400]);
            }

            // --- REFORZADO: Función auxiliar para validar y formatear fechas de manera segura ---
            $validate_and_format_datetime = function($datetime_str, $field_key, $expected_label) {
                // $field_key: 'start_time' o 'end_time'
                // $expected_label: 'fecha de inicio' o 'fecha de fin'
                if (empty($datetime_str)) {
                    return ['valid' => true, 'value' => null, 'field_key' => $field_key]; // Permitir vacío
                }
                // Limpiar la cadena de entrada
                $clean_datetime_str = trim(strval($datetime_str));
                if (empty($clean_datetime_str)) {
                    return ['valid' => true, 'value' => null, 'field_key' => $field_key];
                }

                // Registrar el valor limpio para depuración
                error_log("AutoBid Pro API (update_vehicle): Intentando parsear {$expected_label} (clave: {$field_key}, valor limpio): '{$clean_datetime_str}'"); // Log

                // Intentar parsear con el formato esperado (ISO 8601 sin segundos)
                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $clean_datetime_str);

                // Si falla, intentar parsear como string común con espacio y segundos (formato que PHP podría estar generando)
                if (!$dt) {
                     $dt = DateTime::createFromFormat('Y-m-d H:i:s', $clean_datetime_str);
                     if ($dt) {
                          error_log("AutoBid Pro API (update_vehicle): Advertencia: El campo '{$field_key}' fue parseado con formato secundario 'Y-m-d H:i:s'. Valor recibido: '{$clean_datetime_str}'.");
                     }
                }

                // Si aún falla, intentar parsear con espacio pero sin segundos (otra posibilidad)
                if (!$dt) {
                     $dt = DateTime::createFromFormat('Y-m-d H:i', $clean_datetime_str);
                     if ($dt) {
                          error_log("AutoBid Pro API (update_vehicle): Advertencia: El campo '{$field_key}' fue parseado con formato terciario 'Y-m-d H:i'. Valor recibido: '{$clean_datetime_str}'.");
                     }
                }

                if (!$dt) {
                    // Si aún falla, registrar para depuración y devolver error
                    error_log("AutoBid Pro API (update_vehicle): Error al validar {$expected_label} (clave: {$field_key}). Valor recibido: '{$clean_datetime_str}' (longitud: " . strlen($clean_datetime_str) . "). Caracteres hex: " . bin2hex($clean_datetime_str));
                    // --- MENSAJE DE ERROR ESPECÍFICO ---
                    return [
                        'valid' => false,
                        'value' => null,
                        'field_key' => $field_key, // Pasar la clave del campo
                        'error' => "Formato de {$expected_label} inválido. Se recibió '{$clean_datetime_str}'. Use YYYY-MM-DDTHH:MM o YYYY-MM-DD HH:MM." // Mensaje más claro
                    ];
                    // --- FIN MENSAJE DE ERROR ESPECÍFICO ---
                }

                // Si se parseó correctamente, formatear para guardar en la BD
                return ['valid' => true, 'value' => $dt->format('Y-m-d H:i:s'), 'field_key' => $field_key];
            };
            // --- FIN REFORZADO ---

            // Validar y formatear end_time primero
            $end_validation = $validate_and_format_datetime($raw_end_time, 'end_time', 'fecha de fin');
            if (!$end_validation['valid']) {
                return new WP_Error('invalid_data', $end_validation['error'], ['status' => 400]);
            }
            $end_time = $end_validation['value'];

            // Validar y formatear start_time si se proporciona
            $start_validation = $validate_and_format_datetime($raw_start_time, 'start_time', 'fecha de inicio');
            if (!$start_validation['valid']) {
                 return new WP_Error('invalid_data', $start_validation['error'], ['status' => 400]);
            }
            $start_time = $start_validation['value']; // Puede ser null si estaba vacío

        } else {
            // Si el tipo NO es subasta, asegurar que las fechas de subasta se eliminen
            $raw_start_time = null;
            $raw_end_time = null;
            $start_time = null;
            $end_time = null;
        }
        // --- FIN CORREGIDO Y REFORZADO ---

        // Registrar parámetros sanitizados para depuración
        error_log("AutoBid Pro API (update_vehicle): Parámetros sanitizados para ID {$id}: " .
            "title='{$title}', content_length=" . strlen($content) . ", type='{$type}', price={$price}, " .
            "currency='{$currency}', brand='{$brand}', model='{$model}', year={$year}, " .
            "color='{$color}', condition='{$condition}', location='{$location}', featured='{$featured}', " .
            "start_time=" . ($start_time ?? 'null') . ", end_time=" . ($end_time ?? 'null')
        ); // Log

        // --- CORREGIDO Y REFORZADO: Actualizar el post principal (título, contenido) ---
        $updated_post_id = wp_update_post([
            'ID' => $id,
            'post_title' => $title,
            'post_content' => $content,
        ], true); // true = devolver WP_Error en caso de fallo

        if (is_wp_error($updated_post_id)) {
            error_log("AutoBid Pro API (update_vehicle): Error de WordPress al actualizar el post ID {$id}: " . $updated_post_id->get_error_message()); // Log
            return new WP_Error('db_error', 'Error al actualizar el vehículo (título/contenido) en la base de datos: ' . $updated_post_id->get_error_message(), ['status' => 500]);
        }
        if ($updated_post_id !== $id) {
             error_log("AutoBid Pro API (update_vehicle): wp_update_post devolvió un ID inesperado: {$updated_post_id} en lugar de {$id}"); // Log
             // Esto no debería pasar normalmente, pero por si acaso.
        }
        // --- FIN CORREGIDO Y REFORZADO ---

        // --- CORREGIDO Y REFORZADO: Actualizar campos meta de forma más robusta ---
        // Eliminar todos los metadatos relevantes primero para evitar conflictos
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

        // Volver a crear los metadatos con los nuevos valores
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
            // --- CORREGIDO Y REFORZADO: Actualizar/eliminar fechas de subasta ---
            if ($start_time !== null) {
                update_post_meta($id, '_start_time', $start_time);
                error_log("AutoBid Pro API (update_vehicle): Meta _start_time actualizado para ID {$id} a: {$start_time}"); // Log
            } else {
                delete_post_meta($id, '_start_time'); // Eliminar si se borró o estaba vacío
                error_log("AutoBid Pro API (update_vehicle): Meta _start_time eliminado para ID {$id}"); // Log
            }
            if ($end_time !== null) {
                update_post_meta($id, '_end_time', $end_time);
                error_log("AutoBid Pro API (update_vehicle): Meta _end_time actualizado para ID {$id} a: {$end_time}"); // Log
            } else {
                // Esto no debería pasar si type es subasta y validamos arriba, pero por seguridad
                delete_post_meta($id, '_end_time');
                error_log("AutoBid Pro API (update_vehicle): Meta _end_time eliminado para ID {$id}"); // Log
            }

            // Actualizar estado de subasta
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
            error_log("AutoBid Pro API (update_vehicle): Estado de subasta actualizado para ID {$id} a: {$status}"); // Log
            // --- FIN CORREGIDO Y REFORZADO ---
        } else {
            // Si cambia a venta, eliminar fechas de subasta y estado
            delete_post_meta($id, '_start_time');
            delete_post_meta($id, '_end_time');
            delete_post_meta($id, '_auction_status');
            error_log("AutoBid Pro API (update_vehicle): Metadatos de subasta eliminados para ID {$id} (cambio a venta)"); // Log
        }
        // --- FIN CORREGIDO Y REFORZADO ---

        // --- CORREGIDO Y REFORZADO: Manejar la galería de imágenes (añadir nuevas) ---
        // (Este bloque permanece igual al proporcionado anteriormente, solo se incluye para completitud)
        if (isset($_FILES['gallery']) && !empty($_FILES['gallery']['name'][0])) {
            // Asegurar que se incluye el archivo necesario
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }

            $gallery_ids = [];
            $upload_errors = []; // Para registrar errores de subida

            // Iterar sobre los archivos correctamente
            $file_count = count($_FILES['gallery']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['gallery']['error'][$i] === UPLOAD_ERR_OK) {
                    // Crear un array simulando $_FILES para un solo archivo
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
                            // Asegurar que se incluye la librería de imágenes
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
                    // Registrar errores específicos del archivo individual
                    $error_code = $_FILES['gallery']['error'][$i];
                    $error_message = "Error desconocido";
                    switch ($error_code) {
                        case UPLOAD_ERR_INI_SIZE:
                            $error_message = "El archivo excede el tamaño máximo permitido por upload_max_filesize en php.ini.";
                            break;
                        case UPLOAD_ERR_FORM_SIZE:
                            $error_message = "El archivo excede el tamaño máximo permitido por MAX_FILE_SIZE en el formulario.";
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error_message = "El archivo fue solo parcialmente subido.";
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            $error_message = "No se subió ningún archivo.";
                            break;
                        case UPLOAD_ERR_NO_TMP_DIR:
                            $error_message = "Falta la carpeta temporal.";
                            break;
                        case UPLOAD_ERR_CANT_WRITE:
                            $error_message = "Falló la escritura del archivo en disco.";
                            break;
                        case UPLOAD_ERR_EXTENSION:
                            $error_message = "Una extensión de PHP detuvo la subida del archivo.";
                            break;
                    }
                    $upload_errors[] = "Error de archivo " . $_FILES['gallery']['name'][$i] . " (Código: $error_code): " . $error_message;
                }
            }
            if (!empty($gallery_ids)) {
                // Obtener galería actual y añadir las nuevas
                $current_gallery = get_post_meta($id, '_vehicle_gallery', true);
                $current_ids = $current_gallery ? explode(',', $current_gallery) : [];
                $all_ids = array_merge($current_ids, $gallery_ids);
                update_post_meta($id, '_vehicle_gallery', implode(',', $all_ids));
            }
            // Opcional: Devolver errores de subida
            if (!empty($upload_errors)) {
                error_log("AutoBid Pro: Errores de subida de galería para vehículo ID {$id}: " . implode(', ', $upload_errors));
                // Podríamos incluir esto en la respuesta, pero para no romper la lógica principal, lo registramos.
                // return new WP_Error('upload_error', 'Errores durante la subida: ' . implode(', ', $upload_errors), ['status' => 400]);
            }
        }
        // --- FIN CORREGIDO Y REFORZADO ---


        // --- CORREGIDO Y REFORZADO: Forzar recarga del post desde BD antes de formatear ---
        // Limpiar el caché de WordPress para este post específico para asegurar datos frescos
        clean_post_cache($id);
        // Obtener el post actualizado desde la base de datos
        $freshly_updated_post = get_post($id);
        if (!$freshly_updated_post || $freshly_updated_post->ID !== $id) {
             error_log("AutoBid Pro API (update_vehicle): Error al obtener el post actualizado desde BD después de wp_update_post. ID: {$id}");
             // Si no se puede obtener, usar el original (aunque sea menos fresco)
             $freshly_updated_post = $post; // $post es el original obtenido al inicio
        }
        // --- FIN CORREGIDO Y REFORZADO ---

        $updated_vehicle = $this->format_vehicle($freshly_updated_post);
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Vehículo actualizado exitosamente.',
            'vehicle' => $updated_vehicle
        ], 200);
    }
    // --- Fin Nueva función ---

    // --- Fin Nueva función ---
    
  

    // --- Nueva función: Eliminar vehículo ---
    public function delete_vehicle($request) {
        if (!current_user_can('administrator')) {
            return new WP_Error('forbidden', 'Acceso denegado. Requiere permisos de administrador.', ['status' => 403]);
        }

        $id = (int) $request['id'];
        $post = get_post($id);
        if (!$post || $post->post_type !== 'vehicle') {
            return new WP_Error('not_found', 'Vehículo no encontrado.', ['status' => 404]);
        }

        $result = wp_delete_post($id, true); // true = borrado permanente

        if ($result) {
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Vehículo eliminado exitosamente.'
            ], 200);
        } else {
            return new WP_Error('db_error', 'Error al eliminar el vehículo de la base de datos.', ['status' => 500]);
        }
    }
    // --- Fin Nueva función ---

    public function check_user_logged_in_and_authorized() {
        if (!is_user_logged_in()) {
            return false; // No logueado
        }

        $user = wp_get_current_user();
        // Verificar si tiene el rol específico o cualquier rol que permitas pujar
        $allowed_roles = ['vehicle_user', 'administrator', 'editor']; // Ajusta según tus roles
        $has_role = false;
        foreach ($allowed_roles as $role) {
            if (in_array($role, (array) $user->roles)) {
                $has_role = true;
                break;
            }
        }

        return $has_role;
    }

   // En includes/class-api.php
    public function get_vehicles($request = null) {
        error_log("AutoBid Pro API: get_vehicles called."); // <-- Log
        $type = null;
        if ($request) {
            $type = $request->get_param('type');
            error_log("AutoBid Pro API: Request type parameter: " . ($type ?: 'null')); // <-- Log
        }

        // --- CORREGIDO ---
        // Si no se especifica 'type' o se pide 'all', buscar todos los vehículos válidos (venta o subasta)
        if (!$type || $type === 'all') {
            $meta_query = [
                'relation' => 'OR',
                ['key' => '_type', 'value' => 'venta'],
                ['key' => '_type', 'value' => 'subasta']
            ];
        } elseif ($type === 'venta' || $type === 'subasta') {
            // Si se especifica 'venta' o 'subasta', filtrar por ese tipo
            $meta_query = [['key' => '_type', 'value' => $type]];
        } else {
            // Tipo desconocido, devolver vacío o manejar error
            error_log("AutoBid Pro API: Invalid type parameter provided: " . $type); // <-- Log
            $meta_query = [['key' => '_type', 'value' => 'invalid_placeholder_to_force_empty_result']]; // Forzar resultado vacío
        }
        // --- FIN CORREGIDO ---

        $posts = get_posts([
            'post_type' => 'vehicle',
            'meta_query' => $meta_query,
            'numberposts' => -1,
            'post_status' => 'publish'
        ]);
        error_log("AutoBid Pro API: Number of vehicles found: " . count($posts)); // <-- Log
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
    public function place_bid($request) {
        // La verificación de rol ya se hizo en 'permission_callback'
        $vehicle_id = (int) $request['id'];
        $bid_amount = (float) $request['bid_amount'];
        $user_id = get_current_user_id(); // El usuario ya está logueado y autorizado

        $vehicle = get_post($vehicle_id);
        if (!$vehicle || $vehicle->post_type !== 'vehicle') {
            return new WP_Error('invalid_vehicle', 'Vehículo no válido.', ['status' => 400]);
        }

        $auction_status = get_post_meta($vehicle_id, '_auction_status', true);
        if ($auction_status === 'closed') {
            return new WP_Error('auction_closed', 'Esta subasta ya ha finalizado.', ['status' => 400]);
        }

        $end_time = get_post_meta($vehicle_id, '_end_time', true);
        if ($end_time && !empty($end_time) && $end_time !== '0000-00-00 00:00:00') {
            $current_time = current_time('Y-m-d H:i:s');
            if (strcmp($end_time, $current_time) <= 0) {
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
        $result = $wpdb->insert($table, [
            'vehicle_id' => $vehicle_id,
            'user_id'    => $user_id,
            'bid_amount' => $bid_amount
        ]);

        if (!$result) {
            return new WP_Error('db_error', 'Error al registrar la puja en la base de datos.', ['status' => 500]);
        }

        update_post_meta($vehicle_id, '_current_bid', $bid_amount);
        update_post_meta($vehicle_id, '_highest_bidder', $user_id);

        $this->send_bid_notification($vehicle_id, $user_id, $bid_amount);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Puja registrada exitosamente.',
            'current_bid' => $bid_amount
        ], 200);
    }
    private function send_bid_notification($vehicle_id, $user_id, $bid_amount) {
        $user = get_userdata($user_id);
        $vehicle = get_post($vehicle_id);
        $site_name = get_bloginfo('name');
        $subject = '✅ Tu puja ha sido registrada - ' . $vehicle->post_title;
        $message = "Hola {$user->display_name},
    Tu puja de $" . number_format($bid_amount, 2) . " para el vehículo \"{$vehicle->post_title}\" ha sido registrada exitosamente.
    Gracias por participar en {$site_name}.";
        wp_mail($user->user_email, $subject, $message);
    }
   
     // --- Nueva función: Formatear vehículo UNIFICADA ---
    // Reemplaza tu función format_vehicle existente con esta versión corregida
    
    private function format_vehicle($post) {
        // Obtener IDs de la galería desde el meta
        $gallery_ids_raw = get_post_meta($post->ID, '_vehicle_gallery', true);
        $gallery = [];
        if ($gallery_ids_raw) {
            $ids = explode(',', $gallery_ids_raw);
            foreach ($ids as $id) {
                // Usar wp_get_attachment_image_url para obtener la URL
                $url = wp_get_attachment_image_url($id, 'large');
                if ($url) $gallery[] = $url;
            }
        }
        
        // Obtener tipo del vehículo desde el meta
        $type = get_post_meta($post->ID, '_type', true) ?: 'venta'; // Valor por defecto 'venta' si no existe
        
        // Obtener precio y puja actual
        $price = (float) get_post_meta($post->ID, '_price', true);
        $current_bid = (float) get_post_meta($post->ID, '_current_bid', true);
        // Si no hay puja actual y hay precio, usar precio como puja actual
        if ($current_bid <= 0 && $price > 0) {
            $current_bid = $price;
        }
        
        // Obtener moneda desde el meta
        $currency = get_post_meta($post->ID, '_currency', true) ?: 'USD'; // Valor por defecto 'USD' si no existe
        
        // --- CORREGIDO Y REFORZADO: Asegurar valores por defecto para fechas ---
        // Obtener valores crudos de las fechas desde el meta
        $raw_start_time = get_post_meta($post->ID, '_start_time', true);
        $raw_end_time = get_post_meta($post->ID, '_end_time', true);
        
        // Inicializar variables de fecha como null o string vacío
        $start_time_str = $raw_start_time ?: '0000-00-00 00:00:00'; // Valor por defecto seguro
        $end_time_str = $raw_end_time ?: '0000-00-00 00:00:00';     // Valor por defecto seguro
        // --- FIN CORREGIDO Y REFORZADO ---
        
        // Inicializar estado
        $status = 'active';
        if ($type === 'subasta') {
            // Obtener tiempo actual del servidor
            $current_time_str = current_time('Y-m-d H:i:s');
            $current_time = new DateTime($current_time_str);
            
            // --- CORREGIDO Y REFORZADO: Usar los valores asegurados con valores por defecto seguros ---
            // Asegurar que las fechas sean válidas antes de crear DateTime
            $start_time_obj = $start_time_str && $start_time_str !== '0000-00-00 00:00:00'
                ? new DateTime($start_time_str)
                : new DateTime('1970-01-01 00:00:00'); // Fecha por defecto segura
            $end_time_obj = $end_time_str && $end_time_str !== '0000-00-00 00:00:00'
                ? new DateTime($end_time_str)
                : new DateTime('2038-01-19 03:14:07'); // Fecha por defecto segura (máximo valor para timestamp 32-bit)
            // --- FIN CORREGIDO Y REFORZADO ---
            
            if ($current_time < $start_time_obj) {
                $status = 'upcoming';
            } elseif ($current_time > $end_time_obj) {
                $status = 'closed';
            } else {
                $status = 'live';
            }
        }
        
        // Devolver array asociativo con los datos del vehículo
        // Usar claves unificadas para todos los metadatos
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
            // --- CORREGIDO Y REFORZADO: Devolver los valores asegurados con valores por defecto seguros ---
            'start_time' => $start_time_str !== '0000-00-00 00:00:00' ? $start_time_str : null, // Devolver null si es el valor por defecto
            'end_time' => $end_time_str !== '0000-00-00 00:00:00' ? $end_time_str : null,     // Devolver null si es el valor por defecto
            // --- FIN CORREGIDO Y REFORZADO ---
            'auction_status' => $status,
            'featured' => get_post_meta($post->ID, '_featured', true) ?: '0', // Añadir campo destacado con clave unificada
            'image' => get_the_post_thumbnail_url($post->ID, 'large') ?: ($gallery[0] ?? 'https://placehold.co/600x400'),
            'gallery' => $gallery ?: [get_the_post_thumbnail_url($post->ID, 'large') ?: 'https://placehold.co/600x400']
        ];
    }
    // --- Fin Nueva función CORREGIDA ---

    public function get_dashboard_stats($request) {
        global $wpdb;

        // 1. Total de vehículos
        $total_vehicles = wp_count_posts('vehicle');
        $total_vehicles_count = $total_vehicles->publish;

        // 2. Pujas hoy
        $today_start = current_time('Y-m-d 00:00:00');
        $today_end = current_time('Y-m-d 23:59:59');
        $bids_today_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}autobid_bids
            WHERE created_at >= %s AND created_at <= %s
        ", $today_start, $today_end));

        // 3. Ventas hoy (vehículos tipo venta creados hoy)
        // NOTA: Esta consulta asume que una "venta" es un vehículo tipo 'venta' creado hoy.
        // Si tu sistema tiene otro concepto de "venta" (ej: subasta cerrada con ganador), necesitarías ajustarla.
        $sales_today_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_type = 'vehicle'
            AND post_status = 'publish'
            AND post_date >= %s
            AND post_date <= %s
            AND ID IN (
                SELECT post_id
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_type' AND meta_value = 'venta'
            )
        ", $today_start, $today_end));

        // 4. Total de usuarios
        $user_count = count_users();
        $total_users_count = $user_count['total_users'];

        return new WP_REST_Response([
            'total_vehicles' => $total_vehicles_count,
            'bids_today' => $bids_today_count,
            'sales_today' => $sales_today_count,
            'total_users' => $total_users_count
        ], 200);
    }

    // --- Nueva función: Obtener todas las pujas ---
    public function get_all_bids($request) {
        if (!current_user_can('administrator')) {
            return new WP_Error('forbidden', 'Acceso denegado. Requiere permisos de administrador.', ['status' => 403]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'autobid_bids';

        // Obtener todas las pujas con información del vehículo y usuario
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
    // --- Fin Nueva función ---

    // --- Nueva función: Obtener todas las ventas ---
    public function get_all_sales($request) {
        if (!current_user_can('administrator')) {
            return new WP_Error('forbidden', 'Acceso denegado. Requiere permisos de administrador.', ['status' => 403]);
        }

        // Buscar vehículos vendidos (subastas cerradas con ganador y ventas directas)
        $sold_auctions = get_posts([
            'post_type' => 'vehicle',
            'meta_query' => [
                [
                    'key' => '_type',
                    'value' => 'subasta',
                ],
                [
                    'key' => '_auction_status',
                    'value' => 'closed', // Solo subastas finalizadas
                ],
                [
                    'key' => '_highest_bidder', // Asegurar que hay un ganador
                    'compare' => 'EXISTS'
                ]
            ],
            'numberposts' => -1,
            'post_status' => 'publish',
        ]);

        $direct_sales = get_posts([
            'post_type' => 'vehicle',
            'meta_query' => [
                [
                    'key' => '_type',
                    'value' => 'venta',
                ],
            ],
            'numberposts' => -1,
            'post_status' => 'publish',
        ]);

        $sales_data = [
            'sold_auctions' => array_map([$this, 'format_vehicle_for_sales'], $sold_auctions),
            'direct_sales' => array_map([$this, 'format_vehicle_for_sales'], $direct_sales)
        ];

        return new WP_REST_Response($sales_data, 200);
    }
    // --- Fin Nueva función ---

    // --- Nueva función auxiliar: Formatear vehículo para ventas ---
    private function format_vehicle_for_sales($post) {
        // Reutilizar la lógica de format_vehicle existente
        $vehicle_data = $this->format_vehicle($post);
        
        // Añadir información específica de ventas
        if ($vehicle_data['type'] === 'subasta') {
            // Para subastas vendidas, obtener el ganador
            $winner_id = get_post_meta($post->ID, '_highest_bidder', true);
            $winner_user = $winner_id ? get_userdata($winner_id) : null;
            $vehicle_data['winner'] = $winner_user ? [
                'id' => $winner_user->ID,
                'name' => $winner_user->display_name,
                'email' => $winner_user->user_email
            ] : null;
            $vehicle_data['final_bid'] = (float) get_post_meta($post->ID, '_current_bid', true);
        } else {
            // Para ventas directas, no hay ganador ni puja final
            $vehicle_data['winner'] = null;
            $vehicle_data['final_bid'] = null;
        }
        
        return $vehicle_data;
    }
    // --- Fin Nueva función ---
}