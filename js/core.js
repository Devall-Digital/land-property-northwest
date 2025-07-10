/**
 * Northwest Property & Land Sales - Core JavaScript
 * Consolidated and optimized version combining script.js and main.js functionality
 * 
 * @version 2.0.0
 * @author JavaScript Agent
 * @date 2024
 */

// Configuration object for easy customization
const CONFIG = {
    // Analytics
    GA_TRACKING_ID: 'GA_MEASUREMENT_ID', // Replace with actual GA4 ID
    LAZY_LOAD_GA: true,
    
    // Performance
    SCROLL_THROTTLE_MS: 16, // ~60fps
    ANIMATION_OFFSET: 80,
    
    // Contact
    EMERGENCY_PHONE: '07561724095',
    BUSINESS_HOURS: {
        start: 8,
        end: 18,
        closedDays: [0] // Sunday
    },
    
    // Selectors
    SELECTORS: {
        header: '.header, .navbar',
        mobileMenu: '.mobile-menu-toggle, .hamburger',
        navMenu: '.nav-menu',
        contactForm: '#contactForm',
        submitBtn: '#submitBtn',
        serviceCards: '.service-card',
        areaCards: '.area-card',
        propertyCards: '.property-card',
        landCards: '.land-card',
        animatedElements: '.section-header, .property-card, .land-card, .about-feature, .contact-item, .service-card, .area-card, .stat'
    }
};

// Performance utilities
const PerformanceUtils = {
    /**
     * Debounce function calls
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in milliseconds
     * @returns {Function} Debounced function
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Throttle function calls
     * @param {Function} func - Function to throttle
     * @param {number} limit - Throttle limit in milliseconds
     * @returns {Function} Throttled function
     */
    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    /**
     * Check if element is in viewport
     * @param {Element} element - Element to check
     * @returns {boolean} True if element is in viewport
     */
    isInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }
};

