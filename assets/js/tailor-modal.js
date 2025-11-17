(() => {
    const modalElement = document.getElementById('tailorDetailModal');
    if (!modalElement || typeof bootstrap === 'undefined') {
        return;
    }

    if (!document.getElementById('tailor-modal-style')) {
        const style = document.createElement('style');
        style.id = 'tailor-modal-style';
        style.textContent = `
            .star-rating-input {
                display: inline-flex;
                flex-direction: row-reverse;
                gap: 6px;
            }
            .star-rating-input input {
                display: none;
            }
            .star-rating-input label {
                font-size: 1.75rem;
                color: #d1d5db;
                cursor: pointer;
                transition: color 0.2s ease;
            }
            .star-rating-input input:checked ~ label,
            .star-rating-input label:hover,
            .star-rating-input label:hover ~ label {
                color: #f59e0b;
            }
            .review-card {
                border: 1px solid #edf2f7;
                border-radius: 12px;
                padding: 16px;
                margin-bottom: 16px;
                background: #fff;
            }
            .review-card .review-rating {
                color: #f59e0b;
            }
            .review-card img {
                max-height: 120px;
                object-fit: cover;
                border-radius: 10px;
                margin-top: 10px;
            }
            .rating-pill {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #f1f5f9;
                border-radius: 999px;
                padding: 6px 12px;
                font-size: 0.9rem;
            }
            .rating-pill i {
                color: #f59e0b;
            }
        `;
        document.head.appendChild(style);
    }

    const modal = new bootstrap.Modal(modalElement);

    modalElement.addEventListener('hidden.bs.modal', () => {
        modalElement.classList.remove('show');
        modalElement.style.display = '';
        modalElement.setAttribute('aria-hidden', 'true');
    });
    const loadingEl = modalElement.querySelector('#tailorModalLoading');
    const contentEl = modalElement.querySelector('#tailorModalContent');
    const errorEl = modalElement.querySelector('#tailorModalError');
    const formEl = modalElement.querySelector('#tailorReviewForm');
    const messageEl = modalElement.querySelector('#tailorReviewMessage');

    const titleEl = modalElement.querySelector('#tailorModalTitle');
    const imageEl = modalElement.querySelector('#tailorModalImage');
    const ratingEl = modalElement.querySelector('#tailorModalRating');
    const statsEl = modalElement.querySelector('#tailorModalStats');
    const breakdownEl = modalElement.querySelector('#tailorRatingBreakdown');
    const locationEl = modalElement.querySelector('#tailorModalLocation');
    const contactEl = modalElement.querySelector('#tailorModalContact');
    const nameEl = modalElement.querySelector('#tailorModalName');
    const ownerEl = modalElement.querySelector('#tailorModalOwner');
    const descriptionEl = modalElement.querySelector('#tailorModalDescription');
    const specialtiesEl = modalElement.querySelector('#tailorModalSpecialties');
    const workingHoursEl = modalElement.querySelector('#tailorModalWorkingHours');
    const reviewsContainer = modalElement.querySelector('#tailorReviewsContainer');

    let currentCompanyId = null;

    function showTailorModal(companyId) {
        if (!companyId) {
            return;
        }
        currentCompanyId = companyId;
        resetModal();
        modalElement.removeAttribute('aria-hidden');
        modalElement.classList.add('show');
        modalElement.style.display = 'block';
        modal.show();
        loadTailorDetails(companyId);
    }

    document.addEventListener('click', (event) => {
        const card = event.target.closest('[data-tailor-id]');
        if (!card) {
            return;
        }

        if (event.target.closest('.no-tailor-modal')) {
            return;
        }

        event.preventDefault();
        const companyId = card.getAttribute('data-tailor-id');
        showTailorModal(companyId);
    });

    function resetModal() {
        loadingEl.classList.remove('d-none');
        contentEl.classList.add('d-none');
        errorEl.classList.add('d-none');
        errorEl.textContent = '';
        formEl.reset();
        messageEl.textContent = '';
        messageEl.classList.remove('text-danger', 'text-success');
        formEl.querySelector('input[name="company_id"]').value = currentCompanyId || '';
    }

    function loadTailorDetails(companyId) {
        fetch(`ajax/get_tailor_details.php?id=${encodeURIComponent(companyId)}`)
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) {
                    throw new Error(data.message || 'Unable to load tailor details.');
                }
                populateModal(data);
            })
            .catch((error) => {
                errorEl.textContent = error.message;
                errorEl.classList.remove('d-none');
            })
            .finally(() => {
                loadingEl.classList.add('d-none');
            });
    }

    function escapeHtml(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function populateModal(payload) {
        const company = payload.company;
        titleEl.textContent = company.shop_name || 'Tailor Details';
        nameEl.textContent = company.shop_name || '';
        ownerEl.textContent = company.owner_name ? `Owned by ${company.owner_name}` : '';
        descriptionEl.textContent = company.description || 'No description provided yet.';

        imageEl.src = company.shop_image || 'uploads/logos/default-shop.jpg';
        imageEl.alt = company.shop_name || 'Tailor';

        ratingEl.innerHTML = `
            <div class="rating-pill">
                <i class="fas fa-star"></i>
                <span>${(payload.stats.average_rating || 0).toFixed(1)}</span>
                <span class="text-muted">(${payload.stats.total_reviews || 0} reviews)</span>
            </div>
        `;

        statsEl.textContent = company.years_experience
            ? `${company.years_experience}+ years experience`
            : '';

        const locationText = [company.city, company.state, company.postal_code]
            .filter(Boolean)
            .map(escapeHtml)
            .join(', ') || 'Location unavailable';
        locationEl.innerHTML = `
            <i class="fas fa-map-marker-alt me-1"></i>
            ${locationText}
        `;

        const contactLinks = [];
        if (company.phone) {
            const tel = company.phone.replace(/[^0-9+]/g, '');
            contactLinks.push(`<a href="tel:${tel}" class="btn btn-outline-primary btn-sm me-2 no-tailor-modal">
                <i class="fas fa-phone me-1"></i> Call
            </a>`);
        }
        if (company.whatsapp) {
            const wa = company.whatsapp.replace(/[^0-9]/g, '');
            contactLinks.push(`<a href="https://wa.me/${wa}" target="_blank" class="btn btn-success btn-sm no-tailor-modal">
                <i class="fab fa-whatsapp me-1"></i> WhatsApp
            </a>`);
        }
        contactEl.innerHTML = contactLinks.join(' ') || '';

        specialtiesEl.innerHTML = '';
        if (company.specialties && company.specialties.length) {
            specialtiesEl.innerHTML = `
                <h6>Specialties</h6>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    ${company.specialties.map((item) => `<span class="badge bg-light text-dark">${escapeHtml(item)}</span>`).join('')}
                </div>
            `;
        }

        workingHoursEl.innerHTML = '';
        if (company.working_hours && Object.keys(company.working_hours).length) {
            const list = Object.entries(company.working_hours)
                .map(([day, hours]) => `<div class="d-flex justify-content-between small"><span class="text-muted">${escapeHtml(day)}</span><span>${escapeHtml(hours)}</span></div>`)
                .join('');
            workingHoursEl.innerHTML = `<h6>Working Hours</h6>${list}`;
        }

        renderBreakdown(payload.stats.breakdown || {}, payload.stats.total_reviews || 0);
        renderReviews(payload.reviews || []);
        updateTailorCardStats(company.id, payload.stats);

        contentEl.classList.remove('d-none');
    }

    function renderBreakdown(breakdown, total) {
        breakdownEl.innerHTML = '';
        if (!total) {
            return;
        }

        const counts = Object.values(breakdown);
        const maxCount = counts.length ? Math.max(...counts) : 0;
        const items = [];
        for (let rating = 5; rating >= 1; rating--) {
            const count = breakdown[rating] || 0;
            const width = maxCount ? Math.round((count / maxCount) * 100) : 0;
            items.push(`
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="small text-muted" style="min-width:45px;">${rating} ★</span>
                    <div class="progress flex-grow-1" style="height:6px;">
                        <div class="progress-bar bg-warning" role="progressbar" style="width:${width}%"></div>
                    </div>
                    <span class="small text-muted" style="min-width:24px; text-align:right;">${count}</span>
                </div>
            `);
        }
        breakdownEl.innerHTML = items.join('');
    }

    function renderReviews(reviews) {
        if (!reviews.length) {
            reviewsContainer.innerHTML = `
                <div class="text-muted small">No reviews yet. Be the first to share your experience!</div>
            `;
            return;
        }

        reviewsContainer.innerHTML = reviews.map((review) => `
            <div class="review-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong>${escapeHtml(review.reviewer_name)}</strong>
                        <div class="small text-muted">${escapeHtml(review.created_at)}</div>
                    </div>
                    <div class="review-rating">
                        ${generateStars(review.rating)}
                    </div>
                </div>
                ${review.review_text ? `<p class="mt-2 mb-0">${escapeHtml(review.review_text)}</p>` : ''}
                ${review.review_image ? `<img src="${review.review_image}" alt="Review Image" class="mt-2 img-fluid">` : ''}
            </div>
        `).join('');
    }

    function updateTailorCardStats(companyId, stats) {
        if (!companyId || !stats) {
            console.log('updateTailorCardStats: Missing companyId or stats', { companyId, stats });
            return;
        }

        const card = document.querySelector(`[data-tailor-id="${companyId}"]`);
        if (!card) {
            console.log('updateTailorCardStats: Card not found for companyId', companyId);
            return;
        }

        // Handle both review_count and total_reviews (for compatibility)
        const average = Number(stats.average_rating || stats.rating || 0);
        const total = Number(stats.review_count || stats.total_reviews || 0);

        console.log('updateTailorCardStats: Updating card with', { average, total, stats });

        const ratingNumberEl = card.querySelector('.rating-number');
        const ratingCountEl = card.querySelector('.rating-count');
        const ratingStarsEl = card.querySelector('.rating-stars');

        if (ratingNumberEl) {
            ratingNumberEl.textContent = average.toFixed(1);
        }

        if (ratingCountEl) {
            ratingCountEl.textContent = `(${total} reviews)`;
        }

        if (ratingStarsEl) {
            ratingStarsEl.innerHTML = generateStars(average);
        }
    }

    function generateStars(value) {
        const full = Math.round(value);
        let html = '';
        for (let i = 1; i <= 5; i++) {
            html += `<i class="${i <= full ? 'fas' : 'far'} fa-star"></i>`;
        }
        return html;
    }

    formEl.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!currentCompanyId) {
            return;
        }

        const formData = new FormData(formEl);
        formData.set('company_id', currentCompanyId);

        messageEl.textContent = 'Submitting your review...';
        messageEl.classList.remove('text-danger');
        messageEl.classList.add('text-muted');

        fetch('ajax/submit_tailor_review.php', {
            method: 'POST',
            body: formData
        })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) {
                    throw new Error(data.message || 'Unable to submit review.');
                }
                messageEl.textContent = data.message;
                messageEl.classList.remove('text-muted');
                messageEl.classList.add('text-success');
                formEl.reset();
                
                // Always fetch fresh stats to ensure accuracy
                if (currentCompanyId) {
                    // Fetch updated stats from server
                    fetch(`ajax/get_tailor_details.php?id=${encodeURIComponent(currentCompanyId)}`)
                        .then((response) => response.json())
                        .then((detailData) => {
                            if (detailData.success && detailData.stats) {
                                console.log('Fetched updated stats:', detailData.stats);
                                updateTailorCardStats(currentCompanyId, detailData.stats);
                            } else {
                                // Fallback to stats from submit response if available
                                if (data.stats) {
                                    console.log('Using stats from submit response:', data.stats);
                                    updateTailorCardStats(currentCompanyId, data.stats);
                                }
                            }
                        })
                        .catch((error) => {
                            console.error('Error fetching updated stats:', error);
                            // Fallback to stats from submit response if available
                            if (data.stats) {
                                updateTailorCardStats(currentCompanyId, data.stats);
                            }
                        });
                }
                
                // Close modal after a short delay to show success message
                setTimeout(() => {
                    modal.hide();
                }, 1000);
            })
            .catch((error) => {
                messageEl.textContent = error.message;
                messageEl.classList.remove('text-muted');
                messageEl.classList.add('text-danger');
            });
    });

    window.openTailorModal = showTailorModal;
})();


