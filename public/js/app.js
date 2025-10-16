import { showToast } from './utils.js';

const API_BASE = autobid_vars.api_url;
let allVehicles = [];

async function loadVehicles() {
  try {
    const response = await fetch(API_BASE, {
      headers: { 'X-WP-Nonce': autobid_vars.nonce }
    });
    
    if (!response.ok) throw new Error('Error al cargar los vehículos');
    
    allVehicles = await response.json();
    renderVehicles(allVehicles);
    document.getElementById('results-count').textContent = `${allVehicles.length} equipos disponibles`;
  } catch (error) {
    console.error('Error:', error);
    document.getElementById('vehicles-grid').innerHTML = '<p class="error">❌ No se pudieron cargar los equipos.</p>';
    showToast('Error al conectar con el servidor.', 'error');
  }
}

function renderVehicles(vehicles) {
  const grid = document.getElementById('vehicles-grid');
  if (vehicles.length === 0) {
    grid.innerHTML = '<p>No hay vehículos disponibles.</p>';
    return;
  }

  const html = vehicles.map(v => `
    <div class="vehicle-card">
      <img src="${v.image}" alt="${v.name}" class="vehicle-img">
      <div class="vehicle-content">
        <h3 class="vehicle-title">${v.name}</h3>
        <div class="vehicle-specs">
          <span>📍 ${v.location}</span>
          <span>📅 ${v.year}</span>
          <span>⚙️ ${v.brand}</span>
        </div>
        <div class="vehicle-price">${formatCurrency(v.price)}</div>
        <button class="action-btn" data-id="${v.id}">Ver detalles</button>
      </div>
    </div>
  `).join('');

  grid.innerHTML = html;

  grid.querySelectorAll('.action-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const id = e.currentTarget.dataset.id;
      window.location.href = `${autobid_vars.detail_page_url}?id=${id}`;
    });
  });
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0
  }).format(amount);
}

document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('vehicles-grid')) {
    loadVehicles();
  }
});