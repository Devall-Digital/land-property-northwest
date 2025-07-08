/* ===================================
   NORTHWEST PROPERTY & LAND
   Virtual Tours Module
   =================================== */

document.addEventListener('DOMContentLoaded', function() {
    initVirtualTours();
});

function initVirtualTours() {
    // Initialize tour viewer
    initTourViewer();
    
    // Initialize tour controls
    initTourControls();
    
    // Initialize tour list
    initTourList();
    
    // Initialize VR mode
    initVRMode();
}

// Tour Viewer Management
function initTourViewer() {
    const tourViewer = document.getElementById('tourViewer');
    if (!tourViewer) return;
    
    // Current tour data
    const tours = [
        {
            id: 'tour1',
            title: 'Manchester Penthouse',
            url: 'https://my.matterport.com/show/?m=SxQL3iGyvQk',
            thumbnail: 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c',
            description: '360° Tour Available',
            features: ['4 Bedrooms', '3 Bathrooms', 'City Views', 'Rooftop Terrace']
        },
        {
            id: 'tour2',
            title: 'Liverpool Waterfront',
            url: 'https://my.matterport.com/show/?m=SxQL3iGyvQk',
            thumbnail: 'https://images.unsplash.com/photo-1600566753376-12c8ab7fb75b',
            description: 'VR Experience Ready',
            features: ['3 Bedrooms', '2 Bathrooms', 'Water Views', 'Modern Kitchen']
        },
        {
            id: 'tour3',
            title: 'Cheshire Estate',
            url: 'https://my.matterport.com/show/?m=SxQL3iGyvQk',
            thumbnail: 'https://images.unsplash.com/photo-1600573472550-8090b5e0745e',
            description: 'Drone Tour Available',
            features: ['5 Bedrooms', '4 Bathrooms', '2 Acres', 'Swimming Pool']
        }
    ];
    
    let currentTourIndex = 0;
    
    // Load tour
    window.loadTour = function(index) {
        if (index >= 0 && index < tours.length) {
            currentTourIndex = index;
            const tour = tours[index];
            const iframe = tourViewer.querySelector('iframe');
            
            if (iframe) {
                // Add loading animation
                showTourLoading();
                
                // Update iframe source
                iframe.src = tour.url;
                
                // Update active state in tour list
                updateTourList(index);
                
                // Hide loading after iframe loads
                iframe.onload = function() {
                    hideTourLoading();
                    updateTourInfo(tour);
                };
            }
        }
    };
    
    function showTourLoading() {
        const loader = document.createElement('div');
        loader.className = 'tour-loader';
        loader.innerHTML = `
            <div class="loader-content">
                <div class="loader-spinner"></div>
                <p>Loading Virtual Tour...</p>
            </div>
        `;
        tourViewer.appendChild(loader);
    }
    
    function hideTourLoading() {
        const loader = tourViewer.querySelector('.tour-loader');
        if (loader) {
            loader.classList.add('fade-out');
            setTimeout(() => loader.remove(), 300);
        }
    }
    
    function updateTourInfo(tour) {
        // Create or update tour info overlay
        let infoOverlay = document.querySelector('.tour-info-overlay');
        if (!infoOverlay) {
            infoOverlay = document.createElement('div');
            infoOverlay.className = 'tour-info-overlay';
            tourViewer.appendChild(infoOverlay);
        }
        
        infoOverlay.innerHTML = `
            <div class="tour-info-content">
                <h3>${tour.title}</h3>
                <div class="tour-features">
                    ${tour.features.map(f => `<span class="feature-tag">${f}</span>`).join('')}
                </div>
            </div>
        `;
        
        // Auto-hide after 3 seconds
        setTimeout(() => {
            infoOverlay.classList.add('hidden');
        }, 3000);
    }
}

