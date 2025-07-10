/* ===================================
   NORTHWEST PROPERTY & LAND SALES
   Thank You Page JavaScript
   =================================== */

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Thank you page loaded - initializing tracking and effects');
    
    // Initialize all thank you page functionality
    initAnalyticsTracking();
    initConfettiEffect();
    initAutoRedirect();
});

/* ===================================
   ANALYTICS TRACKING
   =================================== */
function initAnalyticsTracking() {
    // Track successful form submissions for analytics
    if (typeof gtag !== 'undefined') {
        gtag('event', 'conversion', {
            'send_to': 'AW-XXXXXXXXX/XXXXXXXXX', // Replace with your conversion ID
            'value': 1.0,
            'currency': 'GBP'
        });
        
        gtag('event', 'generate_lead', {
            'event_category': 'form',
            'event_label': 'quote_request',
            'value': 1
        });
    }
}

/* ===================================
   CONFETTI EFFECT
   =================================== */
function initConfettiEffect() {
    function createConfetti() {
        const colors = ['#f59e0b', '#2563eb', '#10b981', '#ffffff'];
        
        for (let i = 0; i < 50; i++) {
            const confetti = document.createElement('div');
            confetti.style.position = 'fixed';
            confetti.style.width = '10px';
            confetti.style.height = '10px';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.top = '-10px';
            confetti.style.borderRadius = '50%';
            confetti.style.pointerEvents = 'none';
            confetti.style.zIndex = '9999';
            
            document.body.appendChild(confetti);
            
            const fall = confetti.animate([
                { transform: 'translateY(-10px) rotate(0deg)', opacity: 1 },
                { transform: `translateY(${window.innerHeight + 10}px) rotate(360deg)`, opacity: 0 }
            ], {
                duration: Math.random() * 3000 + 2000,
                easing: 'linear'
            });
            
            fall.onfinish = () => confetti.remove();
        }
    }

    // Create confetti on page load
    window.addEventListener('load', () => {
        setTimeout(createConfetti, 500);
    });
}

/* ===================================
   AUTO REDIRECT
   =================================== */
function initAutoRedirect() {
    // Optional: Auto-redirect to homepage after 5 minutes
    setTimeout(() => {
        const redirect = confirm('Would you like to return to our homepage to explore more services?');
        if (redirect) {
            window.location.href = '/';
        }
    }, 300000); // 5 minutes
}