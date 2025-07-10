/* ===================================
   NORTHWEST PROPERTY & LAND
   Futuristic Main JavaScript
   =================================== */

// DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

// Initialize Application
function initializeApp() {
    // Core Initializations
    initLoadingScreen();
    initCustomCursor();
    initNavigation();
    initBackgroundEffects();
    initScrollAnimations();
    initHeroAnimations();
    initPropertyCards();
    initFormHandling();
    initBackToTop();
    
    // Advanced Features
    initNumberCounters();
    initTiltEffects();
    initMagneticButtons();
    initGlitchEffects();
    initParticleSystem();
}

// Loading Screen
function initLoadingScreen() {
    const loadingScreen = document.querySelector('.loading-screen');
    const loadingProgress = document.querySelector('.loading-progress');
    
    // Simulate loading progress
    let progress = 0;
    const loadingInterval = setInterval(() => {
        progress += Math.random() * 30;
        if (progress > 100) progress = 100;
        
        if (loadingProgress) {
            loadingProgress.style.width = progress + '%';
        }
        
        if (progress === 100) {
            clearInterval(loadingInterval);
            setTimeout(() => {
                if (loadingScreen) {
                    loadingScreen.classList.add('loaded');
                    document.body.style.overflow = 'visible';
                }
            }, 500);
        }
    }, 300);
}

// Custom Cursor
function initCustomCursor() {
    const cursor = document.querySelector('.cursor');
    const cursorFollower = document.querySelector('.cursor-follower');
    
    if (!cursor || !cursorFollower) return;
    
    let mouseX = 0, mouseY = 0;
    let cursorX = 0, cursorY = 0;
    let followerX = 0, followerY = 0;
    
    // Update mouse position
    document.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;
    });
    
    // Animate cursor
    function animateCursor() {
        // Main cursor
        cursorX += (mouseX - cursorX) * 0.2;
        cursorY += (mouseY - cursorY) * 0.2;
        cursor.style.left = cursorX - 10 + 'px';
        cursor.style.top = cursorY - 10 + 'px';
        
        // Follower
        followerX += (mouseX - followerX) * 0.1;
        followerY += (mouseY - followerY) * 0.1;
        cursorFollower.style.left = followerX - 20 + 'px';
        cursorFollower.style.top = followerY - 20 + 'px';
        
        requestAnimationFrame(animateCursor);
    }
    animateCursor();
    
    // Cursor hover effects
    const hoverElements = document.querySelectorAll('a, button, .property-card, .filter-chip, .action-btn');
    hoverElements.forEach(element => {
        element.addEventListener('mouseenter', () => {
            cursor.classList.add('hover');
            cursorFollower.style.transform = 'scale(1.5)';
        });
        
        element.addEventListener('mouseleave', () => {
            cursor.classList.remove('hover');
            cursorFollower.style.transform = 'scale(1)';
        });
    });
    
    // Hide cursor when leaving window
    document.addEventListener('mouseleave', () => {
        cursor.style.opacity = '0';
        cursorFollower.style.opacity = '0';
    });
    
    document.addEventListener('mouseenter', () => {
        cursor.style.opacity = '1';
        cursorFollower.style.opacity = '1';
    });
}

// Navigation
function initNavigation() {
    const navbar = document.querySelector('.navbar');
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
    const navLinks = document.querySelectorAll('.nav-link');
    
    // Mobile menu toggle
    if (navToggle) {
        navToggle.addEventListener('click', () => {
            navToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
            document.body.style.overflow = navMenu.classList.contains('active') ? 'hidden' : 'visible';
        });
    }
    
    // Close mobile menu on link click
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navToggle.classList.remove('active');
            navMenu.classList.remove('active');
            document.body.style.overflow = 'visible';
        });
    });
    
    // Navbar scroll effect
    let lastScroll = 0;
    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;
        
        if (currentScroll > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        
        // Hide/show navbar on scroll
        if (currentScroll > lastScroll && currentScroll > 300) {
            navbar.style.transform = 'translateY(-100%)';
        } else {
            navbar.style.transform = 'translateY(0)';
        }
        
        lastScroll = currentScroll;
    });
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                const offsetTop = target.offsetTop - 80;
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });
            }
        });
    });
}

// Background Effects
function initBackgroundEffects() {
    // Animated grid parallax
    const grid = document.querySelector('.grid-background');
    if (grid) {
        window.addEventListener('mousemove', (e) => {
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            grid.style.transform = `translate(${x * 20}px, ${y * 20}px)`;
        });
    }
    
    // Gradient orbs animation
    const orbs = document.querySelectorAll('.orb');
    orbs.forEach((orb, index) => {
        orb.style.animationDelay = `${index * 2}s`;
    });
}

// Scroll Animations
function initScrollAnimations() {
    // Intersection Observer for reveal animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                
                // Stagger animations for child elements
                const staggerElements = entry.target.querySelectorAll('.stagger-item');
                staggerElements.forEach((el, index) => {
                    setTimeout(() => {
                        el.classList.add('visible');
                    }, index * 100);
                });
            }
        });
    }, observerOptions);
    
    // Observe elements
    const animatedElements = document.querySelectorAll('.fade-in-up, .fade-in-scale, .slide-in-left, .slide-in-right, .property-card, .opportunity-card, .testimonial-card');
    animatedElements.forEach(el => observer.observe(el));
}

