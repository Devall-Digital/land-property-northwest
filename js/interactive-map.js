/* ===================================
   NORTHWEST PROPERTY & LAND
   Interactive Map JavaScript
   =================================== */

// Interactive Map for Land Development Section
class InteractiveMap {
    constructor() {
        this.map = null;
        this.markers = [];
        this.selectedProperty = null;
        this.init();
    }

    init() {
        // Check if Mapbox is loaded
        if (typeof mapboxgl === 'undefined') {
            console.warn('Mapbox GL JS not loaded. Using fallback map.');
            this.createFallbackMap();
            return;
        }

        this.createMap();
        this.addMapControls();
        this.loadDevelopmentSites();
        this.initMapEvents();
    }

    createMap() {
        // Initialize Mapbox map
        mapboxgl.accessToken = 'pk.eyJ1Ijoibm9ydGh3ZXN0LXByb3BlcnR5IiwiYSI6ImNrZXhhbXBsZSJ9.example';
        
        this.map = new mapboxgl.Map({
            container: 'landMap',
            style: 'mapbox://styles/mapbox/dark-v11',
            center: [-2.2426, 53.4808], // Manchester coordinates
            zoom: 10,
            pitch: 45,
            bearing: 0
        });

        // Add navigation controls
        this.map.addControl(new mapboxgl.NavigationControl(), 'top-right');
        
        // Add fullscreen control
        this.map.addControl(new mapboxgl.FullscreenControl(), 'top-right');
    }