// Analytics utilities
const AnalyticsUtils = {
    /**
     * Initialize Google Analytics
     */
    initGoogleAnalytics() {
        if (typeof gtag !== 'undefined') {
            console.log('Google Analytics already loaded');
            return;
        }

        const script = document.createElement('script');
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtag/js?id=${CONFIG.GA_TRACKING_ID}`;
        document.head.appendChild(script);

        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', CONFIG.GA_TRACKING_ID);
        
        console.log('Google Analytics loaded');
    },

    /**
     * Track conversion events
     * @param {string} type - Conversion type
     * @param {Object} additionalData - Additional tracking data
     */
    trackConversion(type, additionalData = {}) {
        if (typeof gtag !== 'undefined') {
            gtag('event', 'conversion', {
                'event_category': 'lead_generation',
                'event_label': type,
                ...additionalData
            });
        }
        console.log('Conversion tracked:', type, additionalData);
    },

    /**
     * Track phone call events
     * @param {string} phoneNumber - Phone number clicked
     * @param {string} location - Location of phone link
     */
    trackPhoneCall(phoneNumber, location = 'unknown') {
        if (typeof gtag !== 'undefined') {
            gtag('event', 'phone_call', {
                'event_category': 'contact',
                'event_label': location,
                'value': phoneNumber
            });
        }
        console.log('Phone call tracked:', phoneNumber, location);
    }
};

// Form utilities
const FormUtils = {
    /**
     * Validate email format
     * @param {string} email - Email to validate
     * @returns {boolean} True if valid email
     */
    validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },

    /**
     * Format UK phone numbers
     * @param {string} phoneNumber - Phone number to format
     * @returns {string} Formatted phone number
     */
    formatPhoneNumber(phoneNumber) {
        const cleaned = phoneNumber.replace(/\D/g, '');
        if (cleaned.length === 11 && cleaned.startsWith('07')) {
            return cleaned.replace(/(\d{5})(\d{6})/, '$1 $2');
        }
        return phoneNumber;
    },

    /**
     * Handle form field focus effects
     * @param {Element} field - Form field element
     */
    handleFieldFocus(field) {
        field.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        field.addEventListener('blur', function() {
            if (this.value === '') {
                this.parentElement.classList.remove('focused');
            }
        });
    },

    /**
     * Initialize form validation and submission
     */
    initFormHandling() {
        // Skip if futuristic-main.js is loaded (it handles forms with better UI)
        if (typeof window.showNotification === 'function') {
            console.log('Form handling skipped - futuristic-main.js detected');
            return;
        }
        
        const contactForm = document.querySelector(CONFIG.SELECTORS.contactForm);
        const submitBtn = document.querySelector(CONFIG.SELECTORS.submitBtn);
        
        if (!contactForm) return;

        // Handle form field focus effects
        const formFields = contactForm.querySelectorAll('input, select, textarea');
        formFields.forEach(field => FormUtils.handleFieldFocus(field));

        // Handle form submission
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Disable submit button
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Sending...';
            }
            
            // Get form data
            const formData = new FormData(this);
            
            // Send to PHP backend
            fetch('process-form.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Success
                    const message = `Thank you! We will contact you within 2 hours to discuss your free quote.\n\nNeed immediate assistance? Call ${data.phone} now!`;
                    alert(message);
                    this.reset();
                    AnalyticsUtils.trackConversion('form_submission', { phone: data.phone });
                } else {
                    // Error
                    const errorMsg = data.errors ? data.errors.join('\n') : 'Something went wrong. Please try again.';
                    alert(`Please correct the following:\n\n${errorMsg}\n\nOr call us directly: ${data.phone}`);
                }
            })
            .catch(error => {
                console.error('Form submission error:', error);
                alert(`There was a problem submitting your form. Please call us directly at ${CONFIG.EMERGENCY_PHONE} or try again.`);
            })
            .finally(() => {
                // Re-enable submit button
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Get My Free Quote';
                }
            });
        });
    }
};

// Navigation utilities
const NavigationUtils = {
    /**
     * Initialize smooth scrolling
     */
    initSmoothScrolling() {
        const navLinks = document.querySelectorAll('a[href^="#"]');
        
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    const offsetTop = targetElement.offsetTop - CONFIG.ANIMATION_OFFSET;
                    
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            });
        });
    },

    /**
     * Initialize mobile navigation
     */
    initMobileNavigation() {
        const mobileMenuToggle = document.querySelector(CONFIG.SELECTORS.mobileMenu);
        const navMenu = document.querySelector(CONFIG.SELECTORS.navMenu);
        const navLinks = document.querySelectorAll('.nav-link');
        
        if (!mobileMenuToggle || !navMenu) return;
        
        // Toggle mobile menu
        mobileMenuToggle.addEventListener('click', function() {
            mobileMenuToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
            
            // Prevent body scroll when menu is open
            if (navMenu.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        });
        
        // Close mobile menu when clicking nav links
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                mobileMenuToggle.classList.remove('active');
                navMenu.classList.remove('active');
                document.body.style.overflow = '';
            });
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!mobileMenuToggle.contains(e.target) && !navMenu.contains(e.target)) {
                mobileMenuToggle.classList.remove('active');
                navMenu.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    },

    /**
     * Initialize header scroll effects
     */
    initHeaderScroll() {
        const header = document.querySelector(CONFIG.SELECTORS.header);
        if (!header) return;
        
        let lastScrollY = window.scrollY;
        
        const handleScroll = PerformanceUtils.throttle(function() {
            const currentScrollY = window.scrollY;
            
            if (currentScrollY > 100) {
                header.style.background = 'rgba(255, 255, 255, 0.98)';
                header.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.1)';
            } else {
                header.style.background = 'rgba(255, 255, 255, 0.95)';
                header.style.boxShadow = 'none';
            }
            
            // Hide/show navbar on scroll (mobile only)
            if (window.innerWidth <= 768) {
                if (currentScrollY > lastScrollY && currentScrollY > 100) {
                    header.style.transform = 'translateY(-100%)';
                } else {
                    header.style.transform = 'translateY(0)';
                }
            }
            
            lastScrollY = currentScrollY;
        }, CONFIG.SCROLL_THROTTLE_MS);
        
        window.addEventListener('scroll', handleScroll);
    }
};

// Animation utilities
const AnimationUtils = {
    /**
     * Initialize scroll animations
     */
    initScrollAnimations() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        // Add animation styles and observe elements
        const animatedElements = document.querySelectorAll(CONFIG.SELECTORS.animatedElements);
        
        animatedElements.forEach((element, index) => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(30px)';
            element.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
            observer.observe(element);
        });
    },

    /**
     * Initialize card hover effects
     */
    initCardHoverEffects() {
        const cards = document.querySelectorAll(`${CONFIG.SELECTORS.serviceCards}, ${CONFIG.SELECTORS.propertyCards}, ${CONFIG.SELECTORS.landCards}`);
        
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px)';
                this.style.boxShadow = '0 12px 32px rgba(0, 0, 0, 0.2)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
            });
        });
    }
};

// Contact utilities
const ContactUtils = {
    /**
     * Initialize phone call tracking
     */
    initPhoneTracking() {
        document.querySelectorAll('a[href^="tel:"]').forEach(link => {
            link.addEventListener('click', function() {
                const phoneNumber = this.href.replace('tel:', '');
                const location = this.closest('header') ? 'header' : 'content';
                AnalyticsUtils.trackPhoneCall(phoneNumber, location);
            });
        });
    },

    /**
     * Handle emergency contact functionality
     * @returns {boolean} True if emergency contact should proceed
     */
    handleEmergencyContact() {
        const now = new Date();
        const hour = now.getHours();
        const day = now.getDay();
        
        // Check if outside business hours
        if (hour < CONFIG.BUSINESS_HOURS.start || 
            hour > CONFIG.BUSINESS_HOURS.end || 
            CONFIG.BUSINESS_HOURS.closedDays.includes(day)) {
            if (confirm('We are currently outside business hours. This will be treated as an emergency call. Continue?')) {
                AnalyticsUtils.trackConversion('emergency_call');
                return true;
            }
            return false;
        }
        
        AnalyticsUtils.trackConversion('business_hours_call');
        return true;
    }
};

// Performance monitoring
const PerformanceMonitor = {
    /**
     * Initialize performance monitoring
     */
    init() {
        if ('performance' in window) {
            window.addEventListener('load', function() {
                setTimeout(function() {
                    const perfData = performance.getEntriesByType('navigation')[0];
                    const loadTime = perfData.loadEventEnd - perfData.fetchStart;
                    console.log('Page load time:', loadTime, 'ms');
                    
                    // Track slow page loads
                    if (loadTime > 3000) {
                        console.warn('Slow page load detected:', loadTime, 'ms');
                    }
                }, 1000);
            });
        }
    }
};

// PWA utilities
const PWAUtils = {
    /**
     * Initialize service worker
     */
    initServiceWorker() {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('Service Worker registered:', registration);
                    })
                    .catch(function(registrationError) {
                        console.log('Service Worker registration failed:', registrationError);
                    });
            });
        }
    }
};

// Main initialization
class NorthwestPropertyApp {
    constructor() {
        this.initialized = false;
    }

    /**
     * Initialize the application
     */
    init() {
        if (this.initialized) return;
        
        console.log('Northwest Property & Land Sales - Core JavaScript Initializing');
        
        try {
            // Initialize all modules
            NavigationUtils.initSmoothScrolling();
            NavigationUtils.initMobileNavigation();
            NavigationUtils.initHeaderScroll();
            
            AnimationUtils.initScrollAnimations();
            AnimationUtils.initCardHoverEffects();
            
            FormUtils.initFormHandling();
            ContactUtils.initPhoneTracking();
            
            PerformanceMonitor.init();
            PWAUtils.initServiceWorker();
            
            // Initialize Google Analytics (lazy loaded)
            if (CONFIG.LAZY_LOAD_GA) {
                this.initLazyAnalytics();
            } else {
                AnalyticsUtils.initGoogleAnalytics();
            }
            
            this.initialized = true;
            console.log('Northwest Property & Land Sales - Core JavaScript Initialized Successfully');
            
        } catch (error) {
            console.error('Error initializing application:', error);
        }
    }

    /**
     * Initialize lazy loading for Google Analytics
     */
    initLazyAnalytics() {
        let gaLoaded = false;
        
        const loadGAOnInteraction = () => {
            if (!gaLoaded) {
                AnalyticsUtils.initGoogleAnalytics();
                gaLoaded = true;
            }
        };

        // Load GA on first user interaction
        ['click', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, loadGAOnInteraction, { once: true, passive: true });
        });
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const app = new NorthwestPropertyApp();
    app.init();
});

// Export for testing and external use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        NorthwestPropertyApp,
        CONFIG,
        PerformanceUtils,
        AnalyticsUtils,
        FormUtils,
        NavigationUtils,
        AnimationUtils,
        ContactUtils,
        PerformanceMonitor,
        PWAUtils
    };
}