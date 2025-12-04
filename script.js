// Initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
    initializePropertyField();
    initializeMobileMenu();
    initializeForm();
    initializeTestimonials();
    initializeScrollAnimations();
    initializeStickyCTA();
    initializeSmoothScroll();
});

// Initialize Property Field Background Animation
function initializePropertyField() {
    const propertyField = document.getElementById('propertyField');
    if (!propertyField) return;
    
    // Create property-themed elements (representing property lines/boundaries)
    const elementCount = 120;
    const layers = 5;
    
    for (let i = 0; i < elementCount; i++) {
        const element = document.createElement('div');
        const layer = Math.floor(Math.random() * layers) + 1;
        element.classList.add('property-element', `layer-${layer}`);
        
        // Random horizontal position
        element.style.left = `${Math.random() * 100}%`;
        
        // Random animation delay and duration for natural movement
        element.style.animationDelay = `${Math.random() * 5}s`;
        element.style.animationDuration = `${3 + Math.random() * 2}s`; // 3s to 5s
        
        // 15% chance for a stronger gust animation
        if (Math.random() < 0.15) {
            element.style.animationName = 'propertyGust';
            element.style.animationDuration = `${2.5 + Math.random() * 1.5}s`; // 2.5s to 4s
        }
        
        // Slight color variation (blue tones)
        const hueOffset = (Math.random() - 0.5) * 15; // -7.5 to 7.5
        const saturationOffset = (Math.random() - 0.5) * 20; // -10 to 10
        element.style.filter = `hue-rotate(${hueOffset}deg) saturate(${100 + saturationOffset}%)`;
        
        propertyField.appendChild(element);
    }
    
    // Add subtle parallax effect on scroll
    let lastScrollTop = 0;
    window.addEventListener('scroll', () => {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const parallax = scrollTop * 0.08; // Adjust parallax strength
        propertyField.style.transform = `translateY(${parallax}px)`;
        lastScrollTop = scrollTop;
    }, { passive: true });
}

// Initialize Mobile Menu
function initializeMobileMenu() {
    const navToggle = document.getElementById('navToggle');
    const navLinks = document.getElementById('navLinks');
    
    if (!navToggle || !navLinks) return;
    
    navToggle.addEventListener('click', () => {
        navLinks.classList.toggle('active');
        navToggle.classList.toggle('active');
    });
    
    // Close menu when clicking on a link
    const links = navLinks.querySelectorAll('.nav-link');
    links.forEach(link => {
        link.addEventListener('click', () => {
            navLinks.classList.remove('active');
            navToggle.classList.remove('active');
        });
    });
    
    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!navToggle.contains(e.target) && !navLinks.contains(e.target)) {
            navLinks.classList.remove('active');
            navToggle.classList.remove('active');
        }
    });
}

// Initialize Form
function initializeForm() {
    const form = document.getElementById('quoteForm');
    if (!form) return;
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = form.querySelector('.submit-btn');
        const formMessage = document.getElementById('formMessage');
        
        // Disable submit button
        submitBtn.disabled = true;
        const originalText = submitBtn.querySelector('span').textContent;
        submitBtn.querySelector('span').textContent = 'Submitting...';
        
        // Get form data
        const formData = new FormData(form);
        
        try {
            const response = await fetch('process-form.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                formMessage.className = 'form-message success';
                formMessage.textContent = result.message || 'Thank you! We will contact you within 24 hours.';
                form.reset();
                
                // Scroll to message
                formMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                formMessage.className = 'form-message error';
                formMessage.textContent = result.message || 'Something went wrong. Please try again.';
            }
        } catch (error) {
            formMessage.className = 'form-message error';
            formMessage.textContent = 'Network error. Please check your connection and try again.';
        } finally {
            // Re-enable submit button
            submitBtn.disabled = false;
            submitBtn.querySelector('span').textContent = originalText;
        }
    });
    
    // Add input validation feedback
    const inputs = form.querySelectorAll('.form-input');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                this.style.borderColor = 'rgba(239, 68, 68, 0.5)';
            } else {
                this.style.borderColor = '';
            }
        });
        
        input.addEventListener('input', function() {
            if (this.style.borderColor.includes('239')) {
                this.style.borderColor = '';
            }
        });
    });
}

