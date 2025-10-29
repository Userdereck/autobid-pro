<?php
// includes/class-auction-cron.php

class AutoBid_Auction_Cron {

    public function __construct() {
        add_action('autobid_close_auctions', [$this, 'close_expired_auctions']);
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
        add_action('init', [$this, 'schedule_cron']);
    }

    /** Añadir intervalo personalizado */
    public function add_cron_schedules($schedules) {
        if (!isset($schedules['every_minute'])) {
            $schedules['every_minute'] = [
                'interval' => 60,
                'display'  => __('Cada minuto', 'autobid')
            ];
        }
        return $schedules;
    }

    /** Programar cron */
    public function schedule_cron() {
        if (!wp_next_scheduled('autobid_close_auctions')) {
            wp_schedule_event(time(), 'every_minute', 'autobid_close_auctions');
            error_log("AutoBid Pro: Cron 'autobid_close_auctions' programado correctamente.");
        }
    }

    /** Función principal */
    public function close_expired_auctions() {
        error_log("AutoBid Pro: Ejecutando close_expired_auctions...");
        $now = current_time('Y-m-d H:i:s');

        // 1️⃣ Activar subastas pendientes
        $upcoming = get_posts([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query'  => [
                ['key' => '_type', 'value' => 'subasta'],
                ['key' => '_auction_status', 'value' => 'upcoming']
            ]
        ]);

        foreach ($upcoming as $vehicle) {
            $start = get_post_meta($vehicle->ID, '_start_time', true) ?: $vehicle->post_date;
            if (strtotime($start) <= strtotime($now)) {
                update_post_meta($vehicle->ID, '_auction_status', 'live');
                $this->notify_watchers($vehicle->ID);
                error_log("AutoBid Pro: Subasta iniciada → {$vehicle->post_title} (ID {$vehicle->ID})");
            }
        }

