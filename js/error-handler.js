/**
 * Northwest Property & Land Sales - Error Handling & Logging
 * Comprehensive error handling and logging system
 * 
 * @version 1.0.0
 * @author JavaScript Agent
 * @date 2024
 */

// Error types for categorization
const ErrorTypes = {
    NETWORK: 'network',
    VALIDATION: 'validation',
    DOM: 'dom',
    ANALYTICS: 'analytics',
    FORM: 'form',
    ANIMATION: 'animation',
    PERFORMANCE: 'performance',
    UNKNOWN: 'unknown'
};

// Error severity levels
const ErrorSeverity = {
    LOW: 'low',
    MEDIUM: 'medium',
    HIGH: 'high',
    CRITICAL: 'critical'
};

// Error handler class
class ErrorHandler {
    constructor() {
        this.errors = [];
        this.maxErrors = 100; // Prevent memory leaks
        this.isInitialized = false;
        this.errorCount = 0;
    }

    /**
     * Initialize error handling
     */
    init() {
        if (this.isInitialized) return;

        // Set up global error handlers
        this.setupGlobalHandlers();
        
        // Set up unhandled promise rejection handler
        this.setupPromiseRejectionHandler();
        
        // Set up performance monitoring
        this.setupPerformanceMonitoring();
        
        this.isInitialized = true;
        console.log('[ErrorHandler] Error handling initialized');
    }

    /**
     * Set up global error handlers
     */
    setupGlobalHandlers() {
        // Handle JavaScript errors
        window.addEventListener('error', (event) => {
            this.handleError({
                type: ErrorTypes.UNKNOWN,
                severity: ErrorSeverity.HIGH,
                message: event.message,
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno,
                error: event.error,
                stack: event.error?.stack
            });
        });

        // Handle resource loading errors
        window.addEventListener('error', (event) => {
            if (event.target !== window) {
                this.handleError({
                    type: ErrorTypes.NETWORK,
                    severity: ErrorSeverity.MEDIUM,
                    message: `Failed to load resource: ${event.target.src || event.target.href}`,
                    element: event.target,
                    resourceType: event.target.tagName.toLowerCase()
                });
            }
        }, true);
    }

    /**
     * Set up promise rejection handler
     */
    setupPromiseRejectionHandler() {
        window.addEventListener('unhandledrejection', (event) => {
            this.handleError({
                type: ErrorTypes.NETWORK,
                severity: ErrorSeverity.HIGH,
                message: 'Unhandled promise rejection',
                reason: event.reason,
                promise: event.promise
            });
        });
    }

    /**
     * Set up performance monitoring
     */
    setupPerformanceMonitoring() {
        if ('performance' in window && 'getEntriesByType' in performance) {
            // Monitor for slow resources
            const observer = new PerformanceObserver((list) => {
                list.getEntries().forEach((entry) => {
                    if (entry.duration > 3000) { // 3 seconds threshold
                        this.handleError({
                            type: ErrorTypes.PERFORMANCE,
                            severity: ErrorSeverity.MEDIUM,
                            message: 'Slow resource detected',
                            duration: entry.duration,
                            name: entry.name,
                            entryType: entry.entryType
                        });
                    }
                });
            });

            try {
                observer.observe({ entryTypes: ['resource', 'navigation'] });
            } catch (error) {
                console.warn('[ErrorHandler] PerformanceObserver not supported');
            }
        }
    }

    /**
     * Handle an error
     * @param {Object} errorInfo - Error information
     */
    handleError(errorInfo) {
        const error = {
            id: ++this.errorCount,
            timestamp: new Date().toISOString(),
            type: errorInfo.type || ErrorTypes.UNKNOWN,
            severity: errorInfo.severity || ErrorSeverity.MEDIUM,
            message: errorInfo.message || 'Unknown error',
            url: window.location.href,
            userAgent: navigator.userAgent,
            ...errorInfo
        };

        // Add to errors array
        this.errors.push(error);
        
        // Prevent memory leaks
        if (this.errors.length > this.maxErrors) {
            this.errors.shift();
        }

        // Log error
        this.logError(error);

        // Send to analytics if critical
        if (error.severity === ErrorSeverity.CRITICAL) {
            this.sendToAnalytics(error);
        }

        // Show user notification for critical errors
        if (error.severity === ErrorSeverity.CRITICAL) {
            this.showUserNotification(error);
        }
    }

    /**
     * Log error to console
     * @param {Object} error - Error object
     */
    logError(error) {
        const logMessage = `[${error.type.toUpperCase()}] ${error.message}`;
        
        switch (error.severity) {
            case ErrorSeverity.LOW:
                console.log(logMessage, error);
                break;
            case ErrorSeverity.MEDIUM:
                console.warn(logMessage, error);
                break;
            case ErrorSeverity.HIGH:
            case ErrorSeverity.CRITICAL:
                console.error(logMessage, error);
                break;
        }
    }

    /**
     * Send error to analytics
     * @param {Object} error - Error object
     */
    sendToAnalytics(error) {
        if (typeof gtag !== 'undefined') {
            gtag('event', 'exception', {
                description: error.message,
                fatal: error.severity === ErrorSeverity.CRITICAL,
                custom_map: {
                    error_type: error.type,
                    error_severity: error.severity,
                    error_url: error.url
                }
            });
        }
    }

