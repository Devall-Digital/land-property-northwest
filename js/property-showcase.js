/* ===================================
   NORTHWEST PROPERTY & LAND
   Property Showcase Module
   =================================== */

document.addEventListener('DOMContentLoaded', function() {
    initPropertyShowcase();
});

function initPropertyShowcase() {
    // Initialize property comparison
    initPropertyComparison();
    
    // Initialize property filtering
    initPropertyFiltering();
    
    // Initialize property carousel
    initPropertyCarousel();
    
    // Initialize 3D property viewer
    init3DViewer();
    
    // Initialize property search
    initAdvancedSearch();
}

// Property Comparison System
function initPropertyComparison() {
    const compareList = [];
    const maxCompare = 4;
    
    // Create comparison modal
    createComparisonModal();
    
    // Handle compare button clicks
    document.addEventListener('click', function(e) {
        if (e.target.closest('.compare-btn')) {
            const btn = e.target.closest('.compare-btn');
            const propertyCard = btn.closest('.property-card');
            const propertyId = propertyCard.dataset.propertyId || Math.random().toString(36).substr(2, 9);
            
            if (btn.classList.contains('comparing')) {
                removeFromComparison(propertyId);
            } else {
                if (compareList.length < maxCompare) {
                    addToComparison(propertyId, propertyCard);
                } else {
                    showNotification(`Maximum ${maxCompare} properties can be compared at once`);
                }
            }
        }
    });
    
    function addToComparison(id, card) {
        const propertyData = {
            id: id,
            title: card.querySelector('.property-title').textContent,
            price: card.querySelector('.property-price').textContent,
            location: card.querySelector('.property-location').textContent,
            features: Array.from(card.querySelectorAll('.feature-text')).map(f => f.textContent),
            image: card.querySelector('.property-image img').src
        };
        
        compareList.push(propertyData);
        updateComparisonUI();
    }
    
    function removeFromComparison(id) {
        const index = compareList.findIndex(p => p.id === id);
        if (index > -1) {
            compareList.splice(index, 1);
            updateComparisonUI();
        }
    }
    
    function updateComparisonUI() {
        // Update compare bar
        const compareBar = document.querySelector('.compare-bar');
        if (compareBar) {
            const viewBtn = compareBar.querySelector('button');
            viewBtn.addEventListener('click', showComparisonModal);
        }
    }
    
    function createComparisonModal() {
        const modal = document.createElement('div');
        modal.className = 'comparison-modal';
        modal.innerHTML = `
            <div class="comparison-content">
                <div class="comparison-header">
                    <h2>PROPERTY COMPARISON</h2>
                    <button class="close-modal">×</button>
                </div>
                <div class="comparison-grid" id="comparisonGrid">
                    <!-- Properties will be inserted here -->
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Close modal
        modal.querySelector('.close-modal').addEventListener('click', () => {
            modal.classList.remove('active');
        });
    }
    
    function showComparisonModal() {
        const modal = document.querySelector('.comparison-modal');
        const grid = document.getElementById('comparisonGrid');
        
        // Build comparison table
        grid.innerHTML = `
            <div class="comparison-table">
                ${compareList.map(property => `
                    <div class="comparison-property">
                        <img src="${property.image}" alt="${property.title}">
                        <h3>${property.title}</h3>
                        <div class="comparison-price">${property.price}</div>
                        <div class="comparison-location">${property.location}</div>
                        <div class="comparison-features">
                            ${property.features.map(f => `<span>${f}</span>`).join('')}
                        </div>
                        <button class="btn-futuristic btn-primary">VIEW DETAILS</button>
                    </div>
                `).join('')}
            </div>
        `;
        
        modal.classList.add('active');
    }
}

// Advanced Property Filtering
function initPropertyFiltering() {
    const properties = document.querySelectorAll('.property-card');
    const filters = {
        type: 'all',
        minPrice: 0,
        maxPrice: 5000000,
        location: '',
        features: []
    };
    
    // Type filters
    document.querySelectorAll('.filter-chip').forEach(chip => {
        chip.addEventListener('click', function() {
            filters.type = this.dataset.filter;
            applyFilters();
        });
    });
    
    // Price range
    const priceInputs = document.querySelectorAll('.range-input');
    if (priceInputs.length === 2) {
        priceInputs[0].addEventListener('input', function() {
            filters.minPrice = parseInt(this.value);
            applyFilters();
        });
        
        priceInputs[1].addEventListener('input', function() {
            filters.maxPrice = parseInt(this.value);
            applyFilters();
        });
    }
    
    // Location filter
    const locationSelect = document.querySelector('.filter-select[name="location"]');
    if (locationSelect) {
        locationSelect.addEventListener('change', function() {
            filters.location = this.value;
            applyFilters();
        });
    }
    
    function applyFilters() {
        properties.forEach(property => {
            const price = parseInt(property.querySelector('.property-price').textContent.replace(/[^0-9]/g, ''));
            const location = property.querySelector('.property-location').textContent.toLowerCase();
            const type = property.dataset.type || 'all';
            
            let show = true;
            
            // Type filter
            if (filters.type !== 'all' && type !== filters.type) {
                show = false;
            }
            
            // Price filter
            if (price < filters.minPrice || price > filters.maxPrice) {
                show = false;
            }
            
            // Location filter
            if (filters.location && !location.includes(filters.location.toLowerCase())) {
                show = false;
            }
            
            // Show/hide property
            if (show) {
                property.style.display = '';
                property.classList.add('fade-in-scale');
            } else {
                property.style.display = 'none';
            }
        });
        
        // Update results count
        const visibleCount = Array.from(properties).filter(p => p.style.display !== 'none').length;
        updateResultsCount(visibleCount);
    }
    
    function updateResultsCount(count) {
        let countDisplay = document.querySelector('.results-count');
        if (!countDisplay) {
            countDisplay = document.createElement('div');
            countDisplay.className = 'results-count';
            document.querySelector('.properties-showcase').parentElement.insertBefore(
                countDisplay, 
                document.querySelector('.properties-showcase')
            );
        }
        
        countDisplay.textContent = `Showing ${count} properties`;
        countDisplay.style.cssText = `
            text-align: center;
            margin-bottom: 2rem;
            color: var(--gray-300);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        `;
    }
}

// Property Carousel
function initPropertyCarousel() {
    const showcase = document.querySelector('.properties-showcase');
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');
    const dots = document.querySelectorAll('.dot');
    
    if (!showcase || !prevBtn || !nextBtn) return;
    
    let currentIndex = 0;
    const properties = showcase.querySelectorAll('.property-card');
    const totalProperties = properties.length;
    
    // Navigation
    prevBtn.addEventListener('click', () => {
        currentIndex = (currentIndex - 1 + totalProperties) % totalProperties;
        updateCarousel();
    });
    
    nextBtn.addEventListener('click', () => {
        currentIndex = (currentIndex + 1) % totalProperties;
        updateCarousel();
    });
    
    // Dots navigation
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            currentIndex = index;
            updateCarousel();
        });
    });
    
    // Touch/swipe support
    let touchStartX = 0;
    let touchEndX = 0;
    
    showcase.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    });
    
    showcase.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    });
    
    function handleSwipe() {
        if (touchEndX < touchStartX - 50) {
            // Swipe left
            currentIndex = (currentIndex + 1) % totalProperties;
            updateCarousel();
        }
        
        if (touchEndX > touchStartX + 50) {
            // Swipe right
            currentIndex = (currentIndex - 1 + totalProperties) % totalProperties;
            updateCarousel();
        }
    }
    
    function updateCarousel() {
        // Update dots
        dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === currentIndex);
        });
        
        // Smooth scroll to property
        const property = properties[currentIndex];
        if (property) {
            property.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }
}

// 3D Property Viewer
function init3DViewer() {
    document.addEventListener('click', function(e) {
        if (e.target.closest('.view-3d-btn')) {
            e.preventDefault();
            const propertyCard = e.target.closest('.property-card');
            open3DViewer(propertyCard);
        }
    });
    
    function open3DViewer(propertyCard) {
        const modal = document.createElement('div');
        modal.className = 'viewer-3d-modal';
        modal.innerHTML = `
            <div class="viewer-3d-content">
                <div class="viewer-header">
                    <h3>${propertyCard.querySelector('.property-title').textContent} - 3D View</h3>
                    <button class="close-viewer">×</button>
                </div>
                <div class="viewer-container">
                    <div class="viewer-placeholder">
                        <div class="loader-3d">
                            <div class="cube">
                                <div class="face front"></div>
                                <div class="face back"></div>
                                <div class="face right"></div>
                                <div class="face left"></div>
                                <div class="face top"></div>
                                <div class="face bottom"></div>
                            </div>
                        </div>
                        <p>Loading 3D Model...</p>
                    </div>
                </div>
                <div class="viewer-controls">
                    <button class="control-btn">🔄 Rotate</button>
                    <button class="control-btn">🔍 Zoom</button>
                    <button class="control-btn">📐 Measure</button>
                    <button class="control-btn">💡 Lighting</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        setTimeout(() => modal.classList.add('active'), 10);
        
        // Close functionality
        modal.querySelector('.close-viewer').addEventListener('click', () => {
            modal.classList.remove('active');
            setTimeout(() => modal.remove(), 300);
        });
        
        // Simulate loading
        setTimeout(() => {
            const placeholder = modal.querySelector('.viewer-placeholder');
            placeholder.innerHTML = `
                <div class="mock-3d-view">
                    <img src="${propertyCard.querySelector('.property-image img').src}" alt="3D View">
                    <div class="overlay-text">3D View Coming Soon</div>
                </div>
            `;
        }, 2000);
    }
}

