        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.getElementById('sidebarToggle');
            
            if (window.innerWidth <= 768 && 
                sidebar.classList.contains('show') && 
                !sidebar.contains(event.target) && 
                !toggle.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        });

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (alert.classList.contains('alert-success') || alert.classList.contains('alert-info')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);

        // CSRF Token for AJAX requests
        $.ajaxSetup({
            beforeSend: function(xhr, settings) {
                if (settings.type === 'POST' && !this.crossDomain) {
                    const csrfToken = $('meta[name="csrf-token"]').attr('content');
                    if (csrfToken) {
                        xhr.setRequestHeader('X-CSRF-Token', csrfToken);
                    }
                }
            }
        });

        // Global AJAX error handler
        $(document).ajaxError(function(event, xhr, settings, error) {
            if (xhr.status === 401) {
                window.location.href = '<?php echo APP_URL; ?>/login.php';
            } else if (xhr.status === 403) {
                alert('Access denied. You do not have permission to perform this action.');
            } else if (xhr.status >= 500) {
                alert('Server error. Please try again later.');
            }
        });

        // Form validation
        function validateForm(formId) {
            const form = document.getElementById(formId);
            if (!form) return false;

            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;

            inputs.forEach(function(input) {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            return isValid;
        }

        // Format currency
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        }

        // Format date
        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString();
        }

        // Show loading spinner
        function showLoading(elementId) {
            const element = document.getElementById(elementId);
            if (element) {
                element.innerHTML = '<div class="loading"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            }
        }

        // Show toast notification
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toast-container') || createToastContainer();
            
            const toastId = 'toast-' + Date.now();
            const toastHtml = `
                <div class="toast" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header">
                        <i class="fas fa-${type === 'success' ? 'check-circle text-success' : type === 'error' ? 'exclamation-triangle text-danger' : 'info-circle text-info'} me-2"></i>
                        <strong class="me-auto">Notification</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
            
            // Remove toast element after it's hidden
            toastElement.addEventListener('hidden.bs.toast', function() {
                this.remove();
            });
        }

        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
            return container;
        }

        // Confirm delete action
        function confirmDelete(message = 'Are you sure you want to delete this item?') {
            return confirm(message);
        }

        // Language switcher - updated to work with the new language switcher component
        document.querySelectorAll('[data-lang]').forEach(function(element) {
            element.addEventListener('click', function(e) {
                e.preventDefault();
                const lang = this.getAttribute('data-lang');
                const langText = this.textContent.trim();
                
                // Update current language display in the language switcher
                const currentLangElement = document.querySelector('.current-language');
                if (currentLangElement) {
                    // Simply update the text content - let the language switcher handle flags
                    currentLangElement.textContent = lang.toUpperCase();
                }
                
                // Store language preference
                localStorage.setItem('preferred_language', lang);
                
                // You can implement actual language switching logic here
                showToast('Language changed to ' + langText, 'info');
            });
        });

        // Load saved language preference
        document.addEventListener('DOMContentLoaded', function() {
            const savedLang = localStorage.getItem('preferred_language');
            if (savedLang) {
                const langElement = document.querySelector(`[data-lang="${savedLang}"]`);
                const currentLangElement = document.querySelector('.current-language');
                if (langElement && currentLangElement) {
                    // Simply update the text content - let the language switcher handle flags
                    currentLangElement.textContent = savedLang.toUpperCase();
                }
            }
        });

        // Responsive table handling
        function makeTableResponsive() {
            const tables = document.querySelectorAll('.table');
            tables.forEach(function(table) {
                if (!table.closest('.table-responsive')) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'table-responsive';
                    table.parentNode.insertBefore(wrapper, table);
                    wrapper.appendChild(table);
                }
            });
        }

        // Phone Number Validation Function (India: +91 prefix, 10 digits)
        function setupPhoneValidation(phoneInputId, countryPrefix = '+91') {
            const phoneInput = document.getElementById(phoneInputId);
            if (!phoneInput) return;
            
            // Remove country prefix from value if present
            let initialValue = phoneInput.value || '';
            if (initialValue.startsWith(countryPrefix)) {
                initialValue = initialValue.replace(countryPrefix, '').trim();
            }
            // Remove any non-digit characters
            initialValue = initialValue.replace(/[^0-9]/g, '').slice(0, 10);
            phoneInput.value = initialValue;
            
            // Set placeholder with country code
            phoneInput.placeholder = phoneInput.placeholder || `${countryPrefix} 10-digit mobile number`;
            phoneInput.maxLength = 10;
            phoneInput.setAttribute('pattern', '[0-9]{10}');
            
            // Only allow digits on input
            phoneInput.addEventListener('input', function(e) {
                let value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
                this.value = value;
            });
            
            // Prevent non-digit characters on keypress
            phoneInput.addEventListener('keypress', function(e) {
                const char = String.fromCharCode(e.which);
                if (!/[0-9]/.test(char)) {
                    e.preventDefault();
                }
            });
            
            // Handle paste event
            phoneInput.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                let value = pastedText.replace(/[^0-9]/g, '').slice(0, 10);
                this.value = value;
            });
            
            // Add country prefix on blur if value exists
            phoneInput.addEventListener('blur', function() {
                let value = this.value.replace(/[^0-9]/g, '');
                if (value.length === 10) {
                    // Store with prefix for form submission
                    this.setAttribute('data-phone-full', countryPrefix + value);
                } else {
                    this.setAttribute('data-phone-full', '');
                }
            });
            
            // Format with prefix for display (optional visual feedback)
            phoneInput.addEventListener('focus', function() {
                let value = this.value;
                if (value.startsWith(countryPrefix)) {
                    this.value = value.replace(countryPrefix, '').trim();
                }
            });
            
            return phoneInput;
        }
        
        // Function to get phone value with prefix for form submission
        function getPhoneWithPrefix(phoneInputId, countryPrefix = '+91') {
            const phoneInput = document.getElementById(phoneInputId);
            if (!phoneInput) return '';
            
            let value = phoneInput.value.replace(/[^0-9]/g, '');
            if (value.length === 10) {
                return countryPrefix + value;
            }
            return phoneInput.getAttribute('data-phone-full') || value || '';
        }
        
        // Function to validate phone number
        function validatePhoneNumber(phoneInputId, countryPrefix = '+91') {
            const phoneInput = document.getElementById(phoneInputId);
            if (!phoneInput) return false;
            
            let value = phoneInput.value.replace(/[^0-9]/g, '');
            
            if (value.length !== 10) {
                phoneInput.classList.add('is-invalid');
                return false;
            }
            
            phoneInput.classList.remove('is-invalid');
            return true;
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            makeTableResponsive();
            
            // Add CSRF token to meta tag if not exists
            if (!document.querySelector('meta[name="csrf-token"]')) {
                const meta = document.createElement('meta');
                meta.name = 'csrf-token';
                meta.content = '<?php echo generate_csrf_token(); ?>';
                document.head.appendChild(meta);
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.querySelector('.sidebar').classList.remove('show');
            }
        });
        
        // Ensure gtag is always available (safety fallback)
        // This runs immediately to ensure gtag exists even if GA4 script hasn't loaded
        if (typeof window !== 'undefined') {
            // Initialize dataLayer if it doesn't exist
            window.dataLayer = window.dataLayer || [];
            
            // Create gtag function if it doesn't exist
            if (typeof window.gtag === 'undefined') {
                window.gtag = function() {
                    window.dataLayer = window.dataLayer || [];
                    window.dataLayer.push(arguments);
                };
            }
        }
        
        // Fire GA4 events stored in session (wait for gtag to be available)
        <?php if (isset($_SESSION['ga4_event']) && !empty($_SESSION['ga4_event'])): ?>
        (function() {
            var attempts = 0;
            var maxAttempts = 50; // 5 seconds max wait time
            
            function fireGA4Event() {
                // Check if gtag is available (use window.gtag or global gtag)
                var gtagAvailable = (typeof window !== 'undefined' && typeof window.gtag !== 'undefined') || (typeof gtag !== 'undefined');
                
                if (gtagAvailable && typeof window !== 'undefined' && typeof window.dataLayer !== 'undefined') {
                    try {
                        // Execute the event code - gtag should be available now
                        <?php echo $_SESSION['ga4_event']; ?>
                        console.log('GA4 event fired successfully');
                    } catch (e) {
                        console.error('GA4 event tracking error:', e);
                        // Don't break the page if GA4 fails
                    }
                } else {
                    // Retry after 100ms if gtag is not ready
                    attempts++;
                    if (attempts < maxAttempts) {
                        setTimeout(fireGA4Event, 100);
                    } else {
                        console.warn('GA4 not loaded after 5 seconds, event may be lost');
                    }
                }
            }
            
            // Start trying to fire the event after a small delay to ensure scripts are loaded
            setTimeout(function() {
                fireGA4Event();
            }, 100);
        })();
        <?php 
        unset($_SESSION['ga4_event']); // Clear after firing
        endif; 
        ?>
    </script>

    <!-- Toast Container -->
    <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

</body>
</html>

