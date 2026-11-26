  </main>
    </div>
    <nav class="mobile-bottom-nav d-lg-none" aria-label="Mobile Bottom Navigation">
        <?php $currentScript = basename($_SERVER['PHP_SELF']); ?>
        <a href="dashboard.php" class="mobile-bottom-nav-item <?php echo ($currentScript === 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="orders.php" class="mobile-bottom-nav-item <?php echo ($currentScript === 'orders.php' || $currentScript === 'order-details.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            <span>Orders</span>
        </a>
        <a href="customers.php" class="mobile-bottom-nav-item <?php echo ($currentScript === 'customers.php') ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Customers</span>
        </a>
        <a href="expenses.php" class="mobile-bottom-nav-item <?php echo ($currentScript === 'expenses.php') ? 'active' : ''; ?>">
            <i class="fas fa-receipt"></i>
            <span>Expenses</span>
        </a>
        <button type="button" class="mobile-bottom-nav-item <?php echo ($currentScript === 'categories.php') ? 'active' : ''; ?>" onclick="openAdminSidebar(); return false;" aria-label="More Menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" class="mb-1" style="display: block;">
                <circle cx="6" cy="6" r="3.2"/>
                <circle cx="18" cy="6" r="3.2"/>
                <circle cx="6" cy="18" r="3.2"/>
                <circle cx="18" cy="18" r="3.2"/>
            </svg>
            <span>More</span>
        </button>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function openAdminSidebar() {
            document.querySelector('.sidebar')?.classList.add('show');
            document.getElementById('sidebarOverlay')?.classList.add('show');
            document.documentElement.classList.add('sidebar-open');
            document.body.classList.add('sidebar-open');
        }
        function closeAdminSidebar() {
            document.querySelector('.sidebar')?.classList.remove('show');
            document.getElementById('sidebarOverlay')?.classList.remove('show');
            document.documentElement.classList.remove('sidebar-open');
            document.body.classList.remove('sidebar-open');
        }
        (function() {
            function isMobileSidebarOpen() {
                return document.body.classList.contains('sidebar-open') || Boolean(document.querySelector('.sidebar.show'));
            }
            document.addEventListener('wheel', function(e) {
                if (!isMobileSidebarOpen()) return;
                const sidebar = e.target.closest ? e.target.closest('.sidebar') : null;
                if (!sidebar) {
                    e.preventDefault();
                    return;
                }
                const atTop = sidebar.scrollTop <= 0 && e.deltaY < 0;
                const atBottom = sidebar.scrollTop + sidebar.clientHeight >= sidebar.scrollHeight - 1 && e.deltaY > 0;
                if (sidebar.scrollHeight <= sidebar.clientHeight || atTop || atBottom) {
                    e.preventDefault();
                }
            }, { passive: false });
            document.addEventListener('touchmove', function(e) {
                if (!isMobileSidebarOpen()) return;
                const sidebar = e.target.closest ? e.target.closest('.sidebar') : null;
                if (!sidebar || sidebar.scrollHeight <= sidebar.clientHeight) {
                    e.preventDefault();
                }
            }, { passive: false });
        })();
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
        function normalizeLocalPhoneDigits(raw, countryPrefix = '+91') {
            let val = (raw || '').toString().trim();
            const prefixDigits = countryPrefix.replace(/[^0-9]/g, '');
            while (val.startsWith(countryPrefix)) {
                val = val.slice(countryPrefix.length).trim();
            }
            let digits = val.replace(/[^0-9]/g, '');
            if (prefixDigits && digits.length > 10 && digits.startsWith(prefixDigits)) {
                digits = digits.slice(prefixDigits.length);
            }
            return digits.slice(0, 10);
        }
        function setupPhoneValidation(phoneInputId, countryPrefix = '+91') {
            const phoneInput = document.getElementById(phoneInputId);
            if (!phoneInput) return;
            const syncDigits = (raw) => {
                const cleaned = normalizeLocalPhoneDigits(raw, countryPrefix);
                if (phoneInput.value !== cleaned) {
                    phoneInput.value = cleaned;
                }
                return cleaned;
            };
            syncDigits(phoneInput.value);
            phoneInput.placeholder = phoneInput.placeholder || `${countryPrefix} 10-digit mobile number`;
            phoneInput.maxLength = 10;
            phoneInput.setAttribute('pattern', '[0-9]{10}');
            
            phoneInput.addEventListener('input', function() {
                syncDigits(this.value);
            });
            phoneInput.addEventListener('change', function() {
                syncDigits(this.value);
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
                syncDigits(pastedText);
            });
            phoneInput.addEventListener('blur', function() {
                const value = syncDigits(this.value);
                if (value.length === 10) {
                    this.setAttribute('data-phone-full', countryPrefix + value);
                } else {
                    this.setAttribute('data-phone-full', '');
                }
            });
            phoneInput.addEventListener('focus', function() {
                syncDigits(this.value);
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
            if (window.innerWidth > 991.98) {
                closeAdminSidebar();
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
        (function() {
            function isControlVisible(el) {
                if (!el || el.disabled) return false;
                if (el.type === 'hidden' || el.type === 'submit' || el.type === 'button' || el.type === 'reset') return false;
                return el.getClientRects().length > 0;
            }

            function getFieldLabelText(form, el) {
                let labelEl = null;
                if (el.id) {
                    try {
                        labelEl = form.querySelector('label[for="' + CSS.escape(el.id) + '"]');
                    } catch (e) {}
                }
                if (!labelEl) {
                    const group = el.closest('.mb-3, .mb-2, .mb-1, .form-group, [class*="col-"]');
                    if (group) {
                        labelEl = group.querySelector('label');
                    }
                }
                if (!labelEl) return '';
                return labelEl.textContent.replace(/[*:\r\n]+/g, ' ').replace(/\s+/g, ' ').trim().toLowerCase();
            }

            function getInlineValidationMessage(form, el) {
                const label = getFieldLabelText(form, el);
                const rawEmpty = el.type !== 'checkbox' && el.type !== 'radio' && el.type !== 'file' && el.required && el.value.trim() === '';
                if (el.validity.valueMissing || rawEmpty) {
                    if (el.tagName === 'SELECT') {
                        return label ? 'Please select ' + label + '.' : 'Please select an option.';
                    }
                    if (el.type === 'checkbox' || el.type === 'radio') {
                        return 'This field is required.';
                    }
                    if (el.type === 'file') {
                        return label ? 'Please choose ' + label + '.' : 'Please choose a file.';
                    }
                    return label ? 'Please enter ' + label + '.' : 'Please fill out this field.';
                }
                if (el.validity.patternMismatch) {
                    if (el.title) return el.title;
                    if (el.pattern === '[0-9]{10}') return 'Please enter a valid 10-digit phone number.';
                }
                return el.validationMessage || 'Please enter a valid value.';
            }

            function ensureFeedbackElement(el) {
                const inputGroup = el.closest('.input-group');
                if (inputGroup) {
                    const igParent = inputGroup.parentElement;
                    const igParentStyle = igParent ? window.getComputedStyle(igParent) : null;
                    const isCompactSideLabelRow = igParentStyle && igParentStyle.display.includes('flex') && igParentStyle.flexDirection.startsWith('row') && igParent.querySelector('label');
                    if (isCompactSideLabelRow && igParent.parentElement) {
                        let wrap = igParent.parentElement.querySelector(':scope > .inline-group-feedback');
                        if (!wrap) {
                            wrap = document.createElement('div');
                            wrap.className = 'invalid-feedback inline-group-feedback';
                            wrap.dataset.autoFeedback = '1';
                            igParent.insertAdjacentElement('afterend', wrap);
                        }
                        return wrap;
                    }
                    inputGroup.classList.add('has-validation');
                    let fb = inputGroup.querySelector(':scope > .invalid-feedback');
                    if (!fb) {
                        fb = document.createElement('div');
                        fb.className = 'invalid-feedback';
                        fb.dataset.autoFeedback = '1';
                        inputGroup.appendChild(fb);
                    }
                    return fb;
                }
                const formCheck = el.closest('.form-check');
                if (formCheck) {
                    let fb = formCheck.querySelector(':scope > .invalid-feedback');
                    if (!fb) {
                        fb = document.createElement('div');
                        fb.className = 'invalid-feedback';
                        fb.dataset.autoFeedback = '1';
                        formCheck.appendChild(fb);
                    }
                    return fb;
                }
                const next = el.nextElementSibling;
                if (next && next.classList.contains('invalid-feedback')) {
                    return next;
                }
                const parent = el.parentElement;
                if (parent && !parent.classList.contains('inline-field-wrap')) {
                    const pStyle = window.getComputedStyle(parent);
                    const isHorizontalLayout = pStyle.display.includes('grid') || (pStyle.display.includes('flex') && pStyle.flexDirection.startsWith('row'));
                    if (isHorizontalLayout) {
                        const wrap = document.createElement('div');
                        wrap.className = 'inline-field-wrap';
                        wrap.style.display = 'flex';
                        wrap.style.flexDirection = 'column';
                        wrap.style.flex = '1 1 auto';
                        wrap.style.minWidth = '0';
                        wrap.style.width = '100%';
                        parent.insertBefore(wrap, el);
                        wrap.appendChild(el);
                    }
                }
                const fb = document.createElement('div');
                fb.className = 'invalid-feedback';
                fb.dataset.autoFeedback = '1';
                el.insertAdjacentElement('afterend', fb);
                return fb;
            }

            function isControlValid(el) {
                const emptyText = el.type !== 'checkbox' && el.type !== 'radio' && el.type !== 'file' && el.required && el.value.trim() === '';
                return !emptyText && el.checkValidity();
            }

            function enableInlineFormValidation() {
                document.querySelectorAll('form').forEach(function(form) {
                    if (form.hasAttribute('novalidate') || form.id === 'measurementForm') return;
                    form.noValidate = true;
                    form.dataset.inlineValidated = '1';
                });
            }

            document.addEventListener('DOMContentLoaded', enableInlineFormValidation);

            document.addEventListener('submit', function(e) {
                const form = e.target;
                if (!form || form.tagName !== 'FORM') return;
                if (form.dataset.inlineValidated !== '1') return;

                const controls = form.querySelectorAll('input, select, textarea');
                let firstInvalid = null;

                controls.forEach(function(el) {
                    if (!isControlVisible(el)) return;
                    if (!isControlValid(el)) {
                        const fb = ensureFeedbackElement(el);
                        if (fb && (fb.dataset.autoFeedback === '1' || !fb.textContent.trim())) {
                            fb.textContent = getInlineValidationMessage(form, el);
                        }
                        el.classList.add('is-invalid');
                        if (fb && fb.classList.contains('inline-group-feedback')) {
                            fb.classList.add('d-block');
                        }
                        if (!firstInvalid) firstInvalid = el;
                    } else {
                        el.classList.remove('is-invalid');
                        const groupWrap = el.closest('.input-group')?.parentElement?.parentElement?.querySelector(':scope > .inline-group-feedback');
                        if (groupWrap) groupWrap.classList.remove('d-block');
                    }
                });

                if (firstInvalid) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    firstInvalid.focus();
                    return false;
                }
            }, true);

            ['input', 'change'].forEach(function(evtName) {
                document.addEventListener(evtName, function(e) {
                    const el = e.target;
                    if (!el || !el.form || el.form.dataset.inlineValidated !== '1') return;
                    if (el.classList.contains('is-invalid') && isControlValid(el)) {
                        el.classList.remove('is-invalid');
                        const groupWrap = el.closest('.input-group')?.parentElement?.parentElement?.querySelector(':scope > .inline-group-feedback');
                        if (groupWrap) groupWrap.classList.remove('d-block');
                    }
                }, true);
            });

            document.addEventListener('hidden.bs.modal', function(e) {
                const modal = e.target;
                if (!modal) return;
                modal.querySelectorAll('.is-invalid').forEach(function(el) {
                    el.classList.remove('is-invalid');
                });
                modal.querySelectorAll('.inline-group-feedback.d-block').forEach(function(el) {
                    el.classList.remove('d-block');
                });
            });
        })();
    </script>
    <div class="modal fade" id="pushNotificationBlockedModal" tabindex="-1" aria-labelledby="notifBlockedModalLabel" aria-hidden="true" style="z-index: 1070;">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-0 pb-0 pt-3 px-4">
                    <h6 class="modal-title fw-bold text-danger d-flex align-items-center gap-2" id="notifBlockedModalLabel">
                        <i class="fas fa-bell-slash"></i> Browser Notifications Blocked
                    </h6>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 pt-3 pb-3 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle mb-3" style="width: 58px; height: 58px;">
                        <i class="fas fa-bell-slash fa-2x"></i>
                    </div>
                    <h6 class="fw-bold mb-1" style="color: #1e293b; font-size: 1.05rem;">Notifications are Blocked in Browser</h6>
                    <p class="text-muted small mb-3">Your browser has blocked notifications for this site, so order alerts cannot be shown.</p>

                    <div class="card border-0 bg-light p-3 mb-3 text-start" style="border-radius: 12px; font-size: 0.85rem;">
                        <div class="fw-semibold text-dark mb-2 d-flex align-items-center gap-2">
                            <i class="fas fa-key text-primary"></i> How to Allow Notifications:
                        </div>
                        <ol class="mb-0 ps-3 text-secondary" style="line-height: 1.7;">
                            <li>Look at the top browser URL address bar.</li>
                            <li>Click the <strong>Settings / Tune / Padlock (🔒 or 🎛️)</strong> icon next to the URL.</li>
                            <li>Find <strong>Notifications</strong> and change it from <strong>Block</strong> to <strong>Allow</strong>.</li>
                            <li>Click <strong>Reload Page</strong> below to activate.</li>
                        </ol>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-sm btn-light px-3" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-sm btn-primary px-3 d-flex align-items-center gap-1" onclick="window.location.reload()">
                            <i class="fas fa-redo"></i> Reload Page
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>
    <div id="notificationToastContainer" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;"></div>
    <script src="../assets/js/push-notifications.js"></script>
</body>
</html>