/**
 * Heaven Nails - Main JavaScript
 * Multi-step booking form, validation, and interactions
 */

document.addEventListener('DOMContentLoaded', () => {
    // Initialize all modules
    initNavigation();
    initBookingForm();
    initServiceCalculator();
    setMinDate();
});

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
    checkboxes.forEach(cb => cb.addEventListener('change', updateTotal));
}

function updateTotal() {
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
    checkboxes.forEach(cb => {
        total += prices[cb.value] || 0;
    });

    document.getElementById('totalAmount').textContent = `₹${total.toLocaleString('en-IN')}`;
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
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});
