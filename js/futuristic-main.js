/* ===================================
   NORTHWEST PROPERTY & LAND
   Futuristic Main JavaScript
   =================================== */

// Mobile Detection
const isMobile = () => {
    return window.innerWidth <= 768 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
};

const isTouchDevice = () => {
    return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
};

// Initialize Application
function initializeApp() {
    // Core Initializations
    initLoadingScreen();
    
    // Only initialize custom cursor on desktop
    if (!isMobile() && !isTouchDevice()) {
        initCustomCursor();
    }
    
    initNavigation();
    
    // Reduce background effects on mobile
    if (!isMobile()) {
        initBackgroundEffects();
    } else {
        initMobileBackgroundEffects();
    }
    
    initScrollAnimations();
    initHeroAnimations();
    initPropertyCards();
    initFormHandling();
    initBackToTop();
    
    // Advanced Features - Disable heavy effects on mobile
    if (!isMobile()) {
        initNumberCounters();
        initTiltEffects();
        initMagneticButtons();
        initGlitchEffects();
        initParticleSystem();
    } else {
        initMobileNumberCounters();
        initMobileEffects();
    }
    
    initVirtualTours();
    initSearchFunctionality();
    initPropertyFilters();
    initInvestmentCalculator();
    initContactForm();
    initSmoothScrolling();
    initPerformanceOptimizations();
}

// Loading Screen
function initLoadingScreen() {
    const loadingScreen = document.querySelector('.loading-screen');
    const loadingProgress = document.querySelector('.loading-progress');
    const loadingText = document.querySelector('.loading-text');
    
    if (!loadingScreen || !loadingProgress) return;
    
    // Simulate loading progress with realistic steps
    const loadingSteps = [
        { progress: 20, text: 'Initializing Experience' },
        { progress: 40, text: 'Loading Property Data' },
        { progress: 60, text: 'Preparing Virtual Tours' },
        { progress: 80, text: 'Optimizing Performance' },
        { progress: 100, text: 'Ready to Explore' }
    ];
    
    let currentStep = 0;
    const loadingInterval = setInterval(() => {
        if (currentStep < loadingSteps.length) {
            const step = loadingSteps[currentStep];
            loadingProgress.style.width = step.progress + '%';
            if (loadingText) {
                loadingText.textContent = step.text;
            }
            currentStep++;
        } else {
            clearInterval(loadingInterval);
            setTimeout(() => {
                loadingScreen.classList.add('loaded');
                document.body.style.overflow = 'visible';
                // Trigger entrance animations
                triggerEntranceAnimations();
            }, 800);
        }
    }, 600);
}