// Hero Animations
function initHeroAnimations() {
    // Animated text
    const titleWords = document.querySelectorAll('.title-word');
    titleWords.forEach((word, index) => {
        word.style.animationDelay = `${index * 0.1}s`;
    });
    
    // Hologram effect
    const hologram = document.querySelector('.hologram-property');
    if (hologram) {
        setInterval(() => {
            hologram.style.opacity = Math.random() > 0.1 ? '1' : '0.7';
        }, 100);
    }
    
    // Stats counter animation
    const stats = document.querySelectorAll('[data-count]');
    stats.forEach(stat => {
        const target = parseInt(stat.getAttribute('data-count'));
        const duration = 2000;
        const increment = target / (duration / 16);
        let current = 0;
        
        const counter = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(counter);
            }
            stat.textContent = Math.floor(current);
        }, 16);
    });
}

// Property Cards
function initPropertyCards() {
    const propertyCards = document.querySelectorAll('.property-card');
    
    propertyCards.forEach(card => {
        // 3D hover effect
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = (y - centerY) / 10;
            const rotateY = (centerX - x) / 10;
            
            card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
        });
        
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0)';
        });
        
        // Click effect
        card.addEventListener('click', function(e) {
            // Create ripple effect
            const ripple = document.createElement('div');
            ripple.className = 'ripple-effect';
            
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
    
    // Save/Compare functionality
    const saveButtons = document.querySelectorAll('.save-btn');
    const compareButtons = document.querySelectorAll('.compare-btn');
    
    saveButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            btn.classList.toggle('saved');
            btn.textContent = btn.classList.contains('saved') ? 'SAVED' : 'SAVE';
            showNotification('Property ' + (btn.classList.contains('saved') ? 'saved' : 'removed'));
        });
    });
    
    compareButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            btn.classList.toggle('comparing');
            updateCompareCount();
        });
    });
}

// Form Handling
function initFormHandling() {
    const form = document.querySelector('#contactForm');
    if (!form) return;
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="loader"></span> SENDING...';
        
        try {
            // Get form data
            const formData = new FormData(form);
            
            // Send to PHP backend
            const response = await fetch('process-form.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Success
                submitBtn.innerHTML = '✓ SENT';
                submitBtn.classList.add('success');
                
                showNotification(`Thank you! We will contact you within 2 hours to discuss your free quote.\n\nNeed immediate assistance? Call ${data.phone} now!`);
                
                // Track conversion
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'conversion', {
                        'event_category': 'lead_generation',
                        'event_label': 'form_submission'
                    });
                }
                
                // Reset form
                setTimeout(() => {
                    form.reset();
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    submitBtn.classList.remove('success');
                }, 3000);
            } else {
                // Error
                const errorMsg = data.errors ? data.errors.join('\n') : 'Something went wrong. Please try again.';
                showNotification(`Please correct the following:\n\n${errorMsg}\n\nOr call us directly: ${data.phone}`);
                
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        } catch (error) {
            console.error('Form submission error:', error);
            showNotification('There was a problem submitting your form. Please call us directly at 07561724095 or try again.');
            
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
    
    // Dynamic label positioning
    const formInputs = form.querySelectorAll('input, textarea, select');
    formInputs.forEach(input => {
        input.addEventListener('focus', () => {
            input.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', () => {
            if (!input.value) {
                input.parentElement.classList.remove('focused');
            }
        });
    });
}

// Back to Top
function initBackToTop() {
    const backToTop = document.querySelector('.back-to-top');
    if (!backToTop) return;
    
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
            backToTop.classList.add('visible');
        } else {
            backToTop.classList.remove('visible');
        }
    });
    
    backToTop.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

// Number Counters
function initNumberCounters() {
    const counters = document.querySelectorAll('[data-count]');
    
    const observerOptions = {
        threshold: 0.5
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !entry.target.classList.contains('counted')) {
                entry.target.classList.add('counted');
                animateCounter(entry.target);
            }
        });
    }, observerOptions);
    
    counters.forEach(counter => observer.observe(counter));
}

// Animate Counter
function animateCounter(element) {
    const target = parseInt(element.getAttribute('data-count'));
    const duration = 2000;
    const increment = target / (duration / 16);
    let current = 0;
    
    const counter = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(counter);
        }
        element.textContent = Math.floor(current);
    }, 16);
}

// Tilt Effects
function initTiltEffects() {
    if (typeof VanillaTilt === 'undefined') return;
    
    const tiltElements = document.querySelectorAll('[data-tilt]');
    
    VanillaTilt.init(tiltElements, {
        max: 10,
        speed: 400,
        glare: true,
        'max-glare': 0.2,
        gyroscope: true
    });
}

