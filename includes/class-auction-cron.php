<?php
// includes/class-auction-cron.php
class AutoBid_Auction_Cron {
    public function __construct() {
        // Registrar la acción que cierra subastas expiradas
        add_action('autobid_close_auctions', [$this, 'close_expired_auctions']);

        // Añadir el intervalo personalizado 'every_minute' al filtro de cron
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);

        // Programar el evento usando WP-Cron
        add_action('init', [$this, 'schedule_cron']);
    }

    // Añadir el intervalo 'every_minute'
    public function add_cron_schedules($schedules) {
        // Verificar si el intervalo ya existe para evitar duplicados
        if (!isset($schedules['every_minute'])) {
            $schedules['every_minute'] = [
                'interval' => 60, // segundos
                'display'  => 'Every Minute'
            ];
        }
        return $schedules;
    }

    public function schedule_cron() {
        // Usar wp_cron en lugar de Action Scheduler
        // Verificar si el evento ya está programado para evitar duplicados
        if (!wp_next_scheduled('autobid_close_auctions')) {
            // wp_schedule_event puede programar eventos recurrentes
            // Usamos el intervalo personalizado 'every_minute'
            $result = wp_schedule_event(time(), 'every_minute', 'autobid_close_auctions');
            if ($result === false) {
                // Opcional: Loggear un error si la programación falla
                error_log("AutoBid Pro: Error al programar el evento wp_schedule_event para 'autobid_close_auctions'.");
            }
        }
    }

    public function close_expired_auctions() {
        error_log("AutoBid Pro: close_expired_auctions ejecutado.");
        $current_time = current_time('Y-m-d H:i:s');

        // 1. INICIAR subastas que ya deben comenzar
        $upcoming_vehicles = get_posts([
            'post_type' => 'vehicle',
            'meta_query' => [
                ['key' => '_type', 'value' => 'subasta'],
                ['key' => '_auction_status', 'value' => 'upcoming']
            ],
            'numberposts' => -1,
            'post_status' => 'publish'
        ]);

        foreach ($upcoming_vehicles as $vehicle) {
            $start_time = get_post_meta($vehicle->ID, '_start_time', true);
            if (!$start_time || $start_time === '0000-00-00 00:00:00') {
                $start_time = $vehicle->post_date; // fallback
            }
            if (strtotime($start_time) <= strtotime($current_time)) {
                update_post_meta($vehicle->ID, '_auction_status', 'live');
                $this->notify_watchers($vehicle->ID);
                error_log("AutoBid Pro: Subasta iniciada - ID: {$vehicle->ID}");
            }
        }

        // 2. CERRAR subastas que ya terminaron
        $live_vehicles = get_posts([
            'post_type' => 'vehicle',
            'meta_query' => [
                ['key' => '_type', 'value' => 'subasta'],
                ['key' => '_auction_status', 'value' => 'live']
            ],
            'numberposts' => -1,
            'post_status' => 'publish'
        ]);

        foreach ($live_vehicles as $vehicle) {
            $end_time = get_post_meta($vehicle->ID, '_end_time', true);
            if ($end_time && $end_time !== '0000-00-00 00:00:00' && strtotime($end_time) <= strtotime($current_time)) {
                global $wpdb;
                $highest_bid = $wpdb->get_row($wpdb->prepare("
                    SELECT user_id, bid_amount
                    FROM {$wpdb->prefix}autobid_bids
                    WHERE vehicle_id = %d
                    ORDER BY bid_amount DESC
                    LIMIT 1
                ", $vehicle->ID));

                if ($highest_bid) {
                    update_post_meta($vehicle->ID, '_auction_status', 'closed');
                    update_post_meta($vehicle->ID, '_winner_user_id', $highest_bid->user_id);
                    $this->notify_winner($vehicle->ID, $highest_bid->user_id, $highest_bid->bid_amount);
                } else {
                    update_post_meta($vehicle->ID, '_auction_status', 'closed_no_bids');
                }
                error_log("AutoBid Pro: Subasta cerrada - ID: {$vehicle->ID}");
            }
        }
    }

    private function notify_watchers($vehicle_id) {
        global $wpdb;
        $watchers = $wpdb->get_results($wpdb->prepare("
            SELECT user_id FROM {$wpdb->prefix}autobid_auction_watchlist
            WHERE vehicle_id = %d AND notified = 0
        ", $vehicle_id));

        $vehicle = get_post($vehicle_id);
        $site_name = get_bloginfo('name');
        $admin_whatsapp = get_option('autobid_whatsapp_number', '');

        foreach ($watchers as $watcher) {
            $user = get_userdata($watcher->user_id);
            if (!$user) continue;

            // Notificar por WhatsApp al usuario
            $user_phone = get_user_meta($user->ID, 'phone', true);
            if (!empty($user_phone)) {
                $clean_phone = preg_replace('/[^0-9+]/', '', $user_phone);
                if (!empty($clean_phone)) {
                    $message = "🔔 *¡Tu subasta ha comenzado!* \n\n" .
                            "El vehículo *{$vehicle->post_title}* ya está en subasta.\n" .
                            "Visítalo ahora en: " . get_permalink($vehicle_id) . "\n" .
                            "Gracias por usar *{$site_name}*.";
                    $whatsapp_url = "https://wa.me/{$clean_phone}?text=" . urlencode($message);
                    // Abrir en background (no redirigir desde backend)
                    wp_remote_get($whatsapp_url); // Esto no envía mensaje, solo abre en navegador del servidor → NO FUNCIONA
                    // ⚠️ Mejor: solo registrar y notificar desde frontend o con API empresarial
                }
            }

            // Marcar como notificado
            $wpdb->update(
                $wpdb->prefix . 'autobid_auction_watchlist',
                ['notified' => 1],
                ['vehicle_id' => $vehicle_id, 'user_id' => $user->ID]
            );
        }
    }

    private function notify_winner($vehicle_id, $user_id, $bid_amount) {
        $user = get_userdata($user_id);
        $vehicle = get_post($vehicle_id);
        $site_name = get_bloginfo('name');

        if (!$user || !$vehicle) {
            error_log("AutoBid Pro: Error al notificar ganador - Usuario o vehículo no encontrado. User ID: $user_id, Vehicle ID: $vehicle_id");
            return; // Salir si no se puede obtener la info del usuario o vehículo
        }

        $subject = '🏆 ¡Felicidades! Ganaste la subasta - ' . $vehicle->post_title;
        $message = "Hola {$user->display_name},\n\n¡Felicidades! Has ganado la subasta del vehículo \"{$vehicle->post_title}\" con una puja de $" . number_format($bid_amount, 2) . ".\n\nEl equipo de {$site_name} se pondrá en contacto contigo para coordinar la entrega.";
        $sent = wp_mail($user->user_email, $subject, $message);

        if (!$sent) {
             error_log("AutoBid Pro: Error al enviar email de notificación al ganador. User Email: {$user->user_email}, Vehicle ID: $vehicle_id");
        } else {
             error_log("AutoBid Pro: Email de notificación enviado al ganador. User Email: {$user->user_email}, Vehicle ID: $vehicle_id");
        }
    }
}

// Instanciar la clase - OK aquí
// Esta línea debe estar *fuera* de cualquier función y en el contexto global
// donde WordPress haya sido cargado.
// Ya está en autobid-pro.php -> new AutoBid_Auction_Cron();
// Por lo tanto, puedes comentar o eliminar esta línea del archivo class-auction-cron.php
// new AutoBid_Auction_Cron(); // Comentar o eliminar esta línea de este archivo