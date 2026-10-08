  </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function openAdminSidebar() {
            document.querySelector('.sidebar')?.classList.add('show');
            document.getElementById('sidebarOverlay')?.classList.add('show');
            document.body.classList.add('sidebar-open');
        }
        function closeAdminSidebar() {
            document.querySelector('.sidebar')?.classList.remove('show');
            document.getElementById('sidebarOverlay')?.classList.remove('show');
            document.body.classList.remove('sidebar-open');
        }
        document.getElementById('sidebarToggle')?.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const sidebar = document.querySelector('.sidebar');
            if (sidebar?.classList.contains('show')) {
                closeAdminSidebar();
            } else {
                openAdminSidebar();
            }
        });
        document.getElementById('sidebarClose')?.addEventListener('click', function(e) {
            e.preventDefault();
            closeAdminSidebar();
        });
        document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
            closeAdminSidebar();
        });
        document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    closeAdminSidebar();
                }
            });
        });
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAdminSidebar();
            }
        });
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (alert.classList.contains('alert-success') || alert.classList.contains('alert-info')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);
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
        $(document).ajaxError(function(event, xhr, settings, error) {
            if (xhr.status === 401) {
                window.location.href = '<?php echo APP_URL; ?>/login.php';
            } else if (xhr.status === 403) {
                alert('Access denied. You do not have permission to perform this action.');
            } else if (xhr.status >= 500) {
                alert('Server error. Please try again later.');
            }
        });
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
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        }
        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString();
        }
        function showLoading(elementId) {
            const element = document.getElementById(elementId);
            if (element) {
                element.innerHTML = '<div class="loading"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            }
        }
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
        function confirmDelete(message = 'Are you sure you want to delete this item?') {
            return confirm(message);
        }
        document.querySelectorAll('[data-lang]').forEach(function(element) {
            element.addEventListener('click', function(e) {
                e.preventDefault();
                const lang = this.getAttribute('data-lang');
                const langText = this.textContent.trim();
                const currentLangElement = document.querySelector('.current-language');
                if (currentLangElement) {
                    currentLangElement.textContent = lang.toUpperCase();
                }
                localStorage.setItem('preferred_language', lang);
                showToast('Language changed to ' + langText, 'info');
            });
        });
        document.addEventListener('DOMContentLoaded', function() {
            const savedLang = localStorage.getItem('preferred_language');
            if (savedLang) {
                const langElement = document.querySelector(`[data-lang="${savedLang}"]`);
                const currentLangElement = document.querySelector('.current-language');
                if (langElement && currentLangElement) {
                    currentLangElement.textContent = savedLang.toUpperCase();
                }
            }
        });
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
        function setupPhoneValidation(phoneInputId, countryPrefix = '+91') {
            const phoneInput = document.getElementById(phoneInputId);
            if (!phoneInput) return;
            let initialValue = phoneInput.value || '';
            if (initialValue.startsWith(countryPrefix)) {
                initialValue = initialValue.replace(countryPrefix, '').trim();
            }
            initialValue = initialValue.replace(/[^0-9]/g, '').slice(0, 10);
            phoneInput.value = initialValue;
            phoneInput.placeholder = phoneInput.placeholder || `${countryPrefix} 10-digit mobile number`;
            phoneInput.maxLength = 10;
            phoneInput.setAttribute('pattern', '[0-9]{10}');
            
            phoneInput.addEventListener('input', function(e) {
                let value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
                this.value = value;
            });
            phoneInput.addEventListener('keypress', function(e) {
                const char = String.fromCharCode(e.which);
                if (!/[0-9]/.test(char)) {
                    e.preventDefault();
                }
            });
            phoneInput.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                let value = pastedText.replace(/[^0-9]/g, '').slice(0, 10);
                this.value = value;
            });
            phoneInput.addEventListener('blur', function() {
                let value = this.value.replace(/[^0-9]/g, '');
                if (value.length === 10) {
                    this.setAttribute('data-phone-full', countryPrefix + value);
                } else {
                    this.setAttribute('data-phone-full', '');
                }
            });
            phoneInput.addEventListener('focus', function() {
                let value = this.value;
                if (value.startsWith(countryPrefix)) {
                    this.value = value.replace(countryPrefix, '').trim();
                }
            });
            return phoneInput;
        }
        function getPhoneWithPrefix(phoneInputId, countryPrefix = '+91') {
            const phoneInput = document.getElementById(phoneInputId);
            if (!phoneInput) return '';
            
            let value = phoneInput.value.replace(/[^0-9]/g, '');
            if (value.length === 10) {
                return countryPrefix + value;
            }
            return phoneInput.getAttribute('data-phone-full') || value || '';
        }
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
        document.addEventListener('DOMContentLoaded', function() {
            makeTableResponsive();
            if (!document.querySelector('meta[name="csrf-token"]')) {
                const meta = document.createElement('meta');
                meta.name = 'csrf-token';
                meta.content = '<?php echo generate_csrf_token(); ?>';
                document.head.appendChild(meta);
            }
        });
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.querySelector('.sidebar').classList.remove('show');
            }
        });
        if (typeof window !== 'undefined') {
            window.dataLayer = window.dataLayer || [];
            if (typeof window.gtag === 'undefined') {
                window.gtag = function() {
                    window.dataLayer = window.dataLayer || [];
                    window.dataLayer.push(arguments);
                };
            }
        }
        <?php if (isset($_SESSION['ga4_event']) && !empty($_SESSION['ga4_event'])): ?>
        (function() {
            var attempts = 0;
            var maxAttempts = 50; 
            function fireGA4Event() {
                var gtagAvailable = (typeof window !== 'undefined' && typeof window.gtag !== 'undefined') || (typeof gtag !== 'undefined');
                if (gtagAvailable && typeof window !== 'undefined' && typeof window.dataLayer !== 'undefined') {
                    try {
                        <?php echo $_SESSION['ga4_event']; ?>
                        console.log('GA4 event fired successfully');
                    } catch (e) {
                        console.error('GA4 event tracking error:', e);
                    }
                } else {
                    attempts++;
                    if (attempts < maxAttempts) {
                        setTimeout(fireGA4Event, 100);
                    } else {
                        console.warn('GA4 not loaded after 5 seconds, event may be lost');
                    }
                }
            }
                        setTimeout(function() {
                fireGA4Event();
            }, 100);
        })();
        <?php 
        unset($_SESSION['ga4_event']);
        endif; 
        ?>
    </script>
    <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>
</body>
</html>