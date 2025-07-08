/* ===================================
   NORTHWEST PROPERTY & LAND SALES
   Main JavaScript File
   =================================== */

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Northwest Property & Land Sales - Website Loaded');
    
    // Initialize all functionality
    initMobileNavigation();
    initSmoothScrolling();
    initScrollAnimations();
    initNavbarScroll();
    initPropertyCards();
    initLandCards();
    initScrollToTop();
    initFormValidation();
    
    // Google Analytics (if ID is provided)
    if (typeof gtag !== 'undefined') {
        console.log('Google Analytics loaded');
    }
});

/* ===================================
   MOBILE NAVIGATION
   =================================== */
function initMobileNavigation() {
    const hamburger = document.querySelector('.hamburger');
    const navMenu = document.querySelector('.nav-menu');
    const navLinks = document.querySelectorAll('.nav-link');
    
    if (!hamburger || !navMenu) return;
    
    // Toggle mobile menu
    hamburger.addEventListener('click', function() {
        hamburger.classList.toggle('active');
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
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
            document.body.style.overflow = '';
        });
    });
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(e) {
        if (!hamburger.contains(e.target) && !navMenu.contains(e.target)) {
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
}

/* ===================================
   SMOOTH SCROLLING
   =================================== */
function initSmoothScrolling() {
    const navLinks = document.querySelectorAll('a[href^="#"]');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                const offsetTop = targetElement.offsetTop - 80; // Account for fixed navbar
                
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });
            }
        });
    });
}

/* ===================================
   NAVBAR SCROLL EFFECT
   =================================== */
function initNavbarScroll() {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;
    
    let lastScrollY = window.scrollY;
    
    function handleScroll() {
        const currentScrollY = window.scrollY;
        
        if (currentScrollY > 100) {
            navbar.style.background = 'rgba(255, 255, 255, 0.98)';
            navbar.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.1)';
        } else {
            navbar.style.background = 'rgba(255, 255, 255, 0.95)';
            navbar.style.boxShadow = 'none';
        }
        
        // Hide/show navbar on scroll (mobile only)
        if (window.innerWidth <= 768) {
            if (currentScrollY > lastScrollY && currentScrollY > 100) {
                navbar.style.transform = 'translateY(-100%)';
            } else {
                navbar.style.transform = 'translateY(0)';
            }
        }
        
        lastScrollY = currentScrollY;
    }
    
    // Throttle scroll events for better performance
    let ticking = false;
    window.addEventListener('scroll', function() {
        if (!ticking) {
            requestAnimationFrame(function() {
                handleScroll();
                ticking = false;
            });
            ticking = true;
        }
    });
}

/* ===================================
   SCROLL ANIMATIONS
   =================================== */
function initScrollAnimations() {
    // Intersection Observer for fade-in animations
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
    const animatedElements = document.querySelectorAll('.section-header, .property-card, .land-card, .about-feature, .contact-item');
    
    animatedElements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(30px)';
        element.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        observer.observe(element);
    });
}

/* ===================================
   PROPERTY CARDS INTERACTIONS
   =================================== */
function initPropertyCards() {
    const propertyCards = document.querySelectorAll('.property-card');
    
    propertyCards.forEach(card => {
        // Add hover effect enhancement
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px)';
            this.style.boxShadow = '0 12px 32px rgba(0, 0, 0, 0.2)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
        });
        
        // Handle button clicks
        const viewDetailsBtn = card.querySelector('.btn-outline');
        const arrangeViewingBtn = card.querySelector('.btn-primary');
        
        if (viewDetailsBtn) {
            viewDetailsBtn.addEventListener('click', function() {
                const propertyTitle = card.querySelector('.property-title').textContent;
                console.log('View details clicked for:', propertyTitle);
                // Here you would typically navigate to property details page
                // window.location.href = `property-details.html?property=${encodeURIComponent(propertyTitle)}`;
            });
        }
        
        if (arrangeViewingBtn) {
            arrangeViewingBtn.addEventListener('click', function() {
                const propertyTitle = card.querySelector('.property-title').textContent;
                console.log('Arrange viewing clicked for:', propertyTitle);
                // Pre-fill contact form or open modal
                scrollToContactForm(propertyTitle);
            });
        }
    });
}

/* ===================================
   LAND CARDS INTERACTIONS
   =================================== */
function initLandCards() {
    const landCards = document.querySelectorAll('.land-card');
    
    landCards.forEach(card => {
        // Add hover effect enhancement
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px)';
            this.style.boxShadow = '0 12px 32px rgba(0, 0, 0, 0.2)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
        });
        
        // Handle button clicks
        const downloadBtn = card.querySelector('.btn-outline');
        const enquireBtn = card.querySelector('.btn-primary');
        
        if (downloadBtn) {
            downloadBtn.addEventListener('click', function() {
                const landTitle = card.querySelector('.land-title').textContent;
                console.log('Download brochure clicked for:', landTitle);
                // Here you would trigger PDF download
                downloadBrochure(landTitle);
            });
        }
        
        if (enquireBtn) {
            enquireBtn.addEventListener('click', function() {
                const landTitle = card.querySelector('.land-title').textContent;
                console.log('Enquire now clicked for:', landTitle);
                scrollToContactForm(landTitle);
            });
        }
    });
}

/* ===================================
   CONTACT FORM FUNCTIONALITY
   =================================== */
