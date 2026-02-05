/**
 * Heaven Nails - Main JavaScript
 * Multi-step booking form, validation, and interactions
 */

document.addEventListener('DOMContentLoaded', () => {
    // Initialize all modules
    initNavigation();
    initBookingForm();
    initServiceCalculator();
    initAvailabilityChecker();
    initGalleryLightbox();
    initMobileFAB();
    setMinDate();
});

// Service durations in minutes
const SERVICE_DURATIONS = {
    'Classic Manicure': 45,
    'Gel Extensions': 90,
    'Nail Art': 60,
    'Spa Pedicure': 60,
    'Acrylic Nails': 90,
    'Nail Repair': 30
};

// ================================
// Navigation
// ================================
function initNavigation() {
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');
    const navLinks = document.querySelectorAll('.nav-link');

    navToggle?.addEventListener('click', () => {
        navToggle.classList.toggle('active');
        navMenu.classList.toggle('active');
        document.body.style.overflow = navMenu.classList.contains('active') ? 'hidden' : '';
    });

    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navToggle.classList.remove('active');
            navMenu.classList.remove('active');
            document.body.style.overflow = '';
        });
    });

    // Navbar scroll effect
    window.addEventListener('scroll', () => {
        const navbar = document.getElementById('navbar');
        if (window.scrollY > 50) {
            navbar.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.1)';
        } else {
            navbar.style.boxShadow = 'none';
        }
    });
}

// ================================
// Multi-Step Booking Form
// ================================
function initBookingForm() {
    const form = document.getElementById('bookingForm');
    const nextBtns = document.querySelectorAll('.btn-next');
    const prevBtns = document.querySelectorAll('.btn-prev');
    const progressSteps = document.querySelectorAll('.progress-step');

    // Next step buttons
    nextBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const currentStep = parseInt(btn.closest('.form-step').dataset.step);
            const nextStep = parseInt(btn.dataset.next);

            if (validateStep(currentStep)) {
                goToStep(nextStep);
            }
        });
    });

    // Previous step buttons
    prevBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const prevStep = parseInt(btn.dataset.prev);
            goToStep(prevStep);
        });
    });

    // Form submission
    form?.addEventListener('submit', handleSubmit);

    // Modal close
    document.getElementById('closeModal')?.addEventListener('click', () => {
        document.getElementById('successModal').classList.remove('active');
        form.reset();
        goToStep(1);
        updateTotal();
    });
}

function goToStep(step) {
    const formSteps = document.querySelectorAll('.form-step');
    const progressSteps = document.querySelectorAll('.progress-step');

    formSteps.forEach(s => s.classList.remove('active'));
    document.querySelector(`.form-step[data-step="${step}"]`)?.classList.add('active');

    progressSteps.forEach((s, index) => {
        s.classList.remove('active', 'completed');
        if (index + 1 < step) {
            s.classList.add('completed');
        } else if (index + 1 === step) {
            s.classList.add('active');
        }
    });
}

function validateStep(step) {
    let isValid = true;

    if (step === 1) {
        const checkboxes = document.querySelectorAll('input[name="services[]"]:checked');
        if (checkboxes.length === 0) {
            showToast('Please select at least one service', 'error');
            isValid = false;
        }
    } else if (step === 2) {
        const date = document.getElementById('preferredDate');
        const time = document.getElementById('preferredTime');

        if (!date.value) {
            showToast('Please select a date', 'error');
            isValid = false;
        } else if (!time.value) {
            showToast('Please select a time', 'error');
            isValid = false;
        }
    } else if (step === 3) {
        const name = document.getElementById('clientName');
        const email = document.getElementById('email');
        const phone = document.getElementById('phone');

        if (!name.value.trim()) {
            markError(name, 'Name is required');
            isValid = false;
        } else {
            clearError(name);
        }

        if (!email.value.trim() || !isValidEmail(email.value)) {
            markError(email, 'Valid email is required');
            isValid = false;
        } else {
            clearError(email);
        }

        if (!phone.value.trim() || phone.value.length < 10) {
            markError(phone, 'Valid phone number is required');
            isValid = false;
        } else {
            clearError(phone);
        }
    }

    return isValid;
}

function markError(input, message) {
    const group = input.closest('.form-group');
    group.classList.add('error');
    let errorMsg = group.querySelector('.error-message');
    if (!errorMsg) {
        errorMsg = document.createElement('span');
        errorMsg.className = 'error-message';
        group.appendChild(errorMsg);
    }
    errorMsg.textContent = message;
}

