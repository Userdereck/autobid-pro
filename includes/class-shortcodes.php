<?php
// includes/class-shortcodes.php

class AutoBid_Shortcodes {
    public function __construct() {
        add_shortcode('autobid_catalog', [$this, 'render_catalog']);
        add_shortcode('autobid_sales', [$this, 'render_sales']);
        add_shortcode('autobid_auctions', [$this, 'render_auctions']);
        add_shortcode('autobid_upcoming_auctions', [$this, 'upcoming_auctions_shortcode']);
        add_shortcode('autobid_live_auctions', [$this, 'live_auctions_shortcode']);
        add_shortcode('autobid_direct_sales_slider', [$this, 'direct_sales_slider_shortcode']);
        add_shortcode('autobid_featured_vehicles', [$this, 'featured_vehicles_shortcode']);
        add_shortcode('autobid_latest_vehicles', [$this, 'latest_vehicles_shortcode']);
    }

    public function render_sales() {
        return $this->render_catalog(['type' => 'venta']);
    }

    public function render_auctions() {
        return $this->render_catalog(['type' => 'subasta']);
    }

    public function render_catalog($atts) {
        $atts = shortcode_atts(['type' => ''], $atts);
        $type = in_array($atts['type'], ['venta', 'subasta']) ? $atts['type'] : '';
        ob_start();
        ?>
        <div class="autobid-catalog" data-type="<?php echo esc_attr($type); ?>">
            <div class="autobid-search-bar">
                <input type="text" id="global-search" placeholder="Buscar por marca, modelo, color o ubicación...">
            </div>
            <div class="autobid-layout">
                <div class="autobid-filters">
                    <h3>Filtros</h3>
                    <label for="filter-brand">Marca</label>
                    <select id="filter-brand"><option value="">Todas</option></select>
                    <label for="filter-model">Modelo</label>
                    <input type="text" id="filter-model" placeholder="Ej: Corolla">
                    <label for="filter-year">Año</label>
                    <select id="filter-year"><option value="">Cualquiera</option></select>
                    <label for="filter-color">Color</label>
                    <input type="text" id="filter-color" placeholder="Ej: Rojo">
                    <label for="filter-condition">Condición</label>
                    <select id="filter-condition">
                        <option value="">Cualquiera</option>
                        <option value="nuevo">Nuevo</option>
                        <option value="usado">Usado</option>
                    </select>
                    <label for="filter-price-min">Precio desde</label>
                    <input type="number" id="filter-price-min" placeholder="0">
                    <label for="filter-price-max">Precio hasta</label>
                    <input type="number" id="filter-price-max" placeholder="999999">
                    <?php if (!$type): ?>
                    <label for="filter-type">Tipo</label>
                    <select id="filter-type">
                        <option value="">Todos</option>
                        <option value="venta"><?php echo esc_html(get_option('autobid_label_sale', 'Venta')); ?></option>
                        <option value="subasta"><?php echo esc_html(get_option('autobid_label_auction', 'Subasta')); ?></option>
                    </select>
                    <?php endif; ?>
                    <div class="filter-actions">
                        <button class="btn-apply" id="apply-filters">Aplicar</button>
                        <button class="btn-reset" id="reset-filters">Restablecer</button>
                    </div>
                </div>
                <div class="autobid-main">
                    <div class="autobid-sort-bar">
                        <span id="results-count">Cargando...</span>
                        <select id="sort-by">
                            <option value="newest">Más recientes</option>
                            <option value="price-asc">Precio: menor a mayor</option>
                            <option value="price-desc">Precio: mayor a menor</option>
                        </select>
                    </div>
                    <div class="vehicle-grid"><p>Cargando vehículos...</p></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function upcoming_auctions_shortcode() {
        return $this->render_slider_shortcode('upcoming', 'Próximas Subastas');
    }

    public function live_auctions_shortcode() {
        return $this->render_slider_shortcode('live', 'Subastas en Curso');
    }

    public function direct_sales_slider_shortcode() {
        return $this->render_direct_sales_slider();
    }

    public function featured_vehicles_shortcode($atts) {
        $atts = shortcode_atts(['limit' => 6], $atts);
        return $this->render_featured_vehicles((int) $atts['limit']);
    }

    public function latest_vehicles_shortcode($atts) {
        $atts = shortcode_atts(['limit' => 6, 'type' => 'all'], $atts);
        return $this->render_latest_vehicles((int) $atts['limit'], $atts['type']);
    }

     private function render_slider_shortcode($status, $title) {
        $delay = (int) get_option('autobid_slider_delay', 4000);
        $speed = (int) get_option('autobid_slider_speed', 600);
        ob_start();
        ?>
        <div class="autobid-slider-container">
            <h3><?php echo esc_html($title); ?></h3>
            <div class="swiper autobid-<?php echo esc_attr($status); ?>-slider">
                <div class="swiper-wrapper" data-status="<?php echo esc_attr($status); ?>">
                    <!-- Carga dinámica -->
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-pagination"></div>
            </div>
        </div>
        <script>
        (function() {
            if (typeof window.autobidSlidersLoaded === 'undefined') {
                window.autobidSlidersLoaded = true;
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js';
                script.onload = function() {
                    document.dispatchEvent(new Event('swipersLoaded'));
                };
                document.head.appendChild(script);
                const style = document.createElement('link');
                style.rel = 'stylesheet';
                style.href = 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css';
                document.head.appendChild(style);
            }
        })();
        document.addEventListener('swipersLoaded', function() {
            const wrapper = document.querySelector('.swiper-wrapper[data-status="<?php echo $status; ?>"]');
            if (!wrapper) return;
            fetch('<?php echo rest_url("autobid/v1/vehicles?type=subasta"); ?>', {
                headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' }
            })
            .then(res => res.json())
            .then(vehicles => {
                const filtered = vehicles.filter(v => v.auction_status === '<?php echo $status; ?>');
                if (filtered.length === 0) {
                    wrapper.innerHTML = '<div class="swiper-slide"><p>No hay subastas <?php echo $status === "upcoming" ? "próximas" : "en curso"; ?>.</p></div>';
                    return;
                }
                filtered.forEach(v => {
                    const slide = document.createElement('div');
                    slide.className = 'swiper-slide';
                    slide.innerHTML = `
                        <div class="auction-slide-item">
                            <div class="slide-image">
                                <img src="${v.image}" alt="${v.name}" loading="lazy">
                            </div>
                            <div class="slide-content">
                                <h4>${v.name}</h4>
                                <p class="slide-date">${v.start_time ? new Date(v.start_time).toLocaleDateString() : 'Inmediato'}</p>
                                <a href="<?php echo get_permalink(get_option('autobid_detail_page_id')) ?: '#'; ?>?id=${v.id}" class="slide-button">Ver detalles</a>
                            </div>
                        </div>
                    `;
                    wrapper.appendChild(slide);
                });
                new Swiper('.autobid-<?php echo $status; ?>-slider', {
                    slidesPerView: 1,
                    spaceBetween: 20,
                    autoplay: {
                        delay: <?php echo $delay; ?>,
                        disableOnInteraction: false,
                    },
                    speed: <?php echo $speed; ?>,
                    pagination: { el: '.swiper-pagination', clickable: true },
                    navigation: { 
                        nextEl: '.swiper-button-next',
                        prevEl: '.swiper-button-prev'
                    },
                    breakpoints: {
                        640: { slidesPerView: 2 },
                        768: { slidesPerView: 3 },
                        1024: { slidesPerView: 4 }
                    },
                    on: {
                        init() {
                            const slider = this.el;
                            slider.addEventListener('mouseenter', () => this.autoplay.stop());
                            slider.addEventListener('mouseleave', () => this.autoplay.start());
                        }
                    }
                });
            })
            .catch(err => {
                console.error('Error al cargar subastas:', err);
                wrapper.innerHTML = '<div class="swiper-slide"><p>Error al cargar datos.</p></div>';
            });
        });
        </script>
        <style>
        .autobid-slider-container {
            margin: 2rem 0;
            padding: 0 1.5rem;
        }
        .autobid-slider-container h3 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: var(--primary, #1e3c72);
        }
        .auction-slide-item {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .slide-image {
            height: 160px;
            overflow: hidden;
        }
        .slide-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .slide-content {
            padding: 15px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .slide-content h4 {
            font-size: 1.1rem;
            margin: 0 0 10px;
            color: var(--primary, #1e3c72);
        }
        .slide-date {
            font-size: 0.9rem;
            color: #6c757d;
            margin: 0 0 15px;
        }
        .slide-button {
            display: inline-block;
            background: var(--primary, #1e3c72);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            margin-top: auto;
            text-align: center;
        }
        .slide-button:hover {
            opacity: 0.9;
        }
        </style>
        <?php
        return ob_get_clean();
    }

    private function render_direct_sales_slider() {
        $delay = (int) get_option('autobid_slider_delay', 4000);
        $speed = (int) get_option('autobid_slider_speed', 600);
        ob_start();
        ?>
        <div class="autobid-slider-container">
            <h3><?php echo esc_html(get_option('autobid_label_sale', 'Ventas Directas')); ?></h3>
            <div class="swiper autobid-direct-sales-slider">
                <div class="swiper-wrapper" data-type="venta">
                    <!-- Carga dinámica -->
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-pagination"></div>
            </div>
        </div>
        <script>
        (function() {
            if (typeof window.autobidSlidersLoaded === 'undefined') {
                window.autobidSlidersLoaded = true;
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js';
                script.onload = function() {
                    document.dispatchEvent(new Event('swipersLoaded'));
                };
                document.head.appendChild(script);
                const style = document.createElement('link');
                style.rel = 'stylesheet';
                style.href = 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css';
                document.head.appendChild(style);
            }
        })();
        document.addEventListener('swipersLoaded', function() {
            const wrapper = document.querySelector('.swiper-wrapper[data-type="venta"]');
            if (!wrapper) return;
            fetch('<?php echo rest_url("autobid/v1/vehicles?type=venta"); ?>', {
                headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' }
            })
            .then(res => res.json())
            .then(vehicles => {
                if (vehicles.length === 0) {
                    wrapper.innerHTML = '<div class="swiper-slide"><p>No hay vehículos en venta directa.</p></div>';
                    return;
                }
                vehicles.forEach(v => {
                    const slide = document.createElement('div');
                    slide.className = 'swiper-slide';
                    slide.innerHTML = `
                        <div class="auction-slide-item">
                            <div class="slide-image">
                                <img src="${v.image}" alt="${v.name}" loading="lazy">
                            </div>
                            <div class="slide-content">
                                <h4>${v.name}</h4>
                                <p class="slide-price"><?php echo get_option('autobid_label_sale', 'Venta'); ?>: ${v.price ? new Intl.NumberFormat('es-ES', { style: 'currency', currency: v.currency }).format(v.price) : 'Consultar'}</p>
                                <a href="<?php echo get_permalink(get_option('autobid_detail_page_id')) ?: '#'; ?>?id=${v.id}" class="slide-button">Ver detalles</a>
                            </div>
                        </div>
                    `;
                    wrapper.appendChild(slide);
                });
                new Swiper('.autobid-direct-sales-slider', {
                    slidesPerView: 1,
                    spaceBetween: 20,
                    autoplay: {
                        delay: <?php echo $delay; ?>,
                        disableOnInteraction: false,
                    },
                    speed: <?php echo $speed; ?>,
                    pagination: { el: '.swiper-pagination', clickable: true },
                    navigation: { 
                        nextEl: '.swiper-button-next',
                        prevEl: '.swiper-button-prev'
                    },
                    breakpoints: {
                        640: { slidesPerView: 2 },
                        768: { slidesPerView: 3 },
                        1024: { slidesPerView: 4 }
                    },
                    on: {
                        init() {
                            const slider = this.el;
                            slider.addEventListener('mouseenter', () => this.autoplay.stop());
                            slider.addEventListener('mouseleave', () => this.autoplay.start());
                        }
                    }
                });
            })
            .catch(err => {
                console.error('Error al cargar ventas directas:', err);
                wrapper.innerHTML = '<div class="swiper-slide"><p>Error al cargar datos.</p></div>';
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    private function render_featured_vehicles($limit = 6) {
        $vehicles = get_posts([
            'post_type' => 'vehicle',
            'meta_query' => [['key' => '_featured', 'value' => '1']],
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        return $this->render_simple_vehicle_grid($vehicles, 'Vehículos Destacados');
    }

    private function render_latest_vehicles($limit = 6, $type = 'all') {
        $meta_query = [];
        if ($type === 'venta' || $type === 'subasta') {
            $meta_query = [['key' => '_type', 'value' => $type]];
        }
        $vehicles = get_posts([
            'post_type' => 'vehicle',
            'meta_query' => $meta_query,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        if ($type === 'venta') {
            $title = 'Últimas Ventas Directas';
        } elseif ($type === 'subasta') {
            $title = 'Últimas Subastas';
        } else {
            $title = 'Últimos Vehículos';
        }
        return $this->render_simple_vehicle_grid($vehicles, $title);
    }

    private function render_simple_vehicle_grid($posts, $title = '') {
        if (empty($posts)) return '<p>No hay vehículos disponibles.</p>';
        $api = new AutoBid_API();
        $html = '<div class="autobid-simple-grid">';
        if ($title) $html .= "<h3>{$title}</h3>";
        $html .= '<div class="vehicle-grid">';
        foreach ($posts as $post) {
            $v = $api->format_vehicle($post);
            $badgeText = $v['type'] === 'subasta' ? 'Subasta' : 'Venta';
            $price = $v['price'] ? $this->format_currency_frontend($v['price'], $v['currency']) : 'Consultar';
            // --- MODIFICADO: Generar URL de detalle ---
            $detail_url = home_url('/detalle/') . $v['id'] . '/'; // Nuevo formato
            // Opcional: $detail_url = home_url('/?view=detail&id=') . $v['id']; // Formato viejo
            // --- FIN MODIFICADO ---
            $html .= "
                <div class='vehicle-card'>
                    <div class='vehicle-image'>
                        <img src='{$v['image']}' alt='{$v['name']}' loading='lazy'>
                        <span class='vehicle-type-badge' data-status='{$v['type']}'>{$badgeText}</span>
                    </div>
                    <div class='vehicle-info'>
                        <h3>{$v['name']}</h3>
                        <div class='specs'>
                            <span>{$v['brand']}</span>
                            <span>{$v['year']}</span>
                        </div>
                        <div class='price'>{$price}</div>
                        <a href='{$detail_url}' class='btn-view-detail'>Ver detalles</a>
                    </div>
                </div>
            ";
        }
        $html .= '</div></div>';
        return $html;
    }

    private function format_currency_frontend($amount, $currency = 'USD') {
        if (!$amount || !is_numeric($amount)) $amount = 0;
        return number_format($amount, 0, ',', '.') . ' ' . $currency;
    }
}