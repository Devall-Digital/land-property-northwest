/**
 * Northwest Property & Land Sales - JavaScript Configuration
 * Centralized configuration for all JavaScript functionality
 * 
 * @version 1.0.0
 * @author JavaScript Agent
 * @date 2024
 */

// Main configuration object
const NW_CONFIG = {
    // Application settings
    APP_NAME: 'Northwest Property & Land Sales',
    VERSION: '2.0.0',
    DEBUG: false, // Set to true for development debugging
    
    // Analytics configuration
    ANALYTICS: {
        GA_TRACKING_ID: 'GA_MEASUREMENT_ID', // Replace with actual GA4 ID
        LAZY_LOAD: true, // Load GA on first user interaction
        TRACK_PHONE_CALLS: true,
        TRACK_FORM_SUBMISSIONS: true,
        TRACK_PAGE_VIEWS: true
    },
    
    // Performance settings
    PERFORMANCE: {
        SCROLL_THROTTLE_MS: 16, // ~60fps
        ANIMATION_OFFSET: 80,
        LAZY_LOAD_IMAGES: true,
        ENABLE_SERVICE_WORKER: true,
        MONITOR_PERFORMANCE: true
    },
    
    // Contact and business settings
    CONTACT: {
        EMERGENCY_PHONE: '07561724095',
        BUSINESS_HOURS: {
            start: 8,
            end: 18,
            closedDays: [0] // Sunday
        },
        RESPONSE_TIME: '2 hours',
        FORM_ENDPOINT: 'process-form.php'
    },
    
    // Animation settings
    ANIMATION: {
        ENABLE_SCROLL_ANIMATIONS: true,
        ENABLE_HOVER_EFFECTS: true,
        ANIMATION_DURATION: 600, // milliseconds
        ANIMATION_DELAY: 100, // milliseconds between elements
        FADE_IN_DISTANCE: 30 // pixels
    },
    
    // Mobile settings
    MOBILE: {
        BREAKPOINT: 768, // pixels
        ENABLE_TOUCH_OPTIMIZATIONS: true,
        HIDE_NAV_ON_SCROLL: true
    },
    
    // CSS selectors (centralized for easy maintenance)
    SELECTORS: {
        // Navigation
        header: '.header, .navbar',
        mobileMenu: '.mobile-menu-toggle, .hamburger',
        navMenu: '.nav-menu',
        navLinks: '.nav-link',
        
        // Forms
        contactForm: '#contactForm',
        submitBtn: '#submitBtn',
        formFields: 'input, select, textarea',
        
        // Cards and content
        serviceCards: '.service-card',
        areaCards: '.area-card',
        propertyCards: '.property-card',
        landCards: '.land-card',
        animatedElements: '.section-header, .property-card, .land-card, .about-feature, .contact-item, .service-card, .area-card, .stat',
        
        // Links
        anchorLinks: 'a[href^="#"]',
        phoneLinks: 'a[href^="tel:"]',
        
        // Utility
        scrollToTop: '.scroll-to-top'
    },
    
    // API endpoints
    API: {
        FORM_SUBMISSION: 'process-form.php',
        IMAGE_DOWNLOAD: 'download-images.php',
        WEBHOOK_DEPLOY: 'webhook-deploy.php'
    },
    
    // Error messages
    MESSAGES: {
        FORM_SUCCESS: 'Thank you! We will contact you within {responseTime} to discuss your free quote.\n\nNeed immediate assistance? Call {phone} now!',
        FORM_ERROR: 'Please correct the following:\n\n{errors}\n\nOr call us directly: {phone}',
        FORM_NETWORK_ERROR: 'There was a problem submitting your form. Please call us directly at {phone} or try again.',
        EMERGENCY_CONFIRM: 'We are currently outside business hours. This will be treated as an emergency call. Continue?',
        LOADING: 'Loading...',
        SENDING: 'Sending...',
        SUBMIT_BUTTON: 'Get My Free Quote'
    },
    
    // Feature flags
    FEATURES: {
        ENABLE_PWA: true,
        ENABLE_OFFLINE_SUPPORT: false,
        ENABLE_PUSH_NOTIFICATIONS: false,
        ENABLE_REAL_TIME_UPDATES: false,
        ENABLE_ADVANCED_FILTERING: false
    },
    
    // Development settings
    DEVELOPMENT: {
        ENABLE_CONSOLE_LOGGING: true,
        ENABLE_PERFORMANCE_LOGGING: true,
        ENABLE_ERROR_TRACKING: true,
        ENABLE_DEBUG_MODE: false
    }
};