function clearError(input) {
    input.closest('.form-group').classList.remove('error');
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

async function handleSubmit(e) {
    e.preventDefault();

    if (!validateStep(3)) return;

    const submitBtn = document.getElementById('submitBtn');
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;

    const formData = new FormData(e.target);

    try {
        const response = await fetch('php/book.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            document.getElementById('successModal').classList.add('active');
        } else {
            showToast(result.message || 'Booking failed. Please try again.', 'error');
        }
    } catch (error) {
        // For demo without PHP, show success modal
        document.getElementById('successModal').classList.add('active');
    } finally {
        submitBtn.classList.remove('loading');
        submitBtn.disabled = false;
    }
}

// ================================
// Service Calculator
// ================================
function initServiceCalculator() {
    const checkboxes = document.querySelectorAll('input[name="services[]"]');
    checkboxes.forEach(cb => cb.addEventListener('change', updateTotalAndDuration));
}

function updateTotalAndDuration() {
    const prices = {
        'Classic Manicure': 500,
        'Gel Extensions': 1500,
        'Nail Art': 800,
        'Spa Pedicure': 700,
        'Acrylic Nails': 1200,
        'Nail Repair': 400
    };

    const checkboxes = document.querySelectorAll('input[name="services[]"]:checked');
    let total = 0;
    let duration = 0;

    checkboxes.forEach(cb => {
        total += prices[cb.value] || 0;
        duration += SERVICE_DURATIONS[cb.value] || 60;
    });

    document.getElementById('totalAmount').textContent = `₹${total.toLocaleString('en-IN')}`;

    // Update duration display
    const durationDisplay = document.getElementById('durationDisplay');
    const durationAmount = document.getElementById('durationAmount');
    if (durationDisplay && durationAmount) {
        if (duration > 0) {
            const hours = Math.floor(duration / 60);
            const mins = duration % 60;
            durationAmount.textContent = hours > 0
                ? `${hours}h ${mins > 0 ? mins + 'min' : ''}`
                : `${mins} min`;
            durationDisplay.style.display = 'flex';
        } else {
            durationDisplay.style.display = 'none';
        }
    }
}

// Keep old function name for compatibility
function updateTotal() {
    updateTotalAndDuration();
}

// ================================
// Date Picker
// ================================
function setMinDate() {
    const dateInput = document.getElementById('preferredDate');
    if (dateInput) {
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        dateInput.min = tomorrow.toISOString().split('T')[0];

        // Set max date to 30 days from now
        const maxDate = new Date(today);
        maxDate.setDate(maxDate.getDate() + 30);
        dateInput.max = maxDate.toISOString().split('T')[0];
    }
}

// ================================
// Toast Notifications
// ================================
function showToast(message, type = 'info') {
    const existingToast = document.querySelector('.toast');
    if (existingToast) existingToast.remove();

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ================================
// Smooth Scroll for Safari
// ================================
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

// ================================
// Real-Time Availability Checker
// ================================
function initAvailabilityChecker() {
    const dateInput = document.getElementById('preferredDate');
    const timeSelect = document.getElementById('preferredTime');
    const staffSelect = document.getElementById('staffId');
    const loadingIndicator = document.getElementById('slotLoading');

    if (!dateInput || !timeSelect) return;

    // Fetch availability when date or staff changes
    dateInput.addEventListener('change', fetchAvailability);
    staffSelect?.addEventListener('change', fetchAvailability);

    async function fetchAvailability() {
        const date = dateInput.value;
        const staffId = staffSelect?.value || '';

        if (!date) {
            timeSelect.innerHTML = '<option value="">Select a date first</option>';
            timeSelect.disabled = true;
            return;
        }

        // Show loading state
        if (loadingIndicator) loadingIndicator.style.display = 'flex';
        timeSelect.disabled = true;
        timeSelect.innerHTML = '<option value="">Loading...</option>';

        try {
            const url = `php/check-availability.php?date=${date}${staffId ? '&staff_id=' + staffId : ''}`;
            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                // Populate staff dropdown if not already done
                if (staffSelect && staffSelect.options.length <= 1 && data.staff) {
                    data.staff.forEach(staff => {
                        const option = document.createElement('option');
                        option.value = staff.id;
                        option.textContent = `${staff.avatar_emoji} ${staff.name} - ${staff.specialty}`;
                        staffSelect.appendChild(option);
                    });
                }

                // Populate time slots
                timeSelect.innerHTML = '<option value="">Select a time slot</option>';

                data.slots.forEach(slot => {
                    const option = document.createElement('option');
                    option.value = slot.time;

                    if (slot.available) {
                        option.textContent = slot.display;
                    } else {
                        option.textContent = `${slot.display} - ${slot.reason || 'Unavailable'}`;
                        option.disabled = true;
                    }

                    timeSelect.appendChild(option);
                });

                timeSelect.disabled = false;
            } else {
                timeSelect.innerHTML = '<option value="">Unable to load slots</option>';
                showToast(data.message || 'Failed to check availability', 'error');
            }
        } catch (error) {
            console.error('Availability check failed:', error);
            // Fallback to static slots if API fails
            timeSelect.innerHTML = `
                <option value="">Select a time slot</option>
                <option value="10:00">10:00 AM</option>
                <option value="11:00">11:00 AM</option>
                <option value="12:00">12:00 PM</option>
                <option value="13:00">1:00 PM</option>
                <option value="14:00">2:00 PM</option>
                <option value="15:00">3:00 PM</option>
                <option value="16:00">4:00 PM</option>
                <option value="17:00">5:00 PM</option>
                <option value="18:00">6:00 PM</option>
            `;
            timeSelect.disabled = false;
        } finally {
            if (loadingIndicator) loadingIndicator.style.display = 'none';
        }
    }
}

// ================================
// Gallery Lightbox
// ================================
function initGalleryLightbox() {
    const galleryItems = document.querySelectorAll('.gallery-item');
    const lightbox = document.getElementById('lightbox');
    const lightboxImage = document.getElementById('lightboxImage');
    const lightboxCaption = document.getElementById('lightboxCaption');
    const closeBtn = document.getElementById('lightboxClose');
    const prevBtn = document.getElementById('lightboxPrev');
    const nextBtn = document.getElementById('lightboxNext');

    if (!lightbox || galleryItems.length === 0) return;

    let currentIndex = 0;
    const galleryData = [];

    // Collect gallery data
    galleryItems.forEach((item, index) => {
        const placeholder = item.querySelector('.gallery-placeholder');
        const emoji = placeholder?.querySelector('span')?.textContent || '💅';
        const bg = placeholder?.style.background || '';

        galleryData.push({
            emoji,
            background: bg,
            caption: `Nail Art Design ${index + 1}`
        });

        item.addEventListener('click', () => openLightbox(index));
    });

    function openLightbox(index) {
        currentIndex = index;
        updateLightboxContent();
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
    }

    function updateLightboxContent() {
        const item = galleryData[currentIndex];
        lightboxImage.style.background = item.background;
        lightboxImage.innerHTML = `<span>${item.emoji}</span>`;
        lightboxCaption.textContent = item.caption;
    }

    function showPrev() {
        currentIndex = (currentIndex - 1 + galleryData.length) % galleryData.length;
        updateLightboxContent();
    }

    function showNext() {
        currentIndex = (currentIndex + 1) % galleryData.length;
        updateLightboxContent();
    }

    // Event listeners
    closeBtn?.addEventListener('click', closeLightbox);
    prevBtn?.addEventListener('click', showPrev);
    nextBtn?.addEventListener('click', showNext);

    // Close on background click
    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) closeLightbox();
    });

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (!lightbox.classList.contains('active')) return;

        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') showPrev();
        if (e.key === 'ArrowRight') showNext();
    });
}

// ================================
// Mobile Floating Action Button
// ================================
function initMobileFAB() {
    const fab = document.getElementById('mobileFab');
    const bookingSection = document.getElementById('booking');
    const heroSection = document.getElementById('home');

    if (!fab) return;

    function updateFABVisibility() {
        const scrollY = window.scrollY;
        const heroBottom = heroSection?.offsetTop + heroSection?.offsetHeight || 500;
        const bookingTop = bookingSection?.offsetTop || Infinity;
        const bookingBottom = bookingTop + (bookingSection?.offsetHeight || 0);
        const viewportHeight = window.innerHeight;

        // Show FAB after hero, hide when booking section is in view
        const pastHero = scrollY > heroBottom - 100;
        const inBookingSection = scrollY + viewportHeight > bookingTop && scrollY < bookingBottom;

        if (pastHero && !inBookingSection) {
            fab.classList.add('visible');
        } else {
            fab.classList.remove('visible');
        }
    }

    window.addEventListener('scroll', updateFABVisibility, { passive: true });
    updateFABVisibility();
}