// Advanced Search with AI
function initAdvancedSearch() {
    const searchInput = document.querySelector('.search-input');
    const searchBtn = document.querySelector('.search-btn');
    
    if (!searchInput || !searchBtn) return;
    
    // AI-powered search suggestions
    const suggestions = [
        'Modern apartment with city views',
        'Family home with garden near schools',
        'Investment property with high ROI',
        'Luxury penthouse in Manchester',
        'Development land with planning permission',
        'Waterfront property in Liverpool',
        'Rural retreat in Lake District',
        'Commercial property for rental income'
    ];
    
    // Create suggestions dropdown
    const suggestionsContainer = document.createElement('div');
    suggestionsContainer.className = 'search-suggestions';
    searchInput.parentElement.appendChild(suggestionsContainer);
    
    // Search input events
    searchInput.addEventListener('focus', () => {
        if (searchInput.value.length === 0) {
            showSuggestions(suggestions.slice(0, 5));
        }
    });
    
    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        if (query.length > 2) {
            const filtered = suggestions.filter(s => s.toLowerCase().includes(query));
            showSuggestions(filtered.slice(0, 5));
        } else {
            hideSuggestions();
        }
    });
    
    searchInput.addEventListener('blur', () => {
        setTimeout(hideSuggestions, 200);
    });
    
    // Search button
    searchBtn.addEventListener('click', performSearch);
    searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            performSearch();
        }
    });
    
    function showSuggestions(items) {
        suggestionsContainer.innerHTML = items.map(item => `
            <div class="suggestion-item">${item}</div>
        `).join('');
        
        suggestionsContainer.style.display = 'block';
        
        // Click on suggestion
        suggestionsContainer.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', () => {
                searchInput.value = item.textContent;
                hideSuggestions();
                performSearch();
            });
        });
    }
    
    function hideSuggestions() {
        suggestionsContainer.style.display = 'none';
    }
    
    function performSearch() {
        const query = searchInput.value;
        if (query.trim()) {
            // Animate search
            searchBtn.innerHTML = '<span class="loader"></span>';
            
            setTimeout(() => {
                searchBtn.innerHTML = '<span class="search-icon"></span>';
                showNotification(`Searching for: "${query}"`);
                
                // In a real app, this would filter properties or make an API call
                // For now, we'll just scroll to properties section
                document.getElementById('properties').scrollIntoView({ behavior: 'smooth' });
            }, 1000);
        }
    }
}

