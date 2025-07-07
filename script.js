// Land Property Northwest - Main JavaScript File

// Smooth scrolling for navigation links
document.addEventListener('DOMContentLoaded', function() {
    
    // Smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                const headerHeight = document.querySelector('.header').offsetHeight;
                const targetPosition = target.offsetTop - headerHeight;
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    // Form submission
    const contactForm = document.querySelector('#contactForm');
    const submitBtn = document.querySelector('#submitBtn');
    
    if (contactForm) {
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
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success
                    alert(`Thank you! We will contact you within 2 hours to discuss your free quote.\n\nNeed immediate assistance? Call ${data.phone} now!`);
                    this.reset();
                    trackConversion('form_submission');
                } else {
                    // Error
                    const errorMsg = data.errors ? data.errors.join('\n') : 'Something went wrong. Please try again.';
                    alert(`Please correct the following:\n\n${errorMsg}\n\nOr call us directly: ${data.phone}`);
                }
            })
            .catch(error => {
                console.error('Form submission error:', error);
                alert('There was a problem submitting your form. Please call us directly at 07561724095 or try again.');
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

    // Mobile menu toggle
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const navMenu = document.querySelector('.nav-menu');
    
    if (mobileMenuToggle && navMenu) {
        mobileMenuToggle.addEventListener('click', function() {
            navMenu.style.display = navMenu.style.display === 'flex' ? 'none' : 'flex';
        });
    }

    // Header scroll effect
    window.addEventListener('scroll', function() {
        const header = document.querySelector('.header');
        if (header) {
            if (window.scrollY > 100) {
                header.style.background = 'rgba(255, 255, 255, 0.95)';
                header.style.backdropFilter = 'blur(10px)';
            } else {
                header.style.background = 'var(--white)';
                header.style.backdropFilter = 'none';
            }
        }
    });

    // Intersection Observer for animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-up');
            }
        });
    }, observerOptions);

    // Observe service cards, area cards, and other elements
    document.querySelectorAll('.service-card, .area-card, .stat, .contact-item').forEach(el => {
        observer.observe(el);
    });

    // Phone number click tracking (for analytics)
    document.querySelectorAll('a[href^="tel:"]').forEach(link => {
        link.addEventListener('click', function() {
            // Track phone clicks for analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', 'phone_call', {
                    'event_category': 'contact',
                    'event_label': 'header_phone'
                });
            }
            console.log('Phone link clicked:', this.href);
        });
    });

    // Contact form field tracking
    const formFields = document.querySelectorAll('.form-group input, .form-group select, .form-group textarea');
    formFields.forEach(field => {
        field.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        field.addEventListener('blur', function() {
            if (this.value === '') {
                this.parentElement.classList.remove('focused');
            }
        });
    });

    // Service card hover effects
    document.querySelectorAll('.service-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-10px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // Load Google Analytics (replace with your GA4 tracking ID)
    function loadGoogleAnalytics() {
        const script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID';
        document.head.appendChild(script);

        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'GA_MEASUREMENT_ID');
    }

    // Lazy load Google Analytics after user interaction
    let gaLoaded = false;
    function loadGAOnInteraction() {
        if (!gaLoaded) {
            loadGoogleAnalytics();
            gaLoaded = true;
        }
    }

    // Load GA on first user interaction
    ['click', 'scroll', 'touchstart'].forEach(event => {
        document.addEventListener(event, loadGAOnInteraction, { once: true, passive: true });
    });

    // Performance monitoring
    if ('performance' in window) {
        window.addEventListener('load', function() {
            setTimeout(function() {
                const perfData = performance.getEntriesByType('navigation')[0];
                console.log('Page load time:', perfData.loadEventEnd - perfData.fetchStart, 'ms');
            }, 1000);
        });
    }

    // Service Worker registration for PWA capabilities (optional)
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/sw.js')
                .then(function(registration) {
                    console.log('SW registered: ', registration);
                })
                .catch(function(registrationError) {
                    console.log('SW registration failed: ', registrationError);
                });
        });
    }
});

// Utility functions
function formatPhoneNumber(phoneNumber) {
    // Format UK phone numbers nicely
    const cleaned = phoneNumber.replace(/\D/g, '');
    if (cleaned.length === 11 && cleaned.startsWith('07')) {
        return cleaned.replace(/(\d{5})(\d{6})/, '$1 $2');
    }
    return phoneNumber;
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function trackConversion(type) {
    // Track conversions for analytics
    if (typeof gtag !== 'undefined') {
        gtag('event', 'conversion', {
            'event_category': 'lead_generation',
            'event_label': type
        });
    }
}

// Emergency contact functionality
function handleEmergencyContact() {
    const now = new Date();
    const hour = now.getHours();
    const day = now.getDay();
    
    // Check if outside business hours
    if (hour < 8 || hour > 18 || day === 0) {
        if (confirm('We are currently outside business hours. This will be treated as an emergency call. Continue?')) {
            trackConversion('emergency_call');
            return true;
        }
        return false;
    }
    
    trackConversion('business_hours_call');
    return true;
}

// Export functions for testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        formatPhoneNumber,
        validateEmail,
        trackConversion
    };
}