        // 2️⃣ Cerrar subastas vencidas
        $live = get_posts([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query'  => [
                ['key' => '_type', 'value' => 'subasta'],
                ['key' => '_auction_status', 'value' => 'live']
            ]
        ]);

        foreach ($live as $vehicle) {
            $end = get_post_meta($vehicle->ID, '_end_time', true);
            if ($end && strtotime($end) <= strtotime($now)) {
                global $wpdb;
                $highest = $wpdb->get_row($wpdb->prepare("
                    SELECT user_id, bid_amount
                    FROM {$wpdb->prefix}autobid_bids
                    WHERE vehicle_id = %d
                    ORDER BY bid_amount DESC
                    LIMIT 1
                ", $vehicle->ID));

                if ($highest) {
                    update_post_meta($vehicle->ID, '_auction_status', 'closed');
                    update_post_meta($vehicle->ID, '_winner_user_id', $highest->user_id);
                    $this->notify_winner($vehicle->ID, $highest->user_id, $highest->bid_amount);
                } else {
                    update_post_meta($vehicle->ID, '_auction_status', 'closed_no_bids');
                }

                error_log("AutoBid Pro: Subasta cerrada → {$vehicle->post_title} (ID {$vehicle->ID})");
            }
        }
    }

    /** 🔔 Notificar usuarios que siguen la subasta */
    private function notify_watchers($vehicle_id) {
        global $wpdb;
        $watchers = $wpdb->get_results($wpdb->prepare("
            SELECT user_id FROM {$wpdb->prefix}autobid_auction_watchlist
            WHERE vehicle_id = %d AND notified = 0
        ", $vehicle_id));

        if (empty($watchers)) return;

        $vehicle = get_post($vehicle_id);
        $site_name = get_bloginfo('name');
        $from_email = get_option('autobid_sender_email', get_option('admin_email'));
        $from_name  = get_option('autobid_sender_name', $site_name);

        foreach ($watchers as $watcher) {
            $user = get_userdata($watcher->user_id);
            if (!$user) continue;

            $vehicle_url = get_permalink($vehicle_id);
            $title = "🚨 ¡Tu subasta ha comenzado! - {$vehicle->post_title}";
            $content = "<p>El vehículo <strong>{$vehicle->post_title}</strong> ya está disponible para recibir pujas.</p>
                        <p><a href='{$vehicle_url}'>Ver subasta</a></p>";

            $body_html = autobid_build_email("¡Tu subasta ha comenzado!", $content);
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                "From: {$from_name} <{$from_email}>"
            ];

            $sent = wp_mail($user->user_email, $title, $body_html, $headers);
            error_log("📨 Notificación para {$user->user_email} → " . ($sent ? "OK" : "FALLO"));

            $wpdb->update(
                "{$wpdb->prefix}autobid_auction_watchlist",
                ['notified' => 1],
                ['vehicle_id' => $vehicle_id, 'user_id' => $user->ID]
            );
        }
    }

    /** 🏆 Notificar ganador */
    private function notify_winner($vehicle_id, $user_id, $bid_amount) {
        $user = get_userdata($user_id);
        $vehicle = get_post($vehicle_id);
        if (!$user || !$vehicle) return;

        $site_name = get_bloginfo('name');
        $admin_email = get_option('admin_email');
        $from_email  = get_option('autobid_sender_email', $admin_email);
        $from_name   = get_option('autobid_sender_name', $site_name);

        $vehicle_url = get_permalink($vehicle_id);
        $formatted_bid = '$' . number_format($bid_amount, 2);

        $subject_user = "🏆 ¡Felicidades! Ganaste la subasta - {$vehicle->post_title}";
        $subject_admin = "🏁 Subasta finalizada - {$vehicle->post_title}";

        $body_user = autobid_build_email(
            "¡Felicidades {$user->display_name}!",
            "<p>Has ganado la subasta del vehículo <strong>{$vehicle->post_title}</strong> con una puja de <strong>{$formatted_bid}</strong>.</p>
            <p><a href='{$vehicle_url}'>Ver detalles del vehículo</a></p>"
        );

        $body_admin = autobid_build_email(
            "Subasta finalizada - {$vehicle->post_title}",
            "<p>El vehículo <strong>{$vehicle->post_title}</strong> ha finalizado.</p>
            <ul>
                <li><strong>Ganador:</strong> {$user->display_name}</li>
                <li><strong>Email:</strong> {$user->user_email}</li>
                <li><strong>Puja:</strong> {$formatted_bid}</li>
            </ul>
            <p><a href='{$vehicle_url}'>Ver vehículo</a></p>"
        );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <{$from_email}>"
        ];

        $sent_user = wp_mail($user->user_email, $subject_user, $body_user, $headers);
        $sent_admin = wp_mail($admin_email, $subject_admin, $body_admin, $headers);

        error_log("📨 AutoBid Pro: Correos → User: " . ($sent_user ? "OK" : "FALLO") . " | Admin: " . ($sent_admin ? "OK" : "FALLO"));
    }

    /** Forzar manualmente notificación */
    public function force_notify_winner($vehicle_id, $user_id = 0, $amount = 0) {
        if (!$vehicle_id) return;
        if (!$user_id) $user_id = get_current_user_id();
        if (!$amount) $amount = 15000;
        error_log("AutoBid Pro: ⚙️ Forzando notificación de ganador ID {$vehicle_id}");
        $this->notify_winner($vehicle_id, $user_id, $amount);
    }
}

// --- 🔧 Prueba manual desde URL ---
add_action('init', function() {
    if (isset($_GET['force_close_auction']) && current_user_can('manage_options')) {
        $vehicle_id = intval($_GET['force_close_auction']);
        if ($vehicle_id > 0) {
            $cron = new AutoBid_Auction_Cron();
            error_log("AutoBid Pro: ⚠️ Forzando cierre manual de subasta ID {$vehicle_id}");
            $cron->force_notify_winner($vehicle_id);
            wp_die("✅ Correos enviados para subasta ID {$vehicle_id}");
        }
    }
});

/** 📨 Configuración SMTP con logs de depuración */
add_action('phpmailer_init', function($phpmailer) {
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
        $phpmailer->SMTPDebug = 2; // <-- añade log detallado al debug.log
        $phpmailer->Debugoutput = function($str) {
            error_log("AutoBid SMTP: " . trim($str));
        };
        $phpmailer->setFrom(
            get_option('autobid_sender_email', $user),
            get_option('autobid_sender_name', get_bloginfo('name'))
        );
    } else {
        error_log("⚠️ AutoBid SMTP: configuración incompleta. No se aplica SMTP.");
    }
});