// Add required styles
const style = document.createElement('style');
style.textContent = `
    .comparison-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.95);
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }
    
    .comparison-modal.active {
        display: flex;
    }
    
    .comparison-content {
        background: var(--gray-900);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        width: 100%;
        max-width: 1200px;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .comparison-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 2rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .comparison-header h2 {
        font-size: 1.5rem;
        text-transform: uppercase;
        letter-spacing: 2px;
    }
    
    .close-modal {
        background: none;
        border: none;
        color: var(--primary-white);
        font-size: 2rem;
        cursor: pointer;
        transition: transform 0.2s;
    }
    
    .close-modal:hover {
        transform: rotate(90deg);
    }
    
    .comparison-table {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
        padding: 2rem;
    }
    
    .comparison-property {
        text-align: center;
    }
    
    .comparison-property img {
        width: 100%;
        height: 200px;
        object-fit: cover;
        border-radius: 4px;
        margin-bottom: 1rem;
    }
    
    .comparison-property h3 {
        font-size: 1rem;
        margin-bottom: 0.5rem;
    }
    
    .comparison-price {
        font-size: 1.5rem;
        color: var(--accent-gold);
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    
    .comparison-location {
        color: var(--gray-400);
        margin-bottom: 1rem;
    }
    
    .comparison-features {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: center;
        margin-bottom: 1.5rem;
    }
    
    .comparison-features span {
        background: rgba(255, 255, 255, 0.1);
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
    }
    
    .viewer-3d-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.95);
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s;
    }
    
    .viewer-3d-modal.active {
        opacity: 1;
    }
    
    .viewer-3d-content {
        background: var(--gray-900);
        border: 1px solid rgba(255, 255, 255, 0.1);
        width: 90%;
        max-width: 1000px;
        height: 80vh;
        display: flex;
        flex-direction: column;
    }
    
    .viewer-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 2rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .close-viewer {
        background: none;
        border: none;
        color: var(--primary-white);
        font-size: 1.5rem;
        cursor: pointer;
    }
    
    .viewer-container {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }
    
    .viewer-placeholder {
        text-align: center;
    }
    
    .loader-3d {
        width: 100px;
        height: 100px;
        margin: 0 auto 2rem;
        perspective: 1000px;
    }
    
    .cube {
        width: 100%;
        height: 100%;
        position: relative;
        transform-style: preserve-3d;
        animation: rotate-cube 2s infinite linear;
    }
    
    .face {
        position: absolute;
        width: 100px;
        height: 100px;
        background: var(--accent-gold);
        opacity: 0.8;
        border: 1px solid var(--primary-white);
    }
    
    .front  { transform: translateZ(50px); }
    .back   { transform: rotateY(180deg) translateZ(50px); }
    .right  { transform: rotateY(90deg) translateZ(50px); }
    .left   { transform: rotateY(-90deg) translateZ(50px); }
    .top    { transform: rotateX(90deg) translateZ(50px); }
    .bottom { transform: rotateX(-90deg) translateZ(50px); }
    
    @keyframes rotate-cube {
        from { transform: rotateX(0deg) rotateY(0deg); }
        to { transform: rotateX(360deg) rotateY(360deg); }
    }
    
    .viewer-controls {
        display: flex;
        justify-content: center;
        gap: 1rem;
        padding: 1rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .mock-3d-view {
        position: relative;
        display: inline-block;
    }
    
    .mock-3d-view img {
        max-width: 100%;
        max-height: 60vh;
        border-radius: 8px;
    }
    
    .overlay-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0, 0, 0, 0.8);
        color: var(--accent-gold);
        padding: 1rem 2rem;
        border-radius: 4px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .search-suggestions {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--gray-900);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-top: none;
        display: none;
        z-index: 100;
    }
    
    .suggestion-item {
        padding: 1rem;
        cursor: pointer;
        transition: background 0.2s;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    
    .suggestion-item:hover {
        background: rgba(255, 255, 255, 0.05);
    }
`;
document.head.appendChild(style);

// Export module
window.PropertyShowcase = {
    init: initPropertyShowcase
};