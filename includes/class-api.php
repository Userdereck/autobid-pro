<?php
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
        register_rest_route('autobid/v1', '/vehicles/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_vehicle'],
            'permission_callback' => '__return_true'
        ]);
        register_rest_route('autobid/v1', '/vehicles/(?P<id>\d+)/bid', [
            'methods' => 'POST',
            'callback' => [$this, 'place_bid'],
            'permission_callback' => [$this, 'check_user_logged_in']
        ]);
    }
    public function check_user_logged_in() {
        return is_user_logged_in();
    }
    public function get_vehicles($request = null) {
        $type = null;
        if ($request) {
            $type = $request->get_param('type');
        }
        $meta_query = [['key' => '_type', 'value' => 'vehicle']];
        if ($type === 'venta' || $type === 'subasta') {
            $meta_query = [['key' => '_type', 'value' => $type]];
        }
        $posts = get_posts([
            'post_type' => 'vehicle',
            'meta_query' => $meta_query,
            'numberposts' => -1,
            'post_status' => 'publish'
        ]);
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
        if (!is_user_logged_in()) {
            return new WP_Error('unauthorized', 'Debes iniciar sesión para pujar.', ['status' => 401]);
        }
        $vehicle_id = (int) $request['id'];
        $bid_amount = (float) $request['bid_amount'];
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
            return new WP_Error('db_error', 'Error al registrar la puja.', ['status' => 500]);
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
    private function format_vehicle($post) {
        $gallery_ids = get_post_meta($post->ID, '_vehicle_gallery', true);
        $gallery = [];
        if ($gallery_ids) {
            $ids = explode(',', $gallery_ids);
            foreach ($ids as $id) {
                $url = wp_get_attachment_image_url($id, 'large');
                if ($url) $gallery[] = $url;
            }
        }
        $type = get_post_meta($post->ID, '_type', true) ?: 'venta';
        $price = (float) get_post_meta($post->ID, '_price', true);
        $current_bid = (float) get_post_meta($post->ID, '_current_bid', true);
        $currency = get_post_meta($post->ID, '_currency', true) ?: 'USD';
        if ($current_bid <= 0 && $price > 0) {
            $current_bid = $price;
        }
        $status = 'active';
        if ($type === 'subasta') {
            $current_time_str = current_time('Y-m-d H:i:s');
            $current_time = new DateTime($current_time_str);
            $start_time_str = get_post_meta($post->ID, '_start_time', true);
            $end_time_str = get_post_meta($post->ID, '_end_time', true);
            $start_time = $start_time_str && $start_time_str !== '0000-00-00 00:00:00' 
                ? new DateTime($start_time_str) 
                : new DateTime('1970-01-01 00:00:00');
            $end_time = $end_time_str && $end_time_str !== '0000-00-00 00:00:00' 
                ? new DateTime($end_time_str) 
                : new DateTime('2038-01-19 03:14:07');
            if ($current_time < $start_time) {
                $status = 'upcoming';
            } elseif ($current_time > $end_time) {
                $status = 'closed';
            } else {
                $status = 'live';
            }
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
            'start_time' => $start_time_str,
            'end_time' => $end_time_str,
            'auction_status' => $status,
            'image' => get_the_post_thumbnail_url($post->ID, 'large') ?: ($gallery[0] ?? 'https://placehold.co/600x400'),
            'gallery' => $gallery ?: [get_the_post_thumbnail_url($post->ID, 'large') ?: 'https://placehold.co/600x400']
        ];
    }
}