    /**
     * Show user notification for critical errors
     * @param {Object} error - Error object
     */
    showUserNotification(error) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'error-notification';
        notification.innerHTML = `
            <div class="error-notification-content">
                <h4>We're experiencing technical difficulties</h4>
                <p>Please refresh the page or contact us if the problem persists.</p>
                <button onclick="this.parentElement.parentElement.remove()">Dismiss</button>
            </div>
        `;

        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #ff4444;
            color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 10000;
            max-width: 300px;
            font-family: Arial, sans-serif;
        `;

        // Add to page
        document.body.appendChild(notification);

        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 10000);
    }

    /**
     * Get error statistics
     * @returns {Object} Error statistics
     */
    getErrorStats() {
        const stats = {
            total: this.errors.length,
            byType: {},
            bySeverity: {},
            recent: this.errors.slice(-10) // Last 10 errors
        };

        this.errors.forEach(error => {
            // Count by type
            stats.byType[error.type] = (stats.byType[error.type] || 0) + 1;
            
            // Count by severity
            stats.bySeverity[error.severity] = (stats.bySeverity[error.severity] || 0) + 1;
        });

        return stats;
    }

    /**
     * Clear error log
     */
    clearErrors() {
        this.errors = [];
        this.errorCount = 0;
        console.log('[ErrorHandler] Error log cleared');
    }

    /**
     * Export errors for debugging
     * @returns {string} JSON string of errors
     */
    exportErrors() {
        return JSON.stringify({
            timestamp: new Date().toISOString(),
            stats: this.getErrorStats(),
            errors: this.errors
        }, null, 2);
    }
}

// Utility functions for common error scenarios
const ErrorUtils = {
    /**
     * Handle network errors
     * @param {Error} error - Network error
     * @param {string} context - Context where error occurred
     */
    handleNetworkError(error, context = '') {
        ErrorHandlerInstance.handleError({
            type: ErrorTypes.NETWORK,
            severity: ErrorSeverity.MEDIUM,
            message: `Network error in ${context}: ${error.message}`,
            context,
            originalError: error
        });
    },

    /**
     * Handle validation errors
     * @param {string} message - Validation message
     * @param {*} value - Invalid value
     * @param {string} field - Field name
     */
    handleValidationError(message, value, field = '') {
        ErrorHandlerInstance.handleError({
            type: ErrorTypes.VALIDATION,
            severity: ErrorSeverity.LOW,
            message: `Validation error: ${message}`,
            field,
            value: String(value).substring(0, 100) // Truncate long values
        });
    },

    /**
     * Handle DOM errors
     * @param {string} message - Error message
     * @param {Element} element - Related DOM element
     */
    handleDOMError(message, element = null) {
        ErrorHandlerInstance.handleError({
            type: ErrorTypes.DOM,
            severity: ErrorSeverity.MEDIUM,
            message: `DOM error: ${message}`,
            element: element ? element.tagName : null,
            selector: element ? this.getElementSelector(element) : null
        });
    },

    /**
     * Handle form errors
     * @param {string} message - Error message
     * @param {HTMLFormElement} form - Form element
     */
    handleFormError(message, form = null) {
        ErrorHandlerInstance.handleError({
            type: ErrorTypes.FORM,
            severity: ErrorSeverity.MEDIUM,
            message: `Form error: ${message}`,
            formId: form ? form.id : null,
            formAction: form ? form.action : null
        });
    },

    /**
     * Handle analytics errors
     * @param {string} message - Error message
     * @param {string} eventType - Analytics event type
     */
    handleAnalyticsError(message, eventType = '') {
        ErrorHandlerInstance.handleError({
            type: ErrorTypes.ANALYTICS,
            severity: ErrorSeverity.LOW,
            message: `Analytics error: ${message}`,
            eventType
        });
    },

    /**
     * Get element selector for debugging
     * @param {Element} element - DOM element
     * @returns {string} CSS selector
     */
    getElementSelector(element) {
        if (!element) return '';
        
        if (element.id) {
            return `#${element.id}`;
        }
        
        if (element.className) {
            return `.${element.className.split(' ').join('.')}`;
        }
        
        return element.tagName.toLowerCase();
    },

    /**
     * Safe function execution with error handling
     * @param {Function} fn - Function to execute
     * @param {string} context - Context for error reporting
     * @param {*} defaultValue - Default value if function fails
     * @returns {*} Function result or default value
     */
    safeExecute(fn, context = '', defaultValue = null) {
        try {
            return fn();
        } catch (error) {
            ErrorHandlerInstance.handleError({
                type: ErrorTypes.UNKNOWN,
                severity: ErrorSeverity.MEDIUM,
                message: `Error in ${context}: ${error.message}`,
                context,
                originalError: error
            });
            return defaultValue;
        }
    },

    /**
     * Safe async function execution with error handling
     * @param {Function} fn - Async function to execute
     * @param {string} context - Context for error reporting
     * @param {*} defaultValue - Default value if function fails
     * @returns {Promise} Promise that resolves to function result or default value
     */
    async safeExecuteAsync(fn, context = '', defaultValue = null) {
        try {
            return await fn();
        } catch (error) {
            ErrorHandlerInstance.handleError({
                type: ErrorTypes.UNKNOWN,
                severity: ErrorSeverity.MEDIUM,
                message: `Async error in ${context}: ${error.message}`,
                context,
                originalError: error
            });
            return defaultValue;
        }
    }
};

// Create global error handler instance
const ErrorHandlerInstance = new ErrorHandler();

// Initialize error handling when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    ErrorHandlerInstance.init();
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        ErrorHandler,
        ErrorHandlerInstance,
        ErrorUtils,
        ErrorTypes,
        ErrorSeverity
    };
}