// Tour Controls
function initTourControls() {
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    const vrBtn = document.getElementById('vrBtn');
    const floorplanBtn = document.getElementById('floorplanBtn');
    
    // Fullscreen functionality
    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', toggleFullscreen);
    }
    
    // VR mode
    if (vrBtn) {
        vrBtn.addEventListener('click', enterVRMode);
    }
    
    // Floorplan view
    if (floorplanBtn) {
        floorplanBtn.addEventListener('click', showFloorplan);
    }
    
    function toggleFullscreen() {
        const tourViewer = document.getElementById('tourViewer');
        
        if (!document.fullscreenElement) {
            tourViewer.requestFullscreen().then(() => {
                fullscreenBtn.textContent = '⛶ Exit Fullscreen';
            }).catch(err => {
                console.error('Error attempting to enable fullscreen:', err);
            });
        } else {
            document.exitFullscreen().then(() => {
                fullscreenBtn.textContent = '⛶ Fullscreen';
            });
        }
    }
    
    // Keyboard controls
    document.addEventListener('keydown', function(e) {
        if (document.querySelector('.virtual-tours').contains(document.activeElement)) {
            switch(e.key) {
                case 'f':
                case 'F':
                    toggleFullscreen();
                    break;
                case 'v':
                case 'V':
                    enterVRMode();
                    break;
                case 'ArrowLeft':
                    navigateTour('prev');
                    break;
                case 'ArrowRight':
                    navigateTour('next');
                    break;
            }
        }
    });
}

// Tour List Management
function initTourList() {
    const tourItems = document.querySelectorAll('.tour-item');
    
    tourItems.forEach((item, index) => {
        item.addEventListener('click', function() {
            // Remove active class from all items
            tourItems.forEach(i => i.classList.remove('active'));
            
            // Add active class to clicked item
            this.classList.add('active');
            
            // Load the selected tour
            loadTour(index);
            
            // Smooth scroll to viewer on mobile
            if (window.innerWidth < 768) {
                document.getElementById('tourViewer').scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }
        });
    });
}

function updateTourList(activeIndex) {
    const tourItems = document.querySelectorAll('.tour-item');
    tourItems.forEach((item, index) => {
        item.classList.toggle('active', index === activeIndex);
    });
}

// VR Mode
function initVRMode() {
    // Check for WebXR support
    if ('xr' in navigator) {
        checkVRSupport();
    }
}

async function checkVRSupport() {
    try {
        const supported = await navigator.xr.isSessionSupported('immersive-vr');
        if (supported) {
            const vrBtn = document.getElementById('vrBtn');
            if (vrBtn) {
                vrBtn.style.display = 'inline-flex';
            }
        }
    } catch (err) {
        console.log('VR not supported:', err);
    }
}

function enterVRMode() {
    // In a real implementation, this would start WebXR session
    showNotification('VR mode requires a compatible headset');
    
    // For demo, show VR instructions
    const modal = document.createElement('div');
    modal.className = 'vr-modal';
    modal.innerHTML = `
        <div class="vr-content">
            <h2>VR Mode</h2>
            <div class="vr-instructions">
                <p>To experience this property in VR:</p>
                <ol>
                    <li>Connect your VR headset</li>
                    <li>Open this page in your VR browser</li>
                    <li>Click the VR button in the tour viewer</li>
                </ol>
                <p>Supported devices: Oculus Quest, HTC Vive, Windows Mixed Reality</p>
            </div>
            <button class="btn-futuristic btn-primary" onclick="this.closest('.vr-modal').remove()">
                <span>CLOSE</span>
            </button>
        </div>
    `;
    document.body.appendChild(modal);
}