function scrollToContactForm(propertyName = '') {
    const contactSection = document.querySelector('#contact');
    const messageField = document.querySelector('textarea[name="message"]');
    const inquiryType = document.querySelector('select[name="inquiry_type"]');
    
    if (contactSection) {
        contactSection.scrollIntoView({ behavior: 'smooth' });
        
        // Pre-fill form if property/land specified
        if (propertyName && messageField) {
            setTimeout(() => {
                messageField.value = `I'm interested in: ${propertyName}\n\nPlease provide more information about this property.`;
                messageField.focus();
            }, 1000);
        }
        
        // Set appropriate inquiry type
        if (inquiryType) {
            if (propertyName.toLowerCase().includes('land')) {
                inquiryType.value = 'land_development';
            } else {
                inquiryType.value = 'property_purchase';
            }
        }
    }
}

function downloadBrochure(landTitle) {
    // Simulate brochure download
    console.log(`Downloading brochure for: ${landTitle}`);
    
    // In a real implementation, you would:
    // 1. Track the download event in analytics
    // 2. Trigger the actual PDF download
    // 3. Possibly capture lead information
    
    // For demo purposes, show an alert
    alert(`Brochure download for "${landTitle}" would start here.\n\nIn the full implementation, this would download a PDF brochure.`);
    
    // Track event in Google Analytics if available
    if (typeof gtag !== 'undefined') {
        gtag('event', 'download', {
            'event_category': 'Land Brochure',
            'event_label': landTitle
        });
    }
}

/* ===================================
   FORM VALIDATION
   =================================== */
function initFormValidation() {
    const contactForm = document.querySelector('.inquiry-form');
    if (!contactForm) return;
    
    contactForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        const formData = new FormData(this);
        const data = Object.fromEntries(formData);
        
        // Basic validation
        if (!data.name || !data.email || !data.inquiry_type) {
            showNotification('Please fill in all required fields.', 'error');
            return;
        }
        
        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(data.email)) {
            showNotification('Please enter a valid email address.', 'error');
            return;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Sending...';
        submitBtn.disabled = true;
        
        // Simulate form submission (replace with actual submission)
        setTimeout(() => {
            showNotification('Thank you for your inquiry! We\'ll get back to you within 2 hours.', 'success');
            this.reset();
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
            
            // Track form submission in Google Analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', 'form_submit', {
                    'event_category': 'Contact',
                    'event_label': data.inquiry_type
                });
            }
        }, 2000);
    });
}

/* ===================================
   SCROLL TO TOP BUTTON
   =================================== */
function initScrollToTop() {
    // Create scroll to top button
    const scrollToTopBtn = document.createElement('button');
    scrollToTopBtn.innerHTML = '↑';
    scrollToTopBtn.className = 'scroll-to-top';
    scrollToTopBtn.style.cssText = `
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--primary-black);
        color: var(--primary-white);
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    `;
    
    document.body.appendChild(scrollToTopBtn);
    
    // Show/hide button based on scroll position
    window.addEventListener('scroll', function() {
        if (window.scrollY > 500) {
            scrollToTopBtn.style.opacity = '1';
            scrollToTopBtn.style.visibility = 'visible';
        } else {
            scrollToTopBtn.style.opacity = '0';
            scrollToTopBtn.style.visibility = 'hidden';
        }
    });
    
    // Scroll to top when clicked
    scrollToTopBtn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    
    // Hover effects
    scrollToTopBtn.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-3px)';
        this.style.boxShadow = '0 6px 16px rgba(0, 0, 0, 0.3)';
    });
    
    scrollToTopBtn.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.2)';
    });
}

/* ===================================
   NOTIFICATION SYSTEM
   =================================== */
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Styling
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 30px;
        padding: 16px 24px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        max-width: 300px;
        word-wrap: break-word;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    `;
    
    // Set background color based on type
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    notification.style.background = colors[type] || colors.info;
    
    // Add to DOM and animate in
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
    
    // Allow manual dismissal
    notification.addEventListener('click', function() {
        this.style.transform = 'translateX(100%)';
        setTimeout(() => {
            this.remove();
        }, 300);
    });
}

/* ===================================
   UTILITY FUNCTIONS
   =================================== */

// Debounce function for performance optimization
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Throttle function for scroll events
function throttle(func, limit) {
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
}

// Check if element is in viewport
function isInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

/* ===================================
   GOOGLE ANALYTICS SETUP
   =================================== */

// Initialize Google Analytics (replace GA_MEASUREMENT_ID with actual ID)
function initGoogleAnalytics() {
    const GA_MEASUREMENT_ID = 'GA_MEASUREMENT_ID'; // Replace with actual ID
    
    if (GA_MEASUREMENT_ID !== 'GA_MEASUREMENT_ID') {
        // Load Google Analytics script
        const script = document.createElement('script');
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtag/js?id=${GA_MEASUREMENT_ID}`;
        document.head.appendChild(script);
        
        // Initialize gtag
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', GA_MEASUREMENT_ID);
        
        // Make gtag available globally
        window.gtag = gtag;
        
        console.log('Google Analytics initialized with ID:', GA_MEASUREMENT_ID);
    }
}

// Initialize Google Analytics on load
// initGoogleAnalytics(); // Uncomment when GA ID is available

/* ===================================
   ERROR HANDLING
   =================================== */
window.addEventListener('error', function(e) {
    console.error('JavaScript Error:', e.error);
    
    // Track errors in Google Analytics if available
    if (typeof gtag !== 'undefined') {
        gtag('event', 'exception', {
            'description': e.error.toString(),
            'fatal': false
        });
    }
});

// Handle unhandled promise rejections
window.addEventListener('unhandledrejection', function(e) {
    console.error('Unhandled Promise Rejection:', e.reason);
    
    if (typeof gtag !== 'undefined') {
        gtag('event', 'exception', {
            'description': e.reason.toString(),
            'fatal': false
        });
    }
});