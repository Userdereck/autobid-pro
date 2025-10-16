<?php
class AutoBid_Auction_Cron {
    public function __construct() {
        add_action('autobid_close_auctions', [$this, 'close_expired_auctions']);
        add_action('init', [$this, 'schedule_cron']);
    }

    public function schedule_cron() {
        if (false === as_next_scheduled_action('autobid_close_auctions')) {
            as_schedule_recurring_action(time(), 60, 'autobid_close_auctions');
        }
    }

    public function close_expired_auctions() {
        $current_time = current_time('Y-m-d H:i:s');
        $all_vehicles = get_posts([
            'post_type' => 'vehicle',
            'meta_query' => [['key' => '_type', 'value' => 'subasta']],
            'numberposts' => -1
        ]);

        foreach ($all_vehicles as $vehicle) {
            $end_time = get_post_meta($vehicle->ID, '_end_time', true);
            $status = get_post_meta($vehicle->ID, '_auction_status', true);

            if ($status === 'closed' || $status === 'closed_no_bids') continue;
            if (!$end_time || $end_time === '0000-00-00 00:00:00') continue;
            if ($end_time > $current_time) continue;

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
        }
    }

    private function notify_winner($vehicle_id, $user_id, $bid_amount) {
        $user = get_userdata($user_id);
        $vehicle = get_post($vehicle_id);
        $site_name = get_bloginfo('name');

        if (!$user || !$vehicle) return;

        $subject = '🏆 ¡Felicidades! Ganaste la subasta - ' . $vehicle->post_title;
        $message = "Hola {$user->display_name},\n\n¡Felicidades! Has ganado la subasta del vehículo \"{$vehicle->post_title}\" con una puja de $" . number_format($bid_amount, 2) . ".\n\nEl equipo de {$site_name} se pondrá en contacto contigo para coordinar la entrega.";
        wp_mail($user->user_email, $subject, $message);
    }
}

new AutoBid_Auction_Cron();