// Initialize Testimonials Slider
function initializeTestimonials() {
    const track = document.getElementById('testimonialTrack');
    const prevBtn = document.getElementById('prevTestimonial');
    const nextBtn = document.getElementById('nextTestimonial');
    
    if (!track || !prevBtn || !nextBtn) return;
    
    const items = track.querySelectorAll('.testimonial-item');
    let currentIndex = 0;
    
    function updateSlider() {
        track.style.transform = `translateX(-${currentIndex * 100}%)`;
    }
    
    function nextTestimonial() {
        currentIndex = (currentIndex + 1) % items.length;
        updateSlider();
    }
    
    function prevTestimonial() {
        currentIndex = (currentIndex - 1 + items.length) % items.length;
        updateSlider();
    }
    
    nextBtn.addEventListener('click', nextTestimonial);
    prevBtn.addEventListener('click', prevTestimonial);
    
    // Auto-advance testimonials
    setInterval(nextTestimonial, 6000);
}

// Initialize Scroll Animations
function initializeScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-up');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    // Observe elements for animation
    const animateElements = document.querySelectorAll(
        '.service-card, .property-card, .benefit-card, .area-card, .faq-item'
    );
    
    animateElements.forEach(el => {
        observer.observe(el);
    });
}

// Initialize Sticky CTA
function initializeStickyCTA() {
    const stickyCta = document.getElementById('stickyCta');
    if (!stickyCta) return;
    
    const quoteSection = document.getElementById('quote');
    if (!quoteSection) return;
    
    function checkScroll() {
        const quoteSectionTop = quoteSection.getBoundingClientRect().top;
        
        if (quoteSectionTop < 0) {
            stickyCta.classList.add('visible');
        } else {
            stickyCta.classList.remove('visible');
        }
    }
    
    window.addEventListener('scroll', checkScroll, { passive: true });
    checkScroll(); // Initial check
}

// Initialize Smooth Scroll
function initializeSmoothScroll() {
    // Handle anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href === '#' || href === '#!') return;
            
            const target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                const navHeight = document.querySelector('.nav-container').offsetHeight;
                const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - navHeight;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
                
                // Close mobile menu if open
                const navLinks = document.getElementById('navLinks');
                const navToggle = document.getElementById('navToggle');
                if (navLinks && navLinks.classList.contains('active')) {
                    navLinks.classList.remove('active');
                    navToggle.classList.remove('active');
                }
            }
        });
    });
}

// FAQ Toggle Function (called from HTML onclick)
function toggleFaq(button) {
    const faqItem = button.closest('.faq-item');
    const isActive = faqItem.classList.contains('active');
    
    // Close all FAQ items
    document.querySelectorAll('.faq-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Open clicked item if it wasn't active
    if (!isActive) {
        faqItem.classList.add('active');
    }
}

// Navbar scroll effect
window.addEventListener('scroll', () => {
    const nav = document.querySelector('.nav-container');
    if (window.scrollY > 50) {
        nav.style.background = 'rgba(5, 5, 8, 0.95)';
        nav.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.3)';
    } else {
        nav.style.background = 'rgba(5, 5, 8, 0.8)';
        nav.style.boxShadow = 'none';
    }
}, { passive: true });

// Property card hover effects
document.querySelectorAll('.property-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-10px) scale(1.02)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0) scale(1)';
    });
});

// Service card hover effects
document.querySelectorAll('.service-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        const icon = this.querySelector('.service-icon');
        if (icon) {
            icon.style.transform = 'scale(1.1) rotate(5deg)';
        }
    });
    
    card.addEventListener('mouseleave', function() {
        const icon = this.querySelector('.service-icon');
        if (icon) {
            icon.style.transform = 'scale(1) rotate(0deg)';
        }
    });
});

// Form input focus effects
document.querySelectorAll('.form-input').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.style.transform = 'scale(1.02)';
    });
    
    input.addEventListener('blur', function() {
        this.parentElement.style.transform = 'scale(1)';
    });
});

// Loading animation for images
document.querySelectorAll('img').forEach(img => {
    img.addEventListener('load', function() {
        this.style.opacity = '1';
    });
    
    // Set initial opacity
    img.style.opacity = '0';
    img.style.transition = 'opacity 0.3s ease';
});

// Performance optimization: Debounce scroll events
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

// Apply debounce to scroll-heavy functions
const debouncedScroll = debounce(() => {
    // Additional scroll-based animations can go here
}, 10);

window.addEventListener('scroll', debouncedScroll, { passive: true });