// Custom Cursor with enhanced interactions
function initCustomCursor() {
    const cursor = document.querySelector('.cursor');
    const cursorFollower = document.querySelector('.cursor-follower');
    
    if (!cursor || !cursorFollower) return;
    
    let mouseX = 0, mouseY = 0;
    let cursorX = 0, cursorY = 0;
    let followerX = 0, followerY = 0;
    let isMoving = false;
    
    // Update mouse position
    document.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;
        isMoving = true;
        
        // Hide cursor when not moving
        clearTimeout(window.cursorTimeout);
        window.cursorTimeout = setTimeout(() => {
            isMoving = false;
        }, 100);
    });
    
    // Animate cursor with smooth easing
    function animateCursor() {
        // Main cursor with faster response
        cursorX += (mouseX - cursorX) * 0.3;
        cursorY += (mouseY - cursorY) * 0.3;
        cursor.style.left = cursorX - 10 + 'px';
        cursor.style.top = cursorY - 10 + 'px';
        
        // Follower with slower, more fluid movement
        followerX += (mouseX - followerX) * 0.08;
        followerY += (mouseY - followerY) * 0.08;
        cursorFollower.style.left = followerX - 20 + 'px';
        cursorFollower.style.top = followerY - 20 + 'px';
        
        // Scale based on movement
        if (isMoving) {
            cursorFollower.style.transform = 'scale(1.2)';
        } else {
            cursorFollower.style.transform = 'scale(1)';
        }
        
        requestAnimationFrame(animateCursor);
    }
    animateCursor();
    
    // Enhanced cursor hover effects
    const hoverElements = document.querySelectorAll('a, button, .property-card, .filter-chip, .action-btn, .tour-item, .land-filter');
    hoverElements.forEach(element => {
        element.addEventListener('mouseenter', () => {
            cursor.classList.add('hover');
            cursorFollower.style.transform = 'scale(1.5)';
            cursorFollower.style.borderColor = 'rgba(255, 204, 0, 0.5)';
        });
        
        element.addEventListener('mouseleave', () => {
            cursor.classList.remove('hover');
            cursorFollower.style.transform = 'scale(1)';
            cursorFollower.style.borderColor = 'rgba(255, 255, 255, 0.3)';
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

// Enhanced Navigation with better mobile support
function initNavigation() {
    const navbar = document.querySelector('.navbar');
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
    const navLinks = document.querySelectorAll('.nav-link');
    
    // Mobile menu toggle with improved UX
    if (navToggle) {
        navToggle.addEventListener('click', () => {
            const isActive = navToggle.classList.contains('active');
            
            navToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
            
            // Prevent body scroll when menu is open
            document.body.style.overflow = isActive ? 'visible' : 'hidden';
            
            // Add entrance animation for menu items
            if (!isActive) {
                const menuItems = navMenu.querySelectorAll('.nav-item');
                menuItems.forEach((item, index) => {
                    item.style.animationDelay = `${index * 0.1}s`;
                    item.classList.add('menu-entrance');
                });
            }
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
    
    // Enhanced navbar scroll effect
    let lastScroll = 0;
    let scrollTimeout;
    
    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;
        
        // Add scrolled class for styling
        if (currentScroll > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        
        // Hide/show navbar on scroll with debouncing
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            if (currentScroll > lastScroll && currentScroll > 300) {
                navbar.style.transform = 'translateY(-100%)';
            } else {
                navbar.style.transform = 'translateY(0)';
            }
            lastScroll = currentScroll;
        }, 100);
    });
}

// Enhanced Background Effects
function initBackgroundEffects() {
    // Animated grid parallax with performance optimization
    const grid = document.querySelector('.grid-background');
    if (grid) {
        let ticking = false;
        
        function updateGrid(e) {
            if (!ticking) {
                requestAnimationFrame(() => {
                    const x = e.clientX / window.innerWidth;
                    const y = e.clientY / window.innerHeight;
                    
                    grid.style.transform = `translate(${x * 30}px, ${y * 30}px)`;
                    ticking = false;
                });
                ticking = true;
            }
        }
        
        // Throttle mousemove events
        let throttleTimer;
        document.addEventListener('mousemove', (e) => {
            if (!throttleTimer) {
                throttleTimer = setTimeout(() => {
                    updateGrid(e);
                    throttleTimer = null;
                }, 16); // ~60fps
            }
        });
    }
    
    // Enhanced gradient orbs animation
    const orbs = document.querySelectorAll('.orb');
    orbs.forEach((orb, index) => {
        orb.style.animationDelay = `${index * 2}s`;
        
        // Add interactive hover effect
        orb.addEventListener('mouseenter', () => {
            orb.style.transform = 'scale(1.2)';
        });
        
        orb.addEventListener('mouseleave', () => {
            orb.style.transform = 'scale(1)';
        });
    });
}

// Enhanced Scroll Animations with Intersection Observer
function initScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
                
                // Trigger specific animations based on element type
                if (entry.target.classList.contains('property-card')) {
                    entry.target.style.animationDelay = `${Math.random() * 0.5}s`;
                }
                
                if (entry.target.classList.contains('stat-item')) {
                    const statValue = entry.target.querySelector('.stat-value');
                    if (statValue && statValue.dataset.count) {
                        animateCounter(statValue);
                    }
                }
            }
        });
    }, observerOptions);
    
    // Observe elements for animation
    const animateElements = document.querySelectorAll('.property-card, .stat-item, .tour-item, .opportunity-card, .testimonial-card');
    animateElements.forEach(el => observer.observe(el));
}

// Enhanced Hero Animations
function initHeroAnimations() {
    const heroTitle = document.querySelector('.hero-title');
    const heroDescription = document.querySelector('.hero-description');
    const heroActions = document.querySelector('.hero-actions');
    const heroStats = document.querySelector('.hero-stats');
    const hologram = document.querySelector('.property-hologram');
    
    // Staggered entrance animations
    if (heroTitle) {
        const titleWords = heroTitle.querySelectorAll('.title-word');
        titleWords.forEach((word, index) => {
            word.style.animationDelay = `${0.8 + index * 0.2}s`;
        });
    }
    
    // Parallax effect for hero elements
    window.addEventListener('scroll', () => {
        const scrolled = window.pageYOffset;
        const rate = scrolled * -0.5;
        
        if (heroTitle) heroTitle.style.transform = `translateY(${rate * 0.5}px)`;
        if (heroDescription) heroDescription.style.transform = `translateY(${rate * 0.3}px)`;
        if (hologram) hologram.style.transform = `translateY(${rate * 0.2}px)`;
    });
}