// Magnetic Buttons
function initMagneticButtons() {
    const magneticElements = document.querySelectorAll('.btn-futuristic, .filter-chip');
    
    magneticElements.forEach(elem => {
        elem.addEventListener('mousemove', (e) => {
            const rect = elem.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;
            
            elem.style.transform = `translate(${x * 0.3}px, ${y * 0.3}px)`;
        });
        
        elem.addEventListener('mouseleave', () => {
            elem.style.transform = 'translate(0, 0)';
        });
    });
}

// Glitch Effects
function initGlitchEffects() {
    const glitchElements = document.querySelectorAll('.glitch');
    
    glitchElements.forEach(element => {
        // Random glitch trigger
        setInterval(() => {
            if (Math.random() < 0.1) {
                element.style.animation = 'none';
                setTimeout(() => {
                    element.style.animation = '';
                }, 100);
            }
        }, 3000);
    });
}

// Particle System
function initParticleSystem() {
    const particleContainer = document.getElementById('particles');
    if (!particleContainer) return;
    
    // Create particles periodically
    setInterval(() => {
        createParticle(particleContainer);
    }, 300);
}

// Create Particle
function createParticle(container) {
    const particle = document.createElement('div');
    particle.className = 'particle';
    
    const size = Math.random() * 4 + 1;
    particle.style.width = size + 'px';
    particle.style.height = size + 'px';
    particle.style.left = Math.random() * window.innerWidth + 'px';
    particle.style.top = window.innerHeight + 'px';
    
    const duration = Math.random() * 3 + 3;
    particle.style.animationDuration = duration + 's';
    
    container.appendChild(particle);
    
    // Remove particle after animation
    setTimeout(() => {
        particle.remove();
    }, duration * 1000);
}

// Show Notification
function showNotification(message) {
    const notification = document.createElement('div');
    notification.className = 'notification';
    notification.textContent = message;
    
    Object.assign(notification.style, {
        position: 'fixed',
        bottom: '20px',
        right: '20px',
        background: 'var(--primary-white)',
        color: 'var(--primary-black)',
        padding: '1rem 2rem',
        borderRadius: '2px',
        fontSize: '0.875rem',
        fontWeight: '500',
        zIndex: '9999',
        animation: 'slide-in-right 0.3s ease-out'
    });
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slide-out-right 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Update Compare Count
function updateCompareCount() {
    const compareButtons = document.querySelectorAll('.compare-btn.comparing');
    const count = compareButtons.length;
    
    if (count > 0) {
        let compareBar = document.querySelector('.compare-bar');
        if (!compareBar) {
            compareBar = document.createElement('div');
            compareBar.className = 'compare-bar';
            compareBar.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: var(--primary-white);
                color: var(--primary-black);
                padding: 1rem 2rem;
                border-radius: 30px;
                font-weight: 600;
                z-index: 999;
                display: flex;
                align-items: center;
                gap: 1rem;
            `;
            document.body.appendChild(compareBar);
        }
        
        compareBar.innerHTML = `
            Comparing ${count} properties
            <button class="btn-futuristic btn-primary" style="padding: 0.5rem 1rem; font-size: 0.75rem;">
                VIEW COMPARISON
            </button>
        `;
    } else {
        const compareBar = document.querySelector('.compare-bar');
        if (compareBar) compareBar.remove();
    }
}

// Filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const filterChips = document.querySelectorAll('.filter-chip');
    
    filterChips.forEach(chip => {
        chip.addEventListener('click', function() {
            // Remove active class from all chips in the same group
            const siblings = this.parentElement.querySelectorAll('.filter-chip');
            siblings.forEach(sibling => sibling.classList.remove('active'));
            
            // Add active class to clicked chip
            this.classList.add('active');
            
            // Filter logic would go here
            const filterType = this.getAttribute('data-filter');
            console.log('Filtering by:', filterType);
        });
    });
});

// Price range slider
document.addEventListener('DOMContentLoaded', function() {
    const rangeInputs = document.querySelectorAll('.range-input');
    const rangeFill = document.querySelector('.range-fill');
    const minValue = document.querySelector('.min-value');
    const maxValue = document.querySelector('.max-value');
    
    if (rangeInputs.length === 2) {
        rangeInputs.forEach(input => {
            input.addEventListener('input', updateRange);
        });
        
        function updateRange() {
            const min = parseInt(rangeInputs[0].value);
            const max = parseInt(rangeInputs[1].value);
            
            if (min <= max) {
                const percentMin = (min / rangeInputs[0].max) * 100;
                const percentMax = (max / rangeInputs[1].max) * 100;
                
                if (rangeFill) {
                    rangeFill.style.left = percentMin + '%';
                    rangeFill.style.width = (percentMax - percentMin) + '%';
                }
                
                if (minValue) minValue.textContent = '£' + formatNumber(min);
                if (maxValue) maxValue.textContent = max >= 5000000 ? '£5M+' : '£' + formatNumber(max);
            }
        }
    }
});

// Format number with commas
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// Add CSS for notification animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slide-in-right {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slide-out-right {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .ripple-effect {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.5);
        transform: scale(0);
        animation: ripple 0.6s ease-out;
        pointer-events: none;
    }
    
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    .loader {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(0, 0, 0, 0.3);
        border-top-color: var(--primary-black);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);