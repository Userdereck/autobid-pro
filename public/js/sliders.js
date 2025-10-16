document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.autobid-slider').forEach(container => {
        const type = container.dataset.type;
        const title = container.dataset.title;
        
        // Renderizar contenedor
        container.innerHTML = `
            <div class="autobid-slider-container">
                <h3>${title}</h3>
                <div class="swiper">
                    <div class="swiper-wrapper"></div>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-pagination"></div>
                </div>
            </div>
        `;

        const wrapper = container.querySelector('.swiper-wrapper');
        let url = autobid_slider_data.api_url;
        
        if (type === 'sales') {
            url += '?type=venta';
        } else {
            url += '?type=subasta';
        }

        fetch(url, {
            headers: { 'X-WP-Nonce': autobid_slider_data.nonce }
        })
        .then(res => res.json())
        .then(vehicles => {
            let items = [];
            if (type === 'sales') {
                items = vehicles;
            } else {
                const status = type === 'upcoming' ? 'upcoming' : 'live';
                items = vehicles.filter(v => v.auction_status === status);
            }

            if (items.length === 0) {
                wrapper.innerHTML = `<div class="swiper-slide"><p>No hay ${type === 'sales' ? 'ventas' : (type === 'upcoming' ? 'subastas próximas' : 'subastas en curso')}.</p></div>`;
                return;
            }

            items.forEach(v => {
                const slide = document.createElement('div');
                slide.className = 'swiper-slide';
                slide.innerHTML = `
                    <div class="auction-slide-item">
                        <div class="slide-image">
                            <img src="${v.image}" alt="${v.name}" loading="lazy">
                        </div>
                        <div class="slide-content">
                            <h4>${v.name}</h4>
                            <p class="slide-date">${type === 'sales' ? 'Venta directa' : (type === 'upcoming' ? 'Empieza: ' + new Date(v.start_time).toLocaleDateString() : 'En curso')}</p>
                            <p class="slide-price">${new Intl.NumberFormat('es-ES', { style: 'currency', currency: v.currency }).format(v.price)}</p>
                            <a href="${autobid_slider_data.detail_url}?id=${v.id}" class="slide-button">Ver detalles</a>
                        </div>
                    </div>
                `;
                wrapper.appendChild(slide);
            });

            new Swiper(container.querySelector('.swiper'), {
                slidesPerView: 1,
                spaceBetween: 20,
                autoplay: { delay: autobid_slider_data.delay, disableOnInteraction: false },
                effect: autobid_slider_data.effect,
                pagination: { el: '.swiper-pagination', clickable: true },
                navigation: { 
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev'
                },
                breakpoints: {
                    640: { slidesPerView: 2 },
                    768: { slidesPerView: 3 },
                    1024: { slidesPerView: 4 }
                }
            });
        })
        .catch(err => {
            console.error('Error:', err);
            wrapper.innerHTML = '<div class="swiper-slide"><p>Error al cargar.</p></div>';
        });
    });
});