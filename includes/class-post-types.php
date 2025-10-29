<?php
class AutoBid_Post_Types {
    public function __construct() {
        add_action('init', [$this, 'register_post_types']);
        add_action('add_meta_boxes', [$this, 'add_vehicle_meta_boxes']);
        add_action('save_post_vehicle', [$this, 'save_vehicle_meta'], 10, 3);
    }
    public function register_post_types() {
        register_post_type('vehicle', [
            'label' => 'Vehículos',
            'public' => true,
            'show_ui' => true,
            'menu_icon' => 'dashicons-car',
            'supports' => ['title', 'editor', 'thumbnail'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'vehiculo'],
        ]);
    }
    public function add_vehicle_meta_boxes() {
        add_meta_box('vehicle_details', 'Detalles del Vehículo', [$this, 'render_main_meta_box'], 'vehicle', 'normal', 'high');
    }
    public function render_main_meta_box($post) {
        wp_nonce_field('vehicle_meta_box', 'vehicle_meta_nonce');
        $price = get_post_meta($post->ID, '_price', true);
        $brand = get_post_meta($post->ID, '_brand', true);
        $year = get_post_meta($post->ID, '_year', true);
        $location = get_post_meta($post->ID, '_location', true);
        $model = get_post_meta($post->ID, '_model', true);
        $color = get_post_meta($post->ID, '_color', true);
        $condition = get_post_meta($post->ID, '_condition', true) ?: 'usado';
        $featured = get_post_meta($post->ID, '_featured', true);
        $type = get_post_meta($post->ID, '_type', true);
        $currency = get_post_meta($post->ID, '_currency', true) ?: 'USD';
        $start_time = get_post_meta($post->ID, '_start_time', true);
        $end_time = get_post_meta($post->ID, '_end_time', true);
        $gallery = get_post_meta($post->ID, '_vehicle_gallery', true);
        $ids = $gallery ? explode(',', $gallery) : [];
        $images = array_filter(array_map('wp_get_attachment_image_url', $ids));
        echo '<table class="form-table">';
        echo '<tr><th><label for="vehicle_price">Precio</label></th>';
        echo '<td><input type="number" id="vehicle_price" name="vehicle_price" value="' . esc_attr($price) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="vehicle_currency">Moneda</label></th>';
        echo '<td>
            <select id="vehicle_currency" name="vehicle_currency">
                <option value="USD" ' . selected($currency, 'USD', false) . '>Dólar (USD)</option>
                <option value="EUR" ' . selected($currency, 'EUR', false) . '>Euro (EUR)</option>
                <option value="DOP" ' . selected($currency, 'DOP', false) . '>Peso Dominicano (DOP)</option>
                <option value="MXN" ' . selected($currency, 'MXN', false) . '>Peso Mexicano (MXN)</option>
                <option value="COP" ' . selected($currency, 'COP', false) . '>Peso Colombiano (COP)</option>
                <option value="PEN" ' . selected($currency, 'PEN', false) . '>Sol Peruano (PEN)</option>
            </select>
        </td></tr>';
        echo '<tr><th><label for="vehicle_ideal_price">Precio Ideal de Venta</label></th>';
        echo '<td><input type="number" id="vehicle_ideal_price" name="vehicle_ideal_price" value="' . esc_attr(get_post_meta($post->ID, '_ideal_price', true)) . '" class="regular-text">';
        echo '<p class="description">Precio mínimo deseado para considerar la subasta exitosa.</p></td></tr>';

        echo '<tr><th><label for="vehicle_brand">Marca</label></th>';
        echo '<td><input type="text" id="vehicle_brand" name="vehicle_brand" value="' . esc_attr($brand) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="vehicle_model">Modelo</label></th>';
        echo '<td><input type="text" id="vehicle_model" name="vehicle_model" value="' . esc_attr($model) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="vehicle_year">Año</label></th>';
        echo '<td><input type="number" id="vehicle_year" name="vehicle_year" value="' . esc_attr($year) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="vehicle_color">Color</label></th>';
        echo '<td><input type="text" id="vehicle_color" name="vehicle_color" value="' . esc_attr($color) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="vehicle_condition">Condición</label></th>';
        echo '<td>
            <select id="vehicle_condition" name="vehicle_condition">
                <option value="nuevo" ' . selected($condition, 'nuevo', false) . '>Nuevo</option>
                <option value="usado" ' . selected($condition, 'usado', false) . '>Usado</option>
            </select>
        </td></tr>';
        echo '<tr><th><label for="vehicle_location">Ubicación</label></th>';
        echo '<td><input type="text" id="vehicle_location" name="vehicle_location" value="' . esc_attr($location) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="vehicle_featured">Destacado</label></th>';
        echo '<td><input type="checkbox" id="vehicle_featured" name="vehicle_featured" value="1" ' . checked($featured, '1', false) . '> Mostrar en sección destacada</td></tr>';
        echo '<tr><th><label for="vehicle_type">Tipo</label></th>';
        echo '<td>
            <select id="vehicle_type" name="vehicle_type">
                <option value="venta" ' . selected($type, 'venta', false) . '>Venta directa</option>
                <option value="subasta" ' . selected($type, 'subasta', false) . '>Subasta</option>
            </select>
        </td></tr>';
        echo '<tr><th><label for="vehicle_start_time">Fecha de inicio (subasta)</label></th>';
        echo '<td>
            <input type="datetime-local" id="vehicle_start_time" name="vehicle_start_time" value="' . esc_attr($this->convert_to_datetime_local($start_time)) . '" class="regular-text">
            <p class="description">Dejar vacío para iniciar inmediatamente.</p>
        </td></tr>';
        echo '<tr><th><label for="vehicle_end_time">Fecha de fin (subasta)</label></th>';
        echo '<td>
            <input type="datetime-local" id="vehicle_end_time" name="vehicle_end_time" value="' . esc_attr($this->convert_to_datetime_local($end_time)) . '" class="regular-text">
            <p class="description">Obligatorio para subastas.</p>
        </td></tr>';
        echo '</table>';
        echo '<h3>Galería de Imágenes</h3>';
        echo '<div id="vehicle-gallery-container">';
        foreach ($images as $url) {
            echo '<img src="' . esc_url($url) . '" style="height:80px; margin:5px; border:1px solid #ddd; border-radius:4px;">';
        }
        echo '</div>';
        echo '<input type="hidden" name="vehicle_gallery" id="vehicle_gallery" value="' . esc_attr($gallery) . '">';
        echo '<button type="button" class="button" id="upload_gallery_button">Agregar imágenes</button>';
        echo '<script>
            jQuery(document).ready(function($) {
                $("#upload_gallery_button").on("click", function(e) {
                    e.preventDefault();
                    var frame = wp.media({
                        title: "Selecciona imágenes",
                        button: { text: "Usar seleccionadas" },
                        multiple: true,
                        library: { type: "image" }
                    });
                    frame.on("select", function() {
                        var selection = frame.state().get("selection");
                        var ids = [];
                        if ($("#vehicle_gallery").val()) ids = $("#vehicle_gallery").val().split(",");
                        selection.each(function(attachment) {
                            ids.push(attachment.id);
                        });
                        $("#vehicle_gallery").val(ids.join(","));
                        $("#vehicle-gallery-container").empty();
                        ids.forEach(function(id) {
                            var url = wp.media.attachment(id).get("url");
                            if (url) {
                                $("#vehicle-gallery-container").append(
                                    $("<img>", {
                                        src: url,
                                        style: "height:80px; margin:5px; border:1px solid #ddd; border-radius:4px;"
                                    })
                                );
                            }
                        });
                    });
                    frame.open();
                });
            });
        </script>';
    }
    private function convert_to_datetime_local($mysql_datetime) {
        if (!$mysql_datetime || $mysql_datetime === '0000-00-00 00:00:00') {
            return '';
        }
        $dt = new DateTime($mysql_datetime);
        return $dt->format('Y-m-d\TH:i');
    }
    public function save_vehicle_meta($post_id, $post, $update) {
        // Validaciones de seguridad
        if (!isset($_POST['vehicle_meta_nonce']) || !wp_verify_nonce($_POST['vehicle_meta_nonce'], 'vehicle_meta_box')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Determinar tipo de vehículo
        $type = 'venta';
        if (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'subasta') {
            $type = 'subasta';
        }
        update_post_meta($post_id, '_type', $type);

        // Guardar campos básicos
        $fields = ['price', 'brand', 'year', 'location', 'model', 'color', 'condition'];
        foreach ($fields as $field) {
            $key = "_{$field}";
            $value = sanitize_text_field($_POST["vehicle_{$field}"] ?? '');
            if ($value !== '') {
                update_post_meta($post_id, $key, $value);
            } else {
                delete_post_meta($post_id, $key);
            }
        }

        // 🔹 Nuevo campo: Precio ideal de venta
        if (isset($_POST['vehicle_ideal_price'])) {
            $ideal_price = floatval($_POST['vehicle_ideal_price']);
            update_post_meta($post_id, '_ideal_price', $ideal_price);
        }

        // Destacado
        $featured = isset($_POST['vehicle_featured']) ? '1' : '';
        update_post_meta($post_id, '_featured', $featured);

        // Moneda
        $currency = sanitize_text_field($_POST['vehicle_currency'] ?? 'USD');
        update_post_meta($post_id, '_currency', $currency);


        // Guardar precio ideal (solo si es subasta)
        if ($type === 'subasta' && isset($_POST['vehicle_ideal_price'])) {
            $ideal_price = floatval($_POST['vehicle_ideal_price']);
            update_post_meta($post_id, '_ideal_price', $ideal_price);
        } else {
            delete_post_meta($post_id, '_ideal_price');
        }


        // Si es subasta, guardar fechas
        if ($type === 'subasta') {
            if (isset($_POST['vehicle_start_time'])) {
                $start_input = sanitize_text_field($_POST['vehicle_start_time']);
                if ($start_input) {
                    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $start_input);
                    if ($dt) update_post_meta($post_id, '_start_time', $dt->format('Y-m-d H:i:s'));
                }
            }
            if (isset($_POST['vehicle_end_time'])) {
                $end_input = sanitize_text_field($_POST['vehicle_end_time']);
                if ($end_input) {
                    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $end_input);
                    if ($dt) update_post_meta($post_id, '_end_time', $dt->format('Y-m-d H:i:s'));
                }
            }
        } else {
            delete_post_meta($post_id, '_start_time');
            delete_post_meta($post_id, '_end_time');
        }

        // Galería
        if (isset($_POST['vehicle_gallery'])) {
            $gallery = sanitize_text_field($_POST['vehicle_gallery']);
            if ($gallery !== '') {
                update_post_meta($post_id, '_vehicle_gallery', $gallery);
            }
        }
    }

}