// Enhanced Property Cards with better interactions
function initPropertyCards() {
    const propertyCards = document.querySelectorAll('.property-card');
    
    propertyCards.forEach(card => {
        // Enhanced hover effects
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-15px) scale(1.03)';
        });
        
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0) scale(1)';
        });
        
        // Action button functionality
        const saveBtn = card.querySelector('.save-btn');
        const compareBtn = card.querySelector('.compare-btn');
        const enquireBtn = card.querySelector('.enquire-btn');
        
        if (saveBtn) {
            saveBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                saveBtn.textContent = 'SAVED ✓';
                saveBtn.style.background = 'var(--accent-gold)';
                saveBtn.style.color = 'var(--primary-black)';
                showNotification('Property saved to favorites');
            });
        }
        
        if (compareBtn) {
            compareBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                compareBtn.textContent = 'ADDED ✓';
                compareBtn.style.background = 'var(--accent-gold)';
                compareBtn.style.color = 'var(--primary-black)';
                updateCompareCount();
                showNotification('Property added to comparison');
            });
        }
        
        if (enquireBtn) {
            enquireBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                showEnquiryModal(card);
            });
        }
        
        // 3D View and Virtual Tour buttons
        const view3dBtn = card.querySelector('.view-3d-btn');
        const tourBtn = card.querySelector('.tour-btn');
        
        if (view3dBtn) {
            view3dBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                show3DViewer(card);
            });
        }
        
        if (tourBtn) {
            tourBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                showVirtualTour(card);
            });
        }
    });
}

// Virtual Tours functionality
function initVirtualTours() {
    const tourItems = document.querySelectorAll('.tour-item');
    const tourViewer = document.querySelector('#tourViewer iframe');
    
    tourItems.forEach((item, index) => {
        item.addEventListener('click', () => {
            // Remove active class from all items
            tourItems.forEach(tour => tour.classList.remove('active'));
            
            // Add active class to clicked item
            item.classList.add('active');
            
            // Update tour viewer (you would replace these with actual tour URLs)
            const tourUrls = [
                'https://my.matterport.com/show/?m=SxQL3iGyvQk',
                'https://my.matterport.com/show/?m=example2',
                'https://my.matterport.com/show/?m=example3'
            ];
            
            if (tourViewer && tourUrls[index]) {
                tourViewer.src = tourUrls[index];
            }
        });
    });
    
    // Tour controls
    const fullscreenBtn = document.querySelector('#fullscreenBtn');
    const vrBtn = document.querySelector('#vrBtn');
    const floorplanBtn = document.querySelector('#floorplanBtn');
    
    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', () => {
            if (tourViewer) {
                tourViewer.requestFullscreen();
            }
        });
    }
    
    if (vrBtn) {
        vrBtn.addEventListener('click', () => {
            showNotification('VR mode activated');
        });
    }
    
    if (floorplanBtn) {
        floorplanBtn.addEventListener('click', () => {
            showNotification('Floorplan view activated');
        });
    }
}

// Search functionality
function initSearchFunctionality() {
    const searchInput = document.querySelector('.search-input');
    const searchBtn = document.querySelector('.search-btn');
    const filterChips = document.querySelectorAll('.filter-chip');
    
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            filterProperties(query);
        });
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', () => {
            const query = searchInput.value.toLowerCase();
            filterProperties(query);
        });
    }
    
    // Filter chips
    filterChips.forEach(chip => {
        chip.addEventListener('click', () => {
            filterChips.forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            
            const filter = chip.dataset.filter;
            filterPropertiesByType(filter);
        });
    });
}

// Property filtering
function filterProperties(query) {
    const propertyCards = document.querySelectorAll('.property-card');
    
    propertyCards.forEach(card => {
        const title = card.querySelector('.property-title').textContent.toLowerCase();
        const location = card.querySelector('.property-location').textContent.toLowerCase();
        const features = Array.from(card.querySelectorAll('.feature-text'))
            .map(feature => feature.textContent.toLowerCase())
            .join(' ');
        
        const matches = title.includes(query) || 
                       location.includes(query) || 
                       features.includes(query);
        
        if (matches) {
            card.style.display = 'block';
            card.style.animation = 'fadeIn 0.5s ease';
        } else {
            card.style.display = 'none';
        }
    });
}

