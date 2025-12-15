<?php
/**
 * Testimonial Popup Component
 * Form for users to submit testimonials/reviews
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Company.php';

$database = new Database();
$db = $database->getConnection();
$companyModel = new Company($db);

// Get active companies for dropdown
$companies = [];
try {
    $companies = $companyModel->getActiveCompanies();
} catch (Exception $e) {
    // Table might not exist yet
    $companies = [];
}

// Get active tailors (users with role 'tailor') - query directly to avoid company filtering
$tailors = [];
try {
    $tailorQuery = "SELECT id, full_name FROM users WHERE role = 'tailor' AND status = 'active' ORDER BY full_name ASC";
    $tailorStmt = $db->prepare($tailorQuery);
    $tailorStmt->execute();
    $tailors = $tailorStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist yet
    $tailors = [];
}
?>

<!-- Testimonial Popup Modal -->
<div id="testimonialPopup" class="testimonial-popup-overlay" style="display: none;">
    <div class="testimonial-popup-container">
        <button class="testimonial-popup-close" id="closeTestimonialPopup" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
        <div class="testimonial-popup-content">
            <div class="testimonial-popup-header">
                <div class="testimonial-popup-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h2 class="testimonial-popup-title">Share Your Experience</h2>
                <p class="testimonial-popup-subtitle">Help others by sharing your feedback</p>
            </div>
            
            <form id="testimonialForm" method="POST" action="ajax/submit_testimonial.php">
                <div class="testimonial-form-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="user_name" class="form-label">Your Name *</label>
                            <input type="text" class="form-control" id="user_name" name="user_name" required placeholder="Enter your name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Your Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required placeholder="your.email@example.com">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="company_id" class="form-label">Shop Name *</label>
                            <div class="searchable-select-wrapper">
                                <input type="text" class="form-control searchable-select-input" id="company_search" placeholder="Search shop name..." autocomplete="off">
                                <select class="form-select" id="company_id" name="company_id" required style="display: none;">
                                    <option value="">Select Shop</option>
                                    <?php foreach ($companies as $comp): ?>
                                        <option value="<?php echo $comp['id']; ?>" data-text="<?php echo htmlspecialchars(strtolower($comp['company_name'] . ' ' . $comp['owner_name'])); ?>">
                                            <?php echo htmlspecialchars($comp['company_name']); ?> - <?php echo htmlspecialchars($comp['owner_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="company_dropdown" class="searchable-dropdown"></div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="user_id" class="form-label">Tailor (Optional)</label>
                            <div class="searchable-select-wrapper">
                                <input type="text" class="form-control searchable-select-input" id="tailor_search" placeholder="Search tailor name..." autocomplete="off">
                                <select class="form-select" id="user_id" name="user_id" style="display: none;">
                                    <option value="">Select Tailor (Optional)</option>
                                    <?php foreach ($tailors as $tailor): ?>
                                        <option value="<?php echo $tailor['id']; ?>" data-text="<?php echo htmlspecialchars(strtolower($tailor['full_name'])); ?>">
                                            <?php echo htmlspecialchars($tailor['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="tailor_dropdown" class="searchable-dropdown"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Rating *</label>
                            <div class="star-rating">
                                <input type="radio" id="star1" name="star" value="1">
                                <label for="star1" class="star-label"><i class="fas fa-star"></i></label>
                                <input type="radio" id="star2" name="star" value="2">
                                <label for="star2" class="star-label"><i class="fas fa-star"></i></label>
                                <input type="radio" id="star3" name="star" value="3">
                                <label for="star3" class="star-label"><i class="fas fa-star"></i></label>
                                <input type="radio" id="star4" name="star" value="4">
                                <label for="star4" class="star-label"><i class="fas fa-star"></i></label>
                                <input type="radio" id="star5" name="star" value="5" checked>
                                <label for="star5" class="star-label"><i class="fas fa-star"></i></label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="comment" class="form-label">Your Review *</label>
                            <textarea class="form-control" id="comment" name="comment" rows="4" required placeholder="Share your experience..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="testimonial-popup-footer">
                    <button type="submit" class="btn btn-testimonial-submit">
                        <i class="fas fa-paper-plane me-2"></i>Submit Review
                    </button>
                    <button type="button" class="btn btn-testimonial-cancel" id="cancelTestimonial">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.testimonial-popup-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(5px);
}

.testimonial-popup-container {
    background: white;
    border-radius: 16px;
    max-width: 700px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: popupSlideIn 0.3s ease;
}

@keyframes popupSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.testimonial-popup-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: transparent;
    border: none;
    font-size: 24px;
    color: #666;
    cursor: pointer;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s ease;
    z-index: 10;
}

.testimonial-popup-close:hover {
    background: #f0f0f0;
    color: #333;
    transform: rotate(90deg);
}

.testimonial-popup-content {
    padding: 2.5rem;
}

.testimonial-popup-header {
    text-align: center;
    margin-bottom: 2rem;
}

.testimonial-popup-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    color: white;
    font-size: 24px;
}

.testimonial-popup-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.testimonial-popup-subtitle {
    color: #718096;
    font-size: 1rem;
}

.testimonial-form-body {
    margin-bottom: 1.5rem;
}

.form-label {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 0.5rem;
    display: block;
}

.form-control, .form-select {
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.75rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

.searchable-select-wrapper {
    position: relative;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

.searchable-select-input {
    margin-bottom: 0 !important;
}

.searchable-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 2px solid #e2e8f0;
    border-top: none;
    border-radius: 0 0 8px 8px;
    max-height: 250px;
    overflow-y: auto;
    z-index: 1000;
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    margin: 0 !important;
    padding: 0 !important;
    height: 0 !important;
    max-height: 0 !important;
    overflow: hidden !important;
    border-width: 0 !important;
    box-shadow: none !important;
}

.searchable-dropdown.show {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    height: auto !important;
    max-height: 250px !important;
    overflow-y: auto !important;
    border-width: 2px !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}

.searchable-dropdown-item {
    padding: 0.75rem 1rem;
    cursor: pointer;
    transition: background 0.2s ease;
    border-bottom: 1px solid #f0f0f0;
}

.searchable-dropdown-item:hover {
    background: #f7fafc;
}

.searchable-dropdown-item:last-child {
    border-bottom: none;
}

.star-rating {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin: 1rem 0;
    flex-direction: row-reverse;
}

.star-rating input[type="radio"] {
    display: none;
}

.star-rating .star-label {
    font-size: 2rem;
    color: #e2e8f0;
    cursor: pointer;
    transition: all 0.2s ease;
}

/* When a star is checked, fill it and all stars after it in DOM (which appear before it visually due to row-reverse) */
.star-rating input[type="radio"]:checked + .star-label,
.star-rating input[type="radio"]:checked ~ .star-label {
    color: #ffc107;
}

