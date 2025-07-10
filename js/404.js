/* ===================================
   NORTHWEST PROPERTY & LAND SALES
   404 Error Page JavaScript
   =================================== */

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('404 error page loaded - initializing tracking');
    
    // Initialize all 404 page functionality
    initErrorTracking();
    // initAutoRedirect(); // Uncomment if you want auto-redirect functionality
});

/* ===================================
   ERROR TRACKING
   =================================== */
function initErrorTracking() {
    // Track 404 errors for analytics
    if (typeof gtag !== 'undefined') {
        gtag('event', 'page_view', {
            'page_title': '404 Error',
            'page_location': window.location.href,
            'custom_map': {'custom_parameter_1': 'error_page'}
        });
    }
}

/* ===================================
   AUTO REDIRECT (OPTIONAL)
   =================================== */
function initAutoRedirect() {
    // Auto-redirect to home page after 30 seconds (optional)
    setTimeout(() => {
        if (confirm('Would you like to go to our homepage?')) {
            window.location.href = '/';
        }
    }, 30000);
}