function filterPropertiesByType(type) {
    const propertyCards = document.querySelectorAll('.property-card');
    
    propertyCards.forEach(card => {
        if (type === 'all') {
            card.style.display = 'block';
        } else {
            // Add data-type attributes to your property cards for this to work
            const cardType = card.dataset.type;
            card.style.display = cardType === type ? 'block' : 'none';
        }
    });
}

// Investment Calculator
function initInvestmentCalculator() {
    const calculatorInputs = document.querySelectorAll('.calc-input, .calc-range');
    const resultCards = document.querySelectorAll('.result-card');
    
    calculatorInputs.forEach(input => {
        input.addEventListener('input', updateCalculator);
    });
    
    function updateCalculator() {
        // Get input values
        const propertyValue = parseFloat(document.querySelector('#propertyValue')?.value) || 500000;
        const deposit = parseFloat(document.querySelector('#deposit')?.value) || 100000;
        const interestRate = parseFloat(document.querySelector('#interestRate')?.value) || 3.5;
        const term = parseFloat(document.querySelector('#term')?.value) || 25;
        
        // Calculate results
        const loanAmount = propertyValue - deposit;
        const monthlyPayment = calculateMonthlyPayment(loanAmount, interestRate, term);
        const totalInterest = (monthlyPayment * term * 12) - loanAmount;
        const totalCost = monthlyPayment * term * 12;
        
        // Update result cards
        updateResultCard('monthly-payment', monthlyPayment);
        updateResultCard('total-interest', totalInterest);
        updateResultCard('total-cost', totalCost);
        updateResultCard('loan-amount', loanAmount);
    }
    
    function calculateMonthlyPayment(principal, rate, years) {
        const monthlyRate = rate / 100 / 12;
        const numberOfPayments = years * 12;
        return principal * (monthlyRate * Math.pow(1 + monthlyRate, numberOfPayments)) / 
               (Math.pow(1 + monthlyRate, numberOfPayments) - 1);
    }
    
    function updateResultCard(id, value) {
        const card = document.querySelector(`[data-result="${id}"]`);
        if (card) {
            const valueElement = card.querySelector('.result-value');
            if (valueElement) {
                valueElement.textContent = formatCurrency(value);
            }
        }
    }
    
    // Initialize calculator
    updateCalculator();
}

// Contact Form
function initContactForm() {
    const contactForm = document.querySelector('.contact-form form');
    
    if (contactForm) {
        contactForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            // Get form data
            const formData = new FormData(contactForm);
            const data = Object.fromEntries(formData);
            
            // Validate form
            if (validateForm(data)) {
                // Show loading state
                const submitBtn = contactForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Sending...';
                submitBtn.disabled = true;
                
                // Simulate form submission
                setTimeout(() => {
                    showNotification('Message sent successfully!');
                    contactForm.reset();
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }, 2000);
            }
        });
    }
}

function validateForm(data) {
    const required = ['name', 'email', 'message'];
    const missing = required.filter(field => !data[field]);
    
    if (missing.length > 0) {
        showNotification(`Please fill in: ${missing.join(', ')}`);
        return false;
    }
    
    // Basic email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(data.email)) {
        showNotification('Please enter a valid email address');
        return false;
    }
    
    return true;
}

// Smooth Scrolling
function initSmoothScrolling() {
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

// Performance Optimizations
function initPerformanceOptimizations() {
    // Lazy load images
    const images = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
    
    // Debounce scroll events
    let scrollTimeout;
    window.addEventListener('scroll', () => {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            // Handle scroll-based animations
        }, 16);
    });
}

// Utility Functions
function showNotification(message) {
    const notification = document.createElement('div');
    notification.className = 'notification';
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--accent-gold);
        color: var(--primary-black);
        padding: var(--spacing-md);
        border-radius: 8px;
        z-index: 10000;
        animation: slideInRight 0.3s ease;
        box-shadow: 0 5px 15px rgba(255, 204, 0, 0.3);
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}

function triggerEntranceAnimations() {
    const elements = document.querySelectorAll('.animate-on-load');
    elements.forEach((el, index) => {
        el.style.animationDelay = `${index * 0.1}s`;
        el.classList.add('animate-in');
    });
}