// Utility functions for configuration
const ConfigUtils = {
    /**
     * Get configuration value with fallback
     * @param {string} path - Dot notation path to config value
     * @param {*} defaultValue - Default value if not found
     * @returns {*} Configuration value or default
     */
    get(path, defaultValue = null) {
        const keys = path.split('.');
        let value = NW_CONFIG;
        
        for (const key of keys) {
            if (value && typeof value === 'object' && key in value) {
                value = value[key];
            } else {
                return defaultValue;
            }
        }
        
        return value;
    },
    
    /**
     * Set configuration value
     * @param {string} path - Dot notation path to config value
     * @param {*} value - Value to set
     */
    set(path, value) {
        const keys = path.split('.');
        let current = NW_CONFIG;
        
        for (let i = 0; i < keys.length - 1; i++) {
            const key = keys[i];
            if (!(key in current) || typeof current[key] !== 'object') {
                current[key] = {};
            }
            current = current[key];
        }
        
        current[keys[keys.length - 1]] = value;
    },
    
    /**
     * Check if feature is enabled
     * @param {string} featureName - Name of the feature
     * @returns {boolean} True if feature is enabled
     */
    isFeatureEnabled(featureName) {
        return this.get(`FEATURES.${featureName}`, false);
    },
    
    /**
     * Get message with placeholders replaced
     * @param {string} messageKey - Key of the message
     * @param {Object} replacements - Object with placeholder replacements
     * @returns {string} Formatted message
     */
    getMessage(messageKey, replacements = {}) {
        let message = this.get(`MESSAGES.${messageKey}`, '');
        
        // Replace placeholders
        Object.entries(replacements).forEach(([key, value]) => {
            message = message.replace(new RegExp(`{${key}}`, 'g'), value);
        });
        
        return message;
    },
    
    /**
     * Get selector with fallback
     * @param {string} selectorKey - Key of the selector
     * @param {string} fallback - Fallback selector
     * @returns {string} CSS selector
     */
    getSelector(selectorKey, fallback = '') {
        return this.get(`SELECTORS.${selectorKey}`, fallback);
    },
    
    /**
     * Check if in development mode
     * @returns {boolean} True if in development mode
     */
    isDevelopment() {
        return this.get('DEVELOPMENT.ENABLE_DEBUG_MODE', false) || 
               window.location.hostname === 'localhost' ||
               window.location.hostname === '127.0.0.1';
    },
    
    /**
     * Log message if logging is enabled
     * @param {string} level - Log level (log, warn, error)
     * @param {...any} args - Arguments to log
     */
    log(level = 'log', ...args) {
        if (this.get('DEVELOPMENT.ENABLE_CONSOLE_LOGGING', true)) {
            console[level](`[${NW_CONFIG.APP_NAME}]`, ...args);
        }
    }
};

// Environment-specific overrides
if (ConfigUtils.isDevelopment()) {
    // Development overrides
    NW_CONFIG.DEBUG = true;
    NW_CONFIG.DEVELOPMENT.ENABLE_DEBUG_MODE = true;
    NW_CONFIG.DEVELOPMENT.ENABLE_CONSOLE_LOGGING = true;
    NW_CONFIG.ANALYTICS.LAZY_LOAD = false; // Load GA immediately in development
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        NW_CONFIG,
        ConfigUtils
    };
}