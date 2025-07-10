/* ===================================
   NORTHWEST PROPERTY & LAND SALES
   Index Page JavaScript
   =================================== */

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Index page loaded - initializing animations');
    
    // Initialize all index page functionality
    initCustomCursor();
    initParticleSystem();
    initGlitchEffect();
    initKeyboardInteractions();
    initClickEffects();
    initGridAnimation();
    initAutoRedirect();
});

/* ===================================
   CUSTOM CURSOR
   =================================== */
function initCustomCursor() {
    const cursor = document.querySelector('.cursor');
    if (!cursor) return;
    
    let mouseX = 0;
    let mouseY = 0;
    let cursorX = 0;
    let cursorY = 0;
    
    document.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;
    });
    
    function animateCursor() {
        const dx = mouseX - cursorX;
        const dy = mouseY - cursorY;
        
        cursorX += dx * 0.1;
        cursorY += dy * 0.1;
        
        cursor.style.left = cursorX - 10 + 'px';
        cursor.style.top = cursorY - 10 + 'px';
        
        requestAnimationFrame(animateCursor);
    }
    animateCursor();
    
    // Hover effect for cursor
    document.addEventListener('mouseenter', () => {
        cursor.classList.add('hover');
    });
    
    document.addEventListener('mouseleave', () => {
        cursor.classList.remove('hover');
    });
}

/* ===================================
   PARTICLE SYSTEM
   =================================== */
function initParticleSystem() {
    const particlesContainer = document.getElementById('particles');
    if (!particlesContainer) return;
    
    function createParticle() {
        const particle = document.createElement('div');
        particle.className = 'particle';
        
        const size = Math.random() * 4 + 1;
        particle.style.width = size + 'px';
        particle.style.height = size + 'px';
        particle.style.left = Math.random() * window.innerWidth + 'px';
        particle.style.top = window.innerHeight + 'px';
        
        const duration = Math.random() * 3 + 3;
        particle.style.animationDuration = duration + 's';
        
        particlesContainer.appendChild(particle);
        
        setTimeout(() => {
            particle.remove();
        }, duration * 1000);
    }
    
    // Create particles periodically
    setInterval(createParticle, 300);
}

/* ===================================
   GLITCH EFFECT
   =================================== */
function initGlitchEffect() {
    const glitchElement = document.querySelector('.glitch');
    if (!glitchElement) return;
    
    setInterval(() => {
        if (Math.random() < 0.1) {
            glitchElement.style.animation = 'none';
            glitchElement.offsetHeight; // Trigger reflow
            glitchElement.style.animation = null;
        }
    }, 100);
}

/* ===================================
   KEYBOARD INTERACTIONS
   =================================== */
function initKeyboardInteractions() {
    document.addEventListener('keydown', (e) => {
        if (e.code === 'Space') {
            document.body.style.filter = 'invert(1)';
            setTimeout(() => {
                document.body.style.filter = 'none';
            }, 100);
        }
    });
}

/* ===================================
   CLICK EFFECTS
   =================================== */
function initClickEffects() {
    // Add ripple animation styles
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
    
    // Click effect
    document.addEventListener('click', (e) => {
        const ripple = document.createElement('div');
        ripple.style.position = 'absolute';
        ripple.style.borderRadius = '50%';
        ripple.style.background = 'rgba(255,255,255,0.3)';
        ripple.style.transform = 'scale(0)';
        ripple.style.animation = 'ripple 0.6s linear';
        ripple.style.left = (e.clientX - 50) + 'px';
        ripple.style.top = (e.clientY - 50) + 'px';
        ripple.style.width = '100px';
        ripple.style.height = '100px';
        ripple.style.pointerEvents = 'none';
        ripple.style.zIndex = '1000';
        
        document.body.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    });
    
    // Prevent right-click context menu for cleaner experience
    document.addEventListener('contextmenu', (e) => {
        e.preventDefault();
    });
}

/* ===================================
   GRID ANIMATION
   =================================== */
function initGridAnimation() {
    const grid = document.querySelector('.background-grid');
    if (!grid) return;
    
    setInterval(() => {
        const speed = Math.random() * 10 + 15;
        grid.style.animationDuration = speed + 's';
    }, 5000);
}

/* ===================================
   AUTO REDIRECT
   =================================== */
function initAutoRedirect() {
    // Manual redirect option
    setTimeout(() => {
        window.location.href = 'home.html';
    }, 3000);
}