// Mobile-optimized background effects
function initMobileBackgroundEffects() {
    // Simplified background effects for mobile
    const gridBackground = document.querySelector('.grid-background');
    if (gridBackground) {
        gridBackground.style.opacity = '0.1';
        gridBackground.style.animation = 'none';
    }
    
    // Hide particle system on mobile
    const particleSystem = document.querySelector('.particle-system');
    if (particleSystem) {
        particleSystem.style.display = 'none';
    }
    
    // Reduce orb opacity
    const orbs = document.querySelectorAll('.orb');
    orbs.forEach(orb => {
        orb.style.opacity = '0.2';
        orb.style.animation = 'none';
    });
}

// Mobile-optimized number counters
function initMobileNumberCounters() {
    const counters = document.querySelectorAll('[data-count]');
    
    const observerOptions = {
        threshold: 0.5,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const counter = entry.target;
                const target = parseInt(counter.getAttribute('data-count'));
                const duration = 2000; // Faster animation on mobile
                const step = target / (duration / 16);
                let current = 0;
                
                const updateCounter = () => {
                    current += step;
                    if (current < target) {
                        counter.textContent = Math.floor(current);
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.textContent = target;
                    }
                };
                
                updateCounter();
                observer.unobserve(counter);
            }
        });
    }, observerOptions);
    
    counters.forEach(counter => observer.observe(counter));
}

// Mobile-optimized effects
function initMobileEffects() {
    // Simplified hover effects for mobile
    const interactiveElements = document.querySelectorAll('.property-card, .tour-item, .land-filter');
    
    interactiveElements.forEach(element => {
        element.addEventListener('touchstart', () => {
            element.style.transform = 'scale(0.98)';
        });
        
        element.addEventListener('touchend', () => {
            element.style.transform = 'scale(1)';
        });
    });
    
    // Disable complex animations
    const animatedElements = document.querySelectorAll('.animate-on-load, .stagger-item');
    animatedElements.forEach(element => {
        element.style.animation = 'none';
        element.style.opacity = '1';
        element.style.transform = 'none';
    });
}

// Enhanced performance optimizations
function initPerformanceOptimizations() {
    // Lazy load images
    const images = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
    
    // Debounce scroll events
    let scrollTimeout;
    window.addEventListener('scroll', () => {
        if (scrollTimeout) {
            clearTimeout(scrollTimeout);
        }
        scrollTimeout = setTimeout(() => {
            // Handle scroll-based effects here
        }, 16); // ~60fps
    });
    
    // Optimize for mobile
    if (isMobile()) {
        // Reduce animation complexity
        document.body.style.setProperty('--transition-duration', '0.2s');
        
        // Disable heavy CSS effects
        const style = document.createElement('style');
        style.textContent = `
            @media (max-width: 768px) {
                * {
                    transition-duration: 0.2s !important;
                }
                .animate-on-load,
                .stagger-item {
                    animation: none !important;
                    opacity: 1 !important;
                    transform: none !important;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

// Mobile-specific navigation improvements
function initMobileNavigation() {
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
    const navLinks = document.querySelectorAll('.nav-link');
    
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            const isActive = navToggle.classList.contains('active');
            
            navToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
            
            // Prevent body scroll when menu is open
            document.body.style.overflow = isActive ? 'visible' : 'hidden';
            
            // Add entrance animation for menu items
            if (!isActive) {
                const menuItems = navMenu.querySelectorAll('.nav-item');
                menuItems.forEach((item, index) => {
                    item.style.animationDelay = `${index * 0.1}s`;
                    item.classList.add('menu-entrance');
                });
            }
        });
        
        // Close menu on link click
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                navToggle.classList.remove('active');
                navMenu.classList.remove('active');
                document.body.style.overflow = 'visible';
            });
        });
        
        // Close menu on outside click
        document.addEventListener('click', (e) => {
            if (!navToggle.contains(e.target) && !navMenu.contains(e.target)) {
                navToggle.classList.remove('active');
                navMenu.classList.remove('active');
                document.body.style.overflow = 'visible';
            }
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    initializeApp();
    
    // Initialize mobile-specific navigation
    if (isMobile()) {
        initMobileNavigation();
    }
});

// Handle window resize
window.addEventListener('resize', () => {
    // Re-initialize mobile detection on resize
    if (isMobile()) {
        document.body.classList.add('mobile-device');
    } else {
        document.body.classList.remove('mobile-device');
    }
});