    createFallbackMap() {
        const mapContainer = document.getElementById('landMap');
        if (!mapContainer) return;

        // Create a styled fallback map
        mapContainer.innerHTML = `
            <div class="fallback-map">
                <div class="map-overlay">
                    <h3>Interactive Development Map</h3>
                    <p>Loading advanced mapping features...</p>
                    <div class="map-placeholder">
                        <div class="map-grid"></div>
                        <div class="development-sites">
                            <div class="site-marker" style="top: 30%; left: 25%;" data-site="manchester-central">
                                <div class="marker-pulse"></div>
                                <div class="marker-info">
                                    <h4>Manchester Central</h4>
                                    <p>£2.5M Development Site</p>
                                    <span class="roi">ROI: 18.5%</span>
                                </div>
                            </div>
                            <div class="site-marker" style="top: 45%; left: 60%;" data-site="liverpool-waterfront">
                                <div class="marker-pulse"></div>
                                <div class="marker-info">
                                    <h4>Liverpool Waterfront</h4>
                                    <p>£4.2M Mixed-Use</p>
                                    <span class="roi">ROI: 22.3%</span>
                                </div>
                            </div>
                            <div class="site-marker" style="top: 65%; left: 40%;" data-site="warrington-tech">
                                <div class="marker-pulse"></div>
                                <div class="marker-info">
                                    <h4>Warrington Tech Hub</h4>
                                    <p>£1.8M Office Development</p>
                                    <span class="roi">ROI: 15.7%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        this.initFallbackMapEvents();
    }

    initFallbackMapEvents() {
        const markers = document.querySelectorAll('.site-marker');
        
        markers.forEach(marker => {
            marker.addEventListener('click', (e) => {
                e.stopPropagation();
                this.showSiteDetails(marker.dataset.site);
            });

            marker.addEventListener('mouseenter', () => {
                marker.querySelector('.marker-info').style.opacity = '1';
                marker.querySelector('.marker-info').style.transform = 'translateY(0)';
            });

            marker.addEventListener('mouseleave', () => {
                marker.querySelector('.marker-info').style.opacity = '0';
                marker.querySelector('.marker-info').style.transform = 'translateY(10px)';
            });
        });
    }

    addMapControls() {
        // Add custom map controls
        const mapControls = document.createElement('div');
        mapControls.className = 'map-controls';
        mapControls.innerHTML = `
            <div class="control-group">
                <button class="control-btn" data-filter="all">All Sites</button>
                <button class="control-btn" data-filter="residential">Residential</button>
                <button class="control-btn" data-filter="commercial">Commercial</button>
                <button class="control-btn" data-filter="mixed">Mixed-Use</button>
            </div>
            <div class="control-group">
                <button class="control-btn" data-view="2d">2D View</button>
                <button class="control-btn active" data-view="3d">3D View</button>
            </div>
        `;

        // Add controls to map container
        const mapContainer = document.getElementById('landMap');
        if (mapContainer) {
            mapContainer.appendChild(mapControls);
        }

        // Add control event listeners
        this.initMapControlEvents();
    }

    initMapControlEvents() {
        const filterButtons = document.querySelectorAll('[data-filter]');
        const viewButtons = document.querySelectorAll('[data-view]');

        filterButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                filterButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.filterSites(btn.dataset.filter);
            });
        });

        viewButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                viewButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.changeView(btn.dataset.view);
            });
        });
    }

    loadDevelopmentSites() {
        // Development site data
        const sites = [
            {
                id: 'manchester-central',
                name: 'Manchester Central Development',
                type: 'mixed',
                price: 2500000,
                size: '2.5 acres',
                roi: 18.5,
                coordinates: [-2.2426, 53.4808],
                description: 'Prime mixed-use development opportunity in Manchester city center.',
                features: ['Planning permission granted', 'Transport links', 'High footfall area']
            },
            {
                id: 'liverpool-waterfront',
                name: 'Liverpool Waterfront Project',
                type: 'commercial',
                price: 4200000,
                size: '3.2 acres',
                roi: 22.3,
                coordinates: [-2.9916, 53.4084],
                description: 'Waterfront commercial development with stunning river views.',
                features: ['Waterfront location', 'Office space', 'Retail opportunities']
            },
            {
                id: 'warrington-tech',
                name: 'Warrington Tech Hub',
                type: 'commercial',
                price: 1800000,
                size: '1.8 acres',
                roi: 15.7,
                coordinates: [-2.5879, 53.3925],
                description: 'Modern tech hub development in growing business district.',
                features: ['Tech-focused design', 'Flexible workspace', 'Green building standards']
            }
        ];

        this.addSitesToMap(sites);
        this.updateSiteList(sites);
    }

    addSitesToMap(sites) {
        if (!this.map) return;

        sites.forEach(site => {
            // Create custom marker
            const markerElement = document.createElement('div');
            markerElement.className = 'custom-marker';
            markerElement.innerHTML = `
                <div class="marker-icon">
                    <div class="marker-pulse"></div>
                </div>
                <div class="marker-popup">
                    <h4>${site.name}</h4>
                    <p>£${this.formatPrice(site.price)}</p>
                    <span class="roi">ROI: ${site.roi}%</span>
                </div>
            `;

            // Create popup
            const popup = new mapboxgl.Popup({ offset: 25 }).setHTML(`
                <div class="map-popup">
                    <h3>${site.name}</h3>
                    <p class="price">£${this.formatPrice(site.price)}</p>
                    <p class="description">${site.description}</p>
                    <div class="features">
                        ${site.features.map(feature => `<span class="feature">${feature}</span>`).join('')}
                    </div>
                    <button class="btn-futuristic btn-primary" onclick="interactiveMap.showSiteDetails('${site.id}')">
                        View Details
                    </button>
                </div>
            `);

            // Add marker to map
            const marker = new mapboxgl.Marker(markerElement)
                .setLngLat(site.coordinates)
                .setPopup(popup)
                .addTo(this.map);

            this.markers.push(marker);
        });
    }

    updateSiteList(sites) {
        const siteList = document.querySelector('.development-sites-list');
        if (!siteList) return;

        siteList.innerHTML = sites.map(site => `
            <div class="site-card" data-site="${site.id}">
                <div class="site-header">
                    <h4>${site.name}</h4>
                    <span class="site-type ${site.type}">${site.type.toUpperCase()}</span>
                </div>
                <div class="site-details">
                    <div class="detail-item">
                        <span class="label">Price:</span>
                        <span class="value">£${this.formatPrice(site.price)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Size:</span>
                        <span class="value">${site.size}</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">ROI:</span>
                        <span class="value roi">${site.roi}%</span>
                    </div>
                </div>
                <button class="btn-futuristic btn-secondary" onclick="interactiveMap.showSiteDetails('${site.id}')">
                    View Details
                </button>
            </div>
        `).join('');
    }

    filterSites(filter) {
        const markers = document.querySelectorAll('.site-marker, .custom-marker');
        const siteCards = document.querySelectorAll('.site-card');

        markers.forEach(marker => {
            const siteType = marker.dataset.site || marker.closest('.site-card')?.dataset.site;
            if (filter === 'all' || siteType?.includes(filter)) {
                marker.style.display = 'block';
            } else {
                marker.style.display = 'none';
            }
        });

        siteCards.forEach(card => {
            const siteType = card.dataset.site;
            if (filter === 'all' || siteType?.includes(filter)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    changeView(view) {
        if (!this.map) return;

        if (view === '2d') {
            this.map.setPitch(0);
            this.map.setBearing(0);
        } else {
            this.map.setPitch(45);
            this.map.setBearing(0);
        }
    }

    showSiteDetails(siteId) {
        // Create modal with site details
        const modal = document.createElement('div');
        modal.className = 'site-modal';
        modal.innerHTML = `
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Development Site Details</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="site-gallery">
                        <img src="https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=800" alt="Development Site">
                    </div>
                    <div class="site-info">
                        <h4>${siteId.replace('-', ' ').toUpperCase()} DEVELOPMENT</h4>
                        <p>Premium development opportunity in the heart of Northwest England.</p>
                        <div class="investment-details">
                            <div class="detail-row">
                                <span>Investment Required:</span>
                                <span>£2,500,000</span>
                            </div>
                            <div class="detail-row">
                                <span>Expected ROI:</span>
                                <span>18.5%</span>
                            </div>
                            <div class="detail-row">
                                <span>Development Period:</span>
                                <span>18-24 months</span>
                            </div>
                        </div>
                        <button class="btn-futuristic btn-primary">Request Investment Pack</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Add event listeners
        modal.querySelector('.modal-close').addEventListener('click', () => {
            modal.remove();
        });

        modal.querySelector('.modal-overlay').addEventListener('click', () => {
            modal.remove();
        });

        // Animate modal
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
    }

    formatPrice(price) {
        return new Intl.NumberFormat('en-GB', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(price);
    }

    initMapEvents() {
        // Map click events
        if (this.map) {
            this.map.on('click', (e) => {
                // Handle map clicks
                console.log('Map clicked at:', e.lngLat);
            });
        }
    }
}

// Initialize interactive map when DOM is loaded
let interactiveMap;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize map when the development section is in view
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !interactiveMap) {
                interactiveMap = new InteractiveMap();
            }
        });
    });

    const developmentSection = document.getElementById('land');
    if (developmentSection) {
        observer.observe(developmentSection);
    }
});

// Export for global access
window.interactiveMap = interactiveMap;