/* Hover effect - fill hovered star and all stars after it in DOM */
.star-rating .star-label:hover,
.star-rating .star-label:hover ~ .star-label {
    color: #ffc107;
}

.testimonial-popup-footer {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    padding-top: 1.5rem;
    border-top: 2px solid #e2e8f0;
}

.btn-testimonial-submit {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 0.75rem 2rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-testimonial-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-testimonial-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.btn-testimonial-cancel {
    background: #e2e8f0;
    color: #4a5568;
    border: none;
    padding: 0.75rem 2rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-testimonial-cancel:hover {
    background: #cbd5e0;
}

@media (max-width: 768px) {
    .testimonial-popup-container {
        max-width: 100%;
        margin: 10px;
    }
    
    .testimonial-popup-content {
        padding: 1.5rem;
    }
    
    .testimonial-popup-footer {
        flex-direction: column;
    }
    
    .btn-testimonial-submit,
    .btn-testimonial-cancel {
        width: 100%;
    }
}

/* Message Popup Styles */
.message-popup-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 10001;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(8px);
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes fadeOut {
    from {
        opacity: 1;
    }
    to {
        opacity: 0;
    }
}

.message-popup-container {
    background: white;
    border-radius: 20px;
    max-width: 500px;
    width: 100%;
    position: relative;
    box-shadow: 0 25px 70px rgba(0, 0, 0, 0.4);
    animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    overflow: hidden;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.message-popup-close-x {
    position: absolute;
    top: 15px;
    right: 15px;
    background: transparent;
    border: none;
    font-size: 20px;
    color: #718096;
    cursor: pointer;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s ease;
    z-index: 10;
}

.message-popup-close-x:hover {
    background: #f0f0f0;
    color: #2d3748;
    transform: rotate(90deg);
}

.message-popup-content {
    padding: 3rem 2.5rem;
    text-align: center;
    position: relative;
}

.message-popup-icon {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 48px;
    animation: scaleIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

@keyframes scaleIn {
    0% {
        transform: scale(0);
        opacity: 0;
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.message-popup-icon.success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
}

.message-popup-icon.error {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
}

.message-popup-title {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 1rem;
    letter-spacing: -0.02em;
}

.message-popup-title.success {
    color: #10b981;
}

.message-popup-title.error {
    color: #ef4444;
}

.message-popup-text {
    color: #4a5568;
    font-size: 1.1rem;
    line-height: 1.7;
    margin-bottom: 2rem;
    padding: 0 1rem;
}

.btn-message-close {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 0.875rem 2.5rem;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1rem;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    min-width: 120px;
}

.btn-message-close:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
}

.btn-message-close:active {
    transform: translateY(0);
}

@media (max-width: 768px) {
    .message-popup-overlay {
        padding: 15px;
    }
    
    .message-popup-container {
        max-width: 100%;
        margin: 0;
        border-radius: 16px;
    }
    
    .message-popup-content {
        padding: 2.5rem 1.5rem;
    }
    
    .message-popup-icon {
        width: 80px;
        height: 80px;
        font-size: 36px;
    }
    
    .message-popup-title {
        font-size: 1.5rem;
    }
    
    .message-popup-text {
        font-size: 1rem;
        padding: 0;
    }
    
    .btn-message-close {
        padding: 0.75rem 2rem;
        width: 100%;
    }
}
</style>

<script>
(function() {
    'use strict';
    
    // Global functions accessible from anywhere
    window.openTestimonialPopup = function() {
        const popup = document.getElementById('testimonialPopup');
        if (popup) {
            popup.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            return true;
        }
        return false;
    };
    
    window.closeTestimonialPopup = function() {
        const popup = document.getElementById('testimonialPopup');
        if (popup) {
            popup.style.display = 'none';
            document.body.style.overflow = '';
            
            // Reset submit button when closing popup
            const form = document.getElementById('testimonialForm');
            if (form) {
                const submitBtn = form.querySelector('.btn-testimonial-submit');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Review';
                }
            }
        }
    };
    
    // Initialize when DOM is ready
    function initTestimonialPopup() {
        const openBtn = document.getElementById('openTestimonialPopup');
        const closeBtn = document.getElementById('closeTestimonialPopup');
        const cancelBtn = document.getElementById('cancelTestimonial');
        const popup = document.getElementById('testimonialPopup');
        const form = document.getElementById('testimonialForm');
        
        // Open popup handler
        if (openBtn) {
            openBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                window.openTestimonialPopup();
                // Reset form and button when opening popup
                if (form) {
                    const submitBtn = form.querySelector('.btn-testimonial-submit');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Review';
                    }
                    form.reset();
                    const star5 = document.getElementById('star5');
                    if (star5) star5.checked = true;
                }
                return false;
            };
        }
        
        // Close popup handlers
        if (closeBtn) {
            closeBtn.onclick = function(e) {
                e.preventDefault();
                window.closeTestimonialPopup();
            };
        }
        
        if (cancelBtn) {
            cancelBtn.onclick = function(e) {
                e.preventDefault();
                window.closeTestimonialPopup();
            };
        }
        
        // Close on overlay click
        if (popup) {
            popup.onclick = function(e) {
                if (e.target === popup) {
                    window.closeTestimonialPopup();
                }
            };
        }
        
        // Form handling
        if (form) {
            // Searchable dropdown variables
            const companySearch = document.getElementById('company_search');
            const companySelect = document.getElementById('company_id');
            const companyDropdown = document.getElementById('company_dropdown');
            const tailorSearch = document.getElementById('tailor_search');
            const tailorSelect = document.getElementById('user_id');
            const tailorDropdown = document.getElementById('tailor_dropdown');
            let selectedCompanyText = '';
            let selectedTailorText = '';
            
            // Company searchable dropdown
            if (companySearch && companySelect && companyDropdown) {
                function filterCompanyOptions() {
                    const searchTerm = companySearch.value.toLowerCase().trim();
                    const options = companySelect.querySelectorAll('option');
                    companyDropdown.innerHTML = '';
                    
                    // If value is selected and no search term, don't show dropdown
                    if (companySelect.value && searchTerm === '' && companySearch.value === selectedCompanyText) {
                        companyDropdown.classList.remove('show');
                        companyDropdown.style.display = 'none';
                        companyDropdown.style.visibility = 'hidden';
                        companyDropdown.style.opacity = '0';
                        return;
                    }
                    
                    let hasResults = false;
                    options.forEach(function(option) {
                        if (option.value === '') {
                            // Show "Select Shop" option when no value selected or when searching
                            if (!companySelect.value || searchTerm !== '') {
                                const item = document.createElement('div');
                                item.className = 'searchable-dropdown-item';
                                item.textContent = option.textContent;
                                item.onclick = function(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    companySelect.value = '';
                                    companySearch.value = '';
                                    selectedCompanyText = '';
                                    companyDropdown.classList.remove('show');
                                    companyDropdown.style.display = 'none';
                                    companyDropdown.style.visibility = 'hidden';
                                    companyDropdown.style.opacity = '0';
                                    companyDropdown.innerHTML = '';
                                    companySearch.blur();
                                };
                                companyDropdown.appendChild(item);
                                hasResults = true;
                            }
                        } else {
                            const optionText = option.getAttribute('data-text') || option.textContent.toLowerCase();
                            // Show option if search term matches or if no search term (show all)
                            if (searchTerm === '' || optionText.includes(searchTerm)) {
                                const item = document.createElement('div');
                                item.className = 'searchable-dropdown-item';
                                item.textContent = option.textContent;
                                if (option.value === companySelect.value) {
                                    item.style.backgroundColor = '#e6f3ff';
                                    item.style.fontWeight = '600';
                                }
                                item.onclick = function(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    companySelect.value = option.value;
                                    selectedCompanyText = option.textContent.trim();
                                    companySearch.value = selectedCompanyText;
                                    companyDropdown.classList.remove('show');
                                    companyDropdown.style.display = 'none';
                                    companyDropdown.style.visibility = 'hidden';
                                    companyDropdown.style.opacity = '0';
                                    companyDropdown.innerHTML = '';
                                    companySearch.blur();
                                };
                                companyDropdown.appendChild(item);
                                hasResults = true;
                            }
                        }
                    });
                    
                    if (hasResults) {
                        companyDropdown.classList.add('show');
                        companyDropdown.style.display = 'block';
                        companyDropdown.style.visibility = 'visible';
                        companyDropdown.style.opacity = '1';
                    } else {
                        companyDropdown.classList.remove('show');
                        companyDropdown.style.display = 'none';
                        companyDropdown.style.visibility = 'hidden';
                        companyDropdown.style.opacity = '0';
                    }
                }
                
                companySearch.addEventListener('focus', function() {
                    filterCompanyOptions();
                });
                
                companySearch.addEventListener('input', filterCompanyOptions);
                
                companySearch.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (companySearch.value === selectedCompanyText && companySelect.value) {
                        companySearch.value = '';
                        selectedCompanyText = '';
                        filterCompanyOptions();
                    } else {
                        filterCompanyOptions();
                    }
                });
                
                document.addEventListener('click', function(e) {
                    if (!companySearch.contains(e.target) && !companyDropdown.contains(e.target)) {
                        companyDropdown.classList.remove('show');
                        companyDropdown.style.display = 'none';
                        companyDropdown.style.visibility = 'hidden';
                        companyDropdown.style.opacity = '0';
                        if (!companySearch.value && selectedCompanyText) {
                            companySearch.value = selectedCompanyText;
                        }
                    }
                });
            }
            
            // Tailor searchable dropdown
            if (tailorSearch && tailorSelect && tailorDropdown) {
                function filterTailorOptions() {
                    const searchTerm = tailorSearch.value.toLowerCase().trim();
                    const options = tailorSelect.querySelectorAll('option');
                    tailorDropdown.innerHTML = '';
                    
                    // If value is selected and no search term, don't show dropdown
                    if (tailorSelect.value && searchTerm === '' && tailorSearch.value === selectedTailorText) {
                        tailorDropdown.classList.remove('show');
                        tailorDropdown.style.display = 'none';
                        tailorDropdown.style.visibility = 'hidden';
                        tailorDropdown.style.opacity = '0';
                        return;
                    }
                    
                    let hasResults = false;
                    options.forEach(function(option) {
                        if (option.value === '') {
                            // Show "Select Tailor" option when no value selected or when searching
                            if (!tailorSelect.value || searchTerm !== '') {
                                const item = document.createElement('div');
                                item.className = 'searchable-dropdown-item';
                                item.textContent = option.textContent;
                                item.onclick = function(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    tailorSelect.value = '';
                                    tailorSearch.value = '';
                                    selectedTailorText = '';
                                    tailorDropdown.classList.remove('show');
                                    tailorDropdown.style.display = 'none';
                                    tailorDropdown.style.visibility = 'hidden';
                                    tailorDropdown.style.opacity = '0';
                                    tailorDropdown.innerHTML = '';
                                    tailorSearch.blur();
                                };
                                tailorDropdown.appendChild(item);
                                hasResults = true;
                            }
                        } else {
                            const optionText = option.getAttribute('data-text') || option.textContent.toLowerCase();
                            // Show option if search term matches or if no search term (show all)
                            if (searchTerm === '' || optionText.includes(searchTerm)) {
                                const item = document.createElement('div');
                                item.className = 'searchable-dropdown-item';
                                item.textContent = option.textContent;
                                if (option.value === tailorSelect.value) {
                                    item.style.backgroundColor = '#e6f3ff';
                                    item.style.fontWeight = '600';
                                }
                                item.onclick = function(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    tailorSelect.value = option.value;
                                    selectedTailorText = option.textContent.trim();
                                    tailorSearch.value = selectedTailorText;
                                    tailorDropdown.classList.remove('show');
                                    tailorDropdown.style.display = 'none';
                                    tailorDropdown.style.visibility = 'hidden';
                                    tailorDropdown.style.opacity = '0';
                                    tailorDropdown.innerHTML = '';
                                    tailorSearch.blur();
                                };
                                tailorDropdown.appendChild(item);
                                hasResults = true;
                            }
                        }
                    });
                    
                    if (hasResults) {
                        tailorDropdown.classList.add('show');
                        tailorDropdown.style.display = 'block';
                        tailorDropdown.style.visibility = 'visible';
                        tailorDropdown.style.opacity = '1';
                    } else {
                        tailorDropdown.classList.remove('show');
                        tailorDropdown.style.display = 'none';
                        tailorDropdown.style.visibility = 'hidden';
                        tailorDropdown.style.opacity = '0';
                    }
                }
                
                tailorSearch.addEventListener('focus', function() {
                    filterTailorOptions();
                });
                
                tailorSearch.addEventListener('input', filterTailorOptions);
                
                tailorSearch.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (tailorSearch.value === selectedTailorText && tailorSelect.value) {
                        tailorSearch.value = '';
                        selectedTailorText = '';
                        filterTailorOptions();
                    } else {
                        filterTailorOptions();
                    }
                });
                
                document.addEventListener('click', function(e) {
                    if (!tailorSearch.contains(e.target) && !tailorDropdown.contains(e.target)) {
                        tailorDropdown.classList.remove('show');
                        tailorDropdown.style.display = 'none';
                        tailorDropdown.style.visibility = 'hidden';
                        tailorDropdown.style.opacity = '0';
                        if (!tailorSearch.value && selectedTailorText) {
                            tailorSearch.value = selectedTailorText;
                        }
                    }
                });
            }
            
            // Reset form function
            function resetForm() {
                form.reset();
                
                // Reset submit button
                const submitBtn = form.querySelector('.btn-testimonial-submit');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Review';
                }
                
                if (companySearch) companySearch.value = '';
                if (companySelect) companySelect.value = '';
                if (companyDropdown) {
                    companyDropdown.classList.remove('show');
                    companyDropdown.style.display = 'none';
                    companyDropdown.style.visibility = 'hidden';
                    companyDropdown.style.opacity = '0';
                    companyDropdown.innerHTML = '';
                }
                
                if (tailorSearch) tailorSearch.value = '';
                if (tailorSelect) tailorSelect.value = '';
                if (tailorDropdown) {
                    tailorDropdown.classList.remove('show');
                    tailorDropdown.style.display = 'none';
                    tailorDropdown.style.visibility = 'hidden';
                    tailorDropdown.style.opacity = '0';
                    tailorDropdown.innerHTML = '';
                }
                
                const star5 = document.getElementById('star5');
                if (star5) star5.checked = true;
                
                selectedCompanyText = '';
                selectedTailorText = '';
            }
            
            // Form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(form);
                const submitBtn = form.querySelector('.btn-testimonial-submit');
                const originalText = submitBtn.innerHTML;
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
                
                fetch('ajax/submit_testimonial.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resetForm();
                        window.closeTestimonialPopup();
                        showMessagePopup('success', 'Thank you for your feedback! Your review has been submitted and will be reviewed.');
                    } else {
                        showMessagePopup('error', 'Error: ' + (data.message || 'Failed to submit review. Please try again.'));
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessagePopup('error', 'An error occurred. Please try again later.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
            });
            
            // Show message popup function
            function showMessagePopup(type, message) {
                let messagePopup = document.getElementById('messagePopup');
                
                if (!messagePopup) {
                    const popupHTML = `
                        <div id="messagePopup" class="message-popup-overlay" style="display: none;">
                            <div class="message-popup-container">
                                <button class="message-popup-close-x" id="messagePopupCloseX" aria-label="Close">
                                    <i class="fas fa-times"></i>
                                </button>
                                <div class="message-popup-content">
                                    <div class="message-popup-icon" id="messageIcon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <h3 class="message-popup-title" id="messageTitle">Success!</h3>
                                    <p class="message-popup-text" id="messageText">${message}</p>
                                    <button class="btn btn-message-close" id="messageCloseBtn">
                                        <i class="fas fa-check me-2"></i>Got it
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    document.body.insertAdjacentHTML('beforeend', popupHTML);
                    messagePopup = document.getElementById('messagePopup');
                }
                
                const icon = document.getElementById('messageIcon');
                const title = document.getElementById('messageTitle');
                const text = document.getElementById('messageText');
                const closeBtn = document.getElementById('messageCloseBtn');
                const closeXBtn = document.getElementById('messagePopupCloseX');
                
                if (type === 'success') {
                    icon.innerHTML = '<i class="fas fa-check-circle"></i>';
                    icon.className = 'message-popup-icon success';
                    title.textContent = 'Success!';
                    title.className = 'message-popup-title success';
                    closeBtn.innerHTML = '<i class="fas fa-check me-2"></i>Got it';
                } else {
                    icon.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
                    icon.className = 'message-popup-icon error';
                    title.textContent = 'Error!';
                    title.className = 'message-popup-title error';
                    closeBtn.innerHTML = '<i class="fas fa-times me-2"></i>Close';
                }
                
                text.textContent = message;
                
                function closeMessagePopup() {
                    messagePopup.style.animation = 'fadeOut 0.3s ease';
                    setTimeout(function() {
                        messagePopup.style.display = 'none';
                        document.body.style.overflow = '';
                        messagePopup.style.animation = '';
                    }, 300);
                }
                
                messagePopup.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                
                closeBtn.onclick = closeMessagePopup;
                if (closeXBtn) {
                    closeXBtn.onclick = closeMessagePopup;
                }
                
                messagePopup.onclick = function(e) {
                    if (e.target === messagePopup) {
                        closeMessagePopup();
                    }
                };
                
                if (type === 'success') {
                    setTimeout(closeMessagePopup, 5000);
                }
            }
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTestimonialPopup);
    } else {
        initTestimonialPopup();
    }
    
    // Also try after delays as backup
    setTimeout(initTestimonialPopup, 100);
    setTimeout(initTestimonialPopup, 500);
})();
</script>