// Floorplan View
function showFloorplan() {
    const modal = document.createElement('div');
    modal.className = 'floorplan-modal';
    modal.innerHTML = `
        <div class="floorplan-content">
            <div class="floorplan-header">
                <h3>Interactive Floorplan</h3>
                <button class="close-btn" onclick="this.closest('.floorplan-modal').remove()">×</button>
            </div>
            <div class="floorplan-viewer">
                <img src="https://images.unsplash.com/photo-1540932239986-30128078f3c5" alt="Floorplan">
                <div class="floorplan-hotspots">
                    <div class="hotspot" style="top: 20%; left: 30%;" data-room="Living Room">
                        <span class="hotspot-marker">1</span>
                        <span class="hotspot-label">Living Room</span>
                    </div>
                    <div class="hotspot" style="top: 40%; left: 50%;" data-room="Kitchen">
                        <span class="hotspot-marker">2</span>
                        <span class="hotspot-label">Kitchen</span>
                    </div>
                    <div class="hotspot" style="top: 60%; left: 70%;" data-room="Master Bedroom">
                        <span class="hotspot-marker">3</span>
                        <span class="hotspot-label">Master Bedroom</span>
                    </div>
                </div>
            </div>
            <div class="floorplan-legend">
                <h4>Room Dimensions</h4>
                <ul>
                    <li>Living Room: 25' × 18'</li>
                    <li>Kitchen: 15' × 12'</li>
                    <li>Master Bedroom: 16' × 14'</li>
                    <li>Total Area: 2,500 sq ft</li>
                </ul>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Add hotspot interactions
    modal.querySelectorAll('.hotspot').forEach(hotspot => {
        hotspot.addEventListener('click', function() {
            const room = this.dataset.room;
            showNotification(`Navigate to ${room} in the virtual tour`);
            modal.remove();
        });
    });
}

// Navigation
function navigateTour(direction) {
    const tourItems = document.querySelectorAll('.tour-item');
    const currentIndex = Array.from(tourItems).findIndex(item => item.classList.contains('active'));
    
    let newIndex;
    if (direction === 'next') {
        newIndex = (currentIndex + 1) % tourItems.length;
    } else {
        newIndex = (currentIndex - 1 + tourItems.length) % tourItems.length;
    }
    
    loadTour(newIndex);
}

// Tour Analytics
function trackTourInteraction(action, tourId) {
    // Track user interactions for analytics
    if (typeof gtag !== 'undefined') {
        gtag('event', 'virtual_tour_interaction', {
            'event_category': 'Virtual Tours',
            'event_label': tourId,
            'event_action': action
        });
    }
}

// Add styles
const style = document.createElement('style');
style.textContent = `
    .tour-loader {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
    }
    
    .tour-loader.fade-out {
        opacity: 0;
        transition: opacity 0.3s;
    }
    
    .loader-content {
        text-align: center;
    }
    
    .loader-spinner {
        width: 50px;
        height: 50px;
        border: 3px solid rgba(255, 255, 255, 0.1);
        border-top-color: var(--accent-gold);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 1rem;
    }
    
    .tour-info-overlay {
        position: absolute;
        top: 20px;
        left: 20px;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(10px);
        padding: 1rem 1.5rem;
        border-radius: 4px;
        transition: opacity 0.3s;
    }
    
    .tour-info-overlay.hidden {
        opacity: 0;
        pointer-events: none;
    }
    
    .tour-info-content h3 {
        font-size: 1.25rem;
        margin-bottom: 0.5rem;
    }
    
    .tour-features {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .feature-tag {
        background: rgba(255, 255, 255, 0.1);
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
    }
    
    .vr-modal,
    .floorplan-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.95);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2000;
        padding: 2rem;
    }
    
    .vr-content,
    .floorplan-content {
        background: var(--gray-900);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 2rem;
        max-width: 600px;
        width: 100%;
    }
    
    .vr-instructions {
        margin: 2rem 0;
    }
    
    .vr-instructions ol {
        margin-left: 1.5rem;
        margin-top: 1rem;
    }
    
    .vr-instructions li {
        margin-bottom: 0.5rem;
    }
    
    .floorplan-content {
        max-width: 800px;
    }
    
    .floorplan-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .close-btn {
        background: none;
        border: none;
        color: var(--primary-white);
        font-size: 1.5rem;
        cursor: pointer;
    }
    
    .floorplan-viewer {
        position: relative;
        margin-bottom: 2rem;
    }
    
    .floorplan-viewer img {
        width: 100%;
        height: auto;
        border-radius: 4px;
    }
    
    .floorplan-hotspots {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }
    
    .hotspot {
        position: absolute;
        cursor: pointer;
        transition: transform 0.2s;
    }
    
    .hotspot:hover {
        transform: scale(1.1);
    }
    
    .hotspot-marker {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        background: var(--accent-gold);
        color: var(--primary-black);
        border-radius: 50%;
        font-weight: 700;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }
    
    .hotspot-label {
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0, 0, 0, 0.9);
        color: var(--primary-white);
        padding: 0.25rem 0.75rem;
        border-radius: 4px;
        font-size: 0.75rem;
        white-space: nowrap;
        opacity: 0;
        transition: opacity 0.2s;
        pointer-events: none;
        margin-top: 0.5rem;
    }
    
    .hotspot:hover .hotspot-label {
        opacity: 1;
    }
    
    .floorplan-legend {
        background: rgba(255, 255, 255, 0.05);
        padding: 1.5rem;
        border-radius: 4px;
    }
    
    .floorplan-legend h4 {
        margin-bottom: 1rem;
    }
    
    .floorplan-legend ul {
        list-style: none;
    }
    
    .floorplan-legend li {
        padding: 0.5rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .floorplan-legend li:last-child {
        border-bottom: none;
        font-weight: 600;
        color: var(--accent-gold);
    }
`;
document.head.appendChild(style);

// Initialize first tour
setTimeout(() => {
    loadTour(0);
}, 500);

// Export module
window.VirtualTours = {
    init: initVirtualTours,
    loadTour: loadTour,
    navigateTour: navigateTour
};