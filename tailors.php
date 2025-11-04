<?php
/**
 * Tailor Listing Page
 * Displays all registered tailors with search and filter options
 */

require_once 'config/database.php';
require_once 'models/Company.php';

$database = new Database();
$db = $database->getConnection();
$company = new Company($db);

// Check if companies table exists
$tableExists = false;
try {
    $tableCheck = $db->query("SHOW TABLES LIKE 'companies'");
    $tableExists = ($tableCheck->rowCount() > 0);
} catch (Exception $e) {
    $tableExists = false;
}

if (!$tableExists) {
    echo '<!DOCTYPE html>
    <html><head><title>Setup Required</title></head>
    <body style="font-family: Arial; text-align: center; padding: 50px;">
        <h1>Setup Required</h1>
        <p>The companies table needs to be created.</p>
        <p>Please run the SQL migration: <code>database/setup_companies_for_listing.sql</code></p>
        <p><a href="IMPORT_DATABASE_NOW.html">Click here for setup instructions</a></p>
    </body></html>';
    exit;
}

// Get initial data
$cities = $company->getUniqueCities();
$states = $company->getUniqueStates();
$stats = $company->getListingStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Tailor Shops Near You | Tailoring Management System</title>
    <meta name="description" content="Find experienced tailor shops near your location. Browse profiles, read reviews, and connect with skilled tailors for all your stitching needs.">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="Favicon.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        /* Header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0 40px;
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .stats-bar {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filter-header h5 {
            margin: 0;
            font-weight: 600;
        }

        .clear-filters {
            color: #667eea;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .clear-filters:hover {
            text-decoration: underline;
            color: #764ba2;
        }

        .filter-active {
            position: relative;
        }

        .filter-active::after {
            content: '';
            position: absolute;
            top: -2px;
            right: -2px;
            width: 8px;
            height: 8px;
            background: #28a745;
            border-radius: 50%;
            border: 2px solid white;
        }

        /* Tailor Cards */
        .tailor-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .tailor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .tailor-image {
            position: relative;
            width: 100%;
            height: 200px;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .tailor-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .verified-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #28a745;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .featured-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #ffc107;
            color: #333;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .tailor-card-body {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .tailor-name {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: #333;
        }

        .tailor-owner {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .rating {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 15px;
        }

        .rating-stars {
            color: #ffc107;
        }

        .rating-number {
            font-weight: 600;
            color: #333;
        }

        .rating-count {
            color: #666;
            font-size: 0.85rem;
        }

        .tailor-location {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .tailor-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .specialties {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 15px;
        }

        .specialty-badge {
            background: #f0f0f0;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            color: #666;
        }

        .tailor-info {
            border-top: 1px solid #f0f0f0;
            padding-top: 15px;
            margin-top: auto;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.85rem;
            color: #666;
        }

        .info-item i {
            color: #667eea;
            width: 16px;
        }

        .contact-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .contact-buttons .btn {
            flex: 1;
            font-size: 0.85rem;
            padding: 8px;
        }

        /* Loading & Empty States */
        .loading-spinner {
            text-align: center;
            padding: 50px;
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
            border-width: 0.3em;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h4 {
            color: #666;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
        }

        /* Pagination */
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin-top: 40px;
        }

        /* Back Button */
        .back-to-home {
            margin-bottom: 30px;
        }

        .back-to-home a {
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .back-to-home a:hover {
            opacity: 0.8;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2rem;
            }

            .filter-section {
                padding: 15px;
            }

            .tailor-card-body {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="back-to-home">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
            
            <h1>Find Tailor Shops Near You</h1>
            <p>Connect with professional tailor shops in your area for all your stitching needs</p>
            
            <div class="stats-bar">
                <div class="row">
                    <div class="col-6 col-md-3">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $stats['active']; ?></span>
                            <span class="stat-label">Active Shops</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $stats['verified']; ?></span>
                            <span class="stat-label">Verified</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($stats['avg_rating'], 1); ?></span>
                            <span class="stat-label">Avg Rating</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo count($cities); ?></span>
                            <span class="stat-label">Cities</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-header">
                <h5><i class="fas fa-filter me-2"></i>Filter Tailor Shops</h5>
                <span class="clear-filters" onclick="clearFilters()">
                    <i class="fas fa-times-circle me-1"></i>Clear Filters
                </span>
            </div>
            
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search <small class="text-muted">(auto-filters as you type)</small></label>
                    <input type="text" class="form-control" id="keyword" placeholder="Shop name, city, specialty...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">City</label>
                    <select class="form-select" id="city">
                        <option value="">All Cities</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo htmlspecialchars($city); ?>"><?php echo htmlspecialchars($city); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">State</label>
                    <select class="form-select" id="state">
                        <option value="">All States</option>
                        <?php foreach ($states as $state): ?>
                            <option value="<?php echo htmlspecialchars($state); ?>"><?php echo htmlspecialchars($state); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Min Rating</label>
                    <select class="form-select" id="min_rating">
                        <option value="">Any</option>
                        <option value="4.5">4.5+</option>
                        <option value="4.0">4.0+</option>
                        <option value="3.5">3.5+</option>
                        <option value="3.0">3.0+</option>
                    </select>
                </div>
            </div>
            
            <div class="row g-3 mt-2">
                <div class="col-md-3">
                    <label class="form-label">Sort By <small class="text-muted">(auto-sorts)</small></label>
                    <select class="form-select" id="sort">
                        <option value="rating">Highest Rating</option>
                        <option value="reviews">Most Reviews</option>
                        <option value="experience">Most Experienced</option>
                        <option value="name">Name (A-Z)</option>
                    </select>
                </div>
                <div class="col-md-9 d-flex align-items-end">
                    <div class="alert alert-info mb-0 py-2 px-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>Filters apply automatically as you change selections</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <div id="resultsSection">
            <div class="loading-spinner">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination-wrapper" id="paginationWrapper"></div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let currentPage = 1;
        const perPage = 12;

        // Load tailors on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadTailors();
            
            // Add event listeners for instant filtering
            
            // Search input - trigger on typing (with debounce)
            let searchTimeout;
            document.getElementById('keyword').addEventListener('input', function(e) {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    applyFilters();
                }, 500); // Wait 500ms after user stops typing
            });
            
            // Enter key support for search
            document.getElementById('keyword').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(searchTimeout);
                    applyFilters();
                }
            });
            
            // City dropdown - instant filter
            document.getElementById('city').addEventListener('change', function() {
                applyFilters();
            });
            
            // State dropdown - instant filter
            document.getElementById('state').addEventListener('change', function() {
                applyFilters();
            });
            
            // Min rating dropdown - instant filter
            document.getElementById('min_rating').addEventListener('change', function() {
                applyFilters();
            });
            
            // Sort dropdown - instant sort
            document.getElementById('sort').addEventListener('change', function() {
                applyFilters();
            });
        });

        function applyFilters() {
            currentPage = 1;
            loadTailors();
        }

        function clearFilters() {
            document.getElementById('keyword').value = '';
            document.getElementById('city').value = '';
            document.getElementById('state').value = '';
            document.getElementById('min_rating').value = '';
            document.getElementById('sort').value = 'rating';
            currentPage = 1;
            loadTailors();
        }

        function loadTailors(page = 1) {
            currentPage = page;
            const offset = (page - 1) * perPage;
            
            const params = new URLSearchParams({
                keyword: document.getElementById('keyword').value,
                city: document.getElementById('city').value,
                state: document.getElementById('state').value,
                min_rating: document.getElementById('min_rating').value,
                sort: document.getElementById('sort').value,
                limit: perPage,
                offset: offset
            });

            // Show loading with animation
            document.getElementById('resultsSection').innerHTML = `
                <div class="loading-spinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading shops...</p>
                </div>
            `;

            // Add slight delay for smooth UX
            fetch('ajax/filter_tailors.php?' + params)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Server error');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        displayTailors(data.data);
                        displayPagination(data.pagination);
                    } else {
                        // Show detailed error message
                        let errorMsg = data.message || 'Failed to load shops';
                        if (data.error_details) {
                            errorMsg += '<br><small>' + data.error_details + '</small>';
                        }
                        showError(errorMsg);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Unable to load shops. Please check if the database is set up correctly.');
                });
        }

        function displayTailors(tailors) {
            const resultsSection = document.getElementById('resultsSection');
            
            if (tailors.length === 0) {
                resultsSection.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h4>No Tailors Found</h4>
                        <p>Try adjusting your filters to find more results</p>
                    </div>
                `;
                return;
            }

            let html = '<div class="row g-4">';
            
            tailors.forEach(tailor => {
                const specialties = (tailor.specialties || []).slice(0, 3);
                const stars = generateStars(tailor.rating);
                
                html += `
                    <div class="col-md-6 col-lg-4">
                        <div class="tailor-card">
                            <div class="tailor-image">
                                <img src="${tailor.shop_image}" alt="${tailor.shop_name}" onerror="this.onerror=null; this.src='uploads/logos/default-shop.jpg';">
                                ${tailor.is_featured ? '<span class="featured-badge"><i class="fas fa-star me-1"></i>Featured</span>' : ''}
                                ${tailor.is_verified ? '<span class="verified-badge"><i class="fas fa-check-circle me-1"></i>Verified</span>' : ''}
                            </div>
                            <div class="tailor-card-body">
                                <h3 class="tailor-name">${tailor.shop_name}</h3>
                                <p class="tailor-owner"><i class="fas fa-user me-1"></i>${tailor.owner_name}</p>
                                
                                <div class="rating">
                                    <span class="rating-stars">${stars}</span>
                                    <span class="rating-number">${tailor.rating}</span>
                                    <span class="rating-count">(${tailor.total_reviews} reviews)</span>
                                </div>
                                
                                <div class="tailor-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    ${tailor.city}, ${tailor.state}
                                </div>
                                
                                <p class="tailor-description">${tailor.description || ''}</p>
                                
                                ${specialties.length > 0 ? `
                                <div class="specialties">
                                    ${specialties.map(s => `<span class="specialty-badge">${s}</span>`).join('')}
                                </div>
                                ` : ''}
                                
                                <div class="tailor-info">
                                    <div class="info-item">
                                        <i class="fas fa-phone"></i>
                                        <span>${tailor.phone}</span>
                                    </div>
                                    ${tailor.years_experience ? `
                                    <div class="info-item">
                                        <i class="fas fa-award"></i>
                                        <span>${tailor.years_experience} years experience</span>
                                    </div>
                                    ` : ''}
                                    
                                    <div class="contact-buttons">
                                        <a href="tel:${tailor.phone}" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-phone me-1"></i>Call
                                        </a>
                                        ${tailor.whatsapp ? `
                                        <a href="https://wa.me/${tailor.whatsapp.replace(/[^0-9]/g, '')}" target="_blank" class="btn btn-success btn-sm">
                                            <i class="fab fa-whatsapp me-1"></i>WhatsApp
                                        </a>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            resultsSection.innerHTML = html;
        }

        function generateStars(rating) {
            const fullStars = Math.floor(rating);
            const halfStar = rating % 1 >= 0.5;
            const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);
            
            let stars = '';
            for (let i = 0; i < fullStars; i++) {
                stars += '<i class="fas fa-star"></i>';
            }
            if (halfStar) {
                stars += '<i class="fas fa-star-half-alt"></i>';
            }
            for (let i = 0; i < emptyStars; i++) {
                stars += '<i class="far fa-star"></i>';
            }
            
            return stars;
        }

        function displayPagination(pagination) {
            const wrapper = document.getElementById('paginationWrapper');
            
            if (pagination.total_pages <= 1) {
                wrapper.innerHTML = '';
                return;
            }

            let html = '<nav><ul class="pagination">';
            
            // Previous button
            html += `
                <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadTailors(${pagination.current_page - 1}); return false;">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
            `;
            
            // Page numbers
            const maxPages = 5;
            let startPage = Math.max(1, pagination.current_page - Math.floor(maxPages / 2));
            let endPage = Math.min(pagination.total_pages, startPage + maxPages - 1);
            
            if (endPage - startPage < maxPages - 1) {
                startPage = Math.max(1, endPage - maxPages + 1);
            }
            
            for (let i = startPage; i <= endPage; i++) {
                html += `
                    <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="loadTailors(${i}); return false;">${i}</a>
                    </li>
                `;
            }
            
            // Next button
            html += `
                <li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadTailors(${pagination.current_page + 1}); return false;">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            `;
            
            html += '</ul></nav>';
            wrapper.innerHTML = html;
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function showError(message) {
            document.getElementById('resultsSection').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle text-danger"></i>
                    <h4>Setup Required</h4>
                    <div style="margin: 20px 0; padding: 20px; background: #fff3cd; border-radius: 10px; text-align: left;">
                        ${message}
                    </div>
                    <p><strong>You need to import the SQL file:</strong></p>
                    <p><code>database/setup_companies_for_listing.sql</code></p>
                    <a href="IMPORT_COMPANIES_NOW.html" class="btn btn-success mt-3">
                        <i class="fas fa-download me-2"></i>Setup Instructions
                    </a>
                    <button class="btn btn-primary mt-3" onclick="loadTailors()">
                        <i class="fas fa-sync me-2"></i>Try Again
                    </button>
                </div>
            `;
        }
    </script>
</body>
</html>

