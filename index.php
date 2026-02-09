<?php
require_once 'php/config.php';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM services WHERE is_active = TRUE ORDER BY name");
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching services: " . $e->getMessage());
    $services = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Heaven Nails - Premium nail salon offering manicures, pedicures, nail art, and luxury treatments. Book your appointment today.">
    <meta name="keywords" content="nail salon, manicure, pedicure, nail art, gel nails, acrylic nails">
    <meta name="author" content="Heaven Nails">
    <title>Heaven Nails | Premium Nail Salon - Book Your Appointment</title>

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://theheavennails.com/">
    <meta property="og:title" content="Heaven Nails | Premium Nail Salon">
    <meta property="og:description"
        content="Indulge in luxury nail care crafted with precision and passion. Where elegance meets artistry in Rajkot.">
    <meta property="og:image" content="https://theheavennails.com/assets/images/logo/logo.png.jpg">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://theheavennails.com/">
    <meta property="twitter:title" content="Heaven Nails | Premium Nail Salon">
    <meta property="twitter:description"
        content="Indulge in luxury nail care crafted with precision and passion. Where elegance meets artistry.">
    <meta property="twitter:image" content="https://theheavennails.com/assets/images/logo/logo.png.jpg">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://theheavennails.com/">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Montserrat:wght@300;400;500;600&display=swap"
        rel="stylesheet">

    <!-- Styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="icon" type="image/jpeg" href="assets/images/logo/logo.png.jpg?v=2">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .logo-img {
            max-height: 50px;
            /* Attempt to clean up JPEG artifacts and blend better */
            filter: brightness(1.05) contrast(1.1);
            mix-blend-mode: multiply;
            display: block;
        }

        .logo-text {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.5rem;
            font-weight: 600;
            color: #2d2d2d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .logo-text span {
            color: #c9a66b;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="#" class="logo">
                <img src="assets/images/logo/logo.png.jpg" alt="Heaven Nails" class="logo-img">
                <span class="logo-text">The Heaven <span>Nails</span></span>
            </a>
            <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
                <span class="hamburger"></span>
            </button>
            <ul class="nav-menu" id="navMenu">
                <li><a href="#home" class="nav-link">Home</a></li>
                <li><a href="#services" class="nav-link">Services</a></li>
                <li><a href="#booking" class="nav-link">Book Now</a></li>
                <li><a href="#gallery" class="nav-link">Gallery</a></li>
                <li><a href="#contact" class="nav-link">Contact</a></li>
            </ul>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <span class="hero-tagline">Premium Nail Experience</span>
            <h1 class="hero-title">Elevate Your <span class="text-accent">Beauty</span></h1>
            <p class="hero-description">Indulge in luxury nail care crafted with precision and passion. Where elegance
                meets artistry.</p>
            <a href="#booking" class="btn btn-primary">Book Appointment</a>
        </div>
        <div class="hero-scroll">
            <span>Scroll</span>
            <div class="scroll-line"></div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services" id="services">
        <div class="container">
            <div class="section-header">
                <span class="section-tagline">What We Offer</span>
                <h2 class="section-title">Our Services</h2>
            </div>
            <div class="services-grid">
                <?php if (empty($services)): ?>
                    <p>No services currently available.</p>
                <?php else: ?>
                    <?php foreach ($services as $svc): ?>
                    <div class="service-card">
                        <div class="service-icon"><i class="<?= htmlspecialchars($svc['icon_class']) ?>"></i></div>
                        <h3 class="service-title"><?= htmlspecialchars($svc['name']) ?></h3>
                        <p class="service-desc"><?= htmlspecialchars($svc['description']) ?></p>
                        <span class="service-price">₹<?= number_format($svc['price']) ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials" id="testimonials">
        <div class="container">
            <div class="section-header">
                <span class="section-tagline">What Clients Say</span>
                <h2 class="section-title">Reviews</h2>
            </div>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-stars">
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                            class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                            class="fa-solid fa-star"></i>
                    </div>
                    <p class="testimonial-text">"Absolutely stunning gel extensions! Priya is incredibly talented and
                        the salon ambiance is so relaxing. My go-to place for special occasions."</p>
                    <div class="testimonial-author">
                        <span class="author-name">Meera S.</span>
                        <span class="author-service">Gel Extensions</span>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-stars">★★★★★</div>
                    <p class="testimonial-text">"Best nail art in the city! They listened to my ideas and created
                        something even better. Super hygienic and professional team."</p>
                    <div class="testimonial-author">
                        <span class="author-name">Anjali R.</span>
                        <span class="author-service">Nail Art</span>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-stars">★★★★★</div>
                    <p class="testimonial-text">"The spa pedicure was heavenly! Perfect for tired feet after a long
                        week. Will definitely be coming back regularly."</p>
                    <div class="testimonial-author">
                        <span class="author-name">Kavitha M.</span>
                        <span class="author-service">Spa Pedicure</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="booking" id="booking">
        <div class="container">
            <div class="section-header">
                <span class="section-tagline">Reserve Your Spot</span>
                <h2 class="section-title">Book Appointment</h2>
            </div>
            <div class="booking-wrapper">
                <form class="booking-form" id="bookingForm" action="php/book.php" method="POST">
                    <!-- Step 1: Services -->
                    <div class="form-step active" data-step="1">
                        <h3 class="step-title">Select Services</h3>
                        <div class="services-select">
                            <?php if (empty($services)): ?>
                                <p>No services available to book.</p>
                            <?php else: ?>
                                <?php foreach ($services as $svc): ?>
                                <label class="service-checkbox">
                                    <input type="checkbox" name="services[]" value="<?= htmlspecialchars($svc['name']) ?>">
                                    <span class="checkbox-custom"></span>
                                    <span class="checkbox-label"><?= htmlspecialchars($svc['name']) ?> <span class="price">₹<?= number_format($svc['price']) ?></span></span>
                                </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="selected-total">
                            <span>Selected Total:</span>
                            <span class="total-amount" id="totalAmount">₹0</span>
                        </div>
                        <div class="duration-display" id="durationDisplay" style="display: none;">
                            <span>Estimated Time:</span>
                            <span class="duration-amount" id="durationAmount">0 min</span>
                        </div>
                        <button type="button" class="btn btn-primary btn-next" data-next="2">Continue</button>
                    </div>

                    <!-- Honeypot field for spam protection -->
                    <input type="text" name="website" class="honeypot-field" tabindex="-1" autocomplete="off">
                    <!-- Step 2: Date, Time & Staff -->
                    <div class="form-step" data-step="2">
                        <h3 class="step-title">Choose Date, Time & Artist</h3>
                        <div class="form-group">
                            <label for="preferredDate">Preferred Date</label>
                            <input type="date" id="preferredDate" name="preferred_date" required>
                        </div>

                        <div class="form-group">
                            <label for="preferredTime">Preferred Time</label>
                            <select id="preferredTime" name="preferred_time" required disabled>
                                <option value="">Select a date first</option>
                            </select>
                            <div class="slot-loading" id="slotLoading" style="display: none;">
                                <span class="loader-small"></span> Checking availability...
                            </div>
                        </div>
                        <div class="form-buttons">
                            <button type="button" class="btn btn-secondary btn-prev" data-prev="1">Back</button>
                            <button type="button" class="btn btn-primary btn-next" data-next="3">Continue</button>
                        </div>
                    </div>

                    <!-- Step 3: Personal Info -->
                    <div class="form-step" data-step="3">
                        <h3 class="step-title">Your Details</h3>
                        <div class="form-group">
                            <label for="clientName">Full Name</label>
                            <input type="text" id="clientName" name="client_name" placeholder="Enter your name"
                                required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" placeholder="your@email.com" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" placeholder="+91 98765 43210" required>
                        </div>
                        <div class="form-group">
                            <label for="notes">Special Requests (Optional)</label>
                            <textarea id="notes" name="notes" rows="3"
                                placeholder="Any preferences or requests..."></textarea>
                        </div>
                        <div class="form-buttons">
                            <button type="button" class="btn btn-secondary btn-prev" data-prev="2">Back</button>
                            <button type="submit" class="btn btn-primary btn-submit" id="submitBtn">
                                <span class="btn-text">Confirm Booking</span>
                                <span class="btn-loader"></span>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Progress Indicator -->
                <div class="form-progress">
                    <div class="progress-step active" data-step="1">
                        <span class="step-number">1</span>
                        <span class="step-label">Services</span>
                    </div>
                    <div class="progress-line"></div>
                    <div class="progress-step" data-step="2">
                        <span class="step-number">2</span>
                        <span class="step-label">Date</span>
                    </div>
                    <div class="progress-line"></div>
                    <div class="progress-step" data-step="3">
                        <span class="step-number">3</span>
                        <span class="step-label">Details</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Success Modal -->
    <div class="modal" id="successModal">
        <div class="modal-content">
            <div class="modal-icon"><i class="fa-regular fa-circle-check"></i></div>
            <h3 class="modal-title">Booking Received!</h3>
            <p class="modal-text">Thank you for choosing Heaven Nails. Your appointment is pending confirmation. We'll
                contact you shortly.</p>
            <button class="btn btn-primary" id="closeModal">Done</button>
        </div>
    </div>

    <!-- Gallery Section -->
    <section class="gallery" id="gallery">
        <div class="container">
            <div class="section-header">
                <span class="section-tagline">Our Work</span>
                <h2 class="section-title">Gallery</h2>
            </div>
            <div class="gallery-grid">
                <div class="gallery-item">
                    <div class="gallery-placeholder"
                        style="background: linear-gradient(135deg, #f5e6d3 0%, #e8d5c4 100%);">
                        <span><i class="fa-solid fa-hand-sparkles"></i></span>
                    </div>
                </div>
                <div class="gallery-item">
                    <div class="gallery-placeholder"
                        style="background: linear-gradient(135deg, #d4a574 0%, #c49a6c 100%);">
                        <span><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                    </div>
                </div>
                <div class="gallery-item">
                    <div class="gallery-placeholder"
                        style="background: linear-gradient(135deg, #f0e4d7 0%, #e5d4c3 100%);">
                        <span><i class="fa-solid fa-palette"></i></span>
                    </div>
                </div>
                <div class="gallery-item large">
                    <div class="gallery-placeholder"
                        style="background: linear-gradient(135deg, #c9a66b 0%, #b8956a 100%);">
                        <span><i class="fa-regular fa-gem"></i></span>
                    </div>
                </div>
                <div class="gallery-item">
                    <div class="gallery-placeholder"
                        style="background: linear-gradient(135deg, #e8dcd0 0%, #dbd1c5 100%);">
                        <span><i class="fa-solid fa-spa"></i></span>
                    </div>
                </div>
                <div class="gallery-item">
                    <div class="gallery-placeholder"
                        style="background: linear-gradient(135deg, #d8c4a8 0%, #cbba9f 100%);">
                        <span><i class="fa-regular fa-star"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact" id="contact">
        <div class="container">
            <div class="contact-wrapper">
                <div class="contact-info">
                    <div class="section-header">
                        <span class="section-tagline">Get In Touch</span>
                        <h2 class="section-title">Visit Us</h2>
                    </div>
                    <div class="contact-details">
                        <div class="contact-item">
                            <span class="contact-icon"><i class="fa-solid fa-location-dot"></i></span>
                            <div class="contact-text">
                                <h4>Address</h4>
                                <p>Shree Ram Society 5-A<br>Rajkot, Gujarat</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <span class="contact-icon"><i class="fa-solid fa-phone"></i></span>
                            <div class="contact-text">
                                <h4>Phone</h4>
                                <a href="tel:+91 93164 58160" class="phone-link">+91 93164 58160</a>
                            </div>
                        </div>
                        <div class="contact-item">
                            <span class="contact-icon"><i class="fa-regular fa-clock"></i></span>
                            <div class="contact-text">
                                <h4>Hours</h4>
                                <p>Mon - Sun: 8:00 AM - 10:00 PM</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <span class="contact-icon"><i class="fa-regular fa-envelope"></i></span>
                            <div class="contact-text">
                                <h4>Email</h4>
                                <a href="mailto:businesstheheavennails@gmail.com">businesstheheavennails@gmail.com</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="contact-map">
                    <div class="map-placeholder" id="map">
                        <iframe
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3691.5!2d70.7833!3d22.3!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMjLCsDE4JzAwLjAiTiA3MMKwNDcnMDAuMCJF!5e0!3m2!1sen!2sin!4v1707138000000"
                            width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>
                    <a href="https://maps.app.goo.gl/sL6c3vtcnqBhm7os8" target="_blank"
                        class="btn btn-secondary map-btn">
                        Open in Google Maps
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <a href="#" class="nav-logo">
                        <span class="logo-text">Heaven</span>
                        <span class="logo-accent">Nails</span>
                    </a>
                    <p class="footer-desc">Where elegance meets artistry. Transform your nails into works of art.</p>
                </div>
                <div class="footer-links">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#booking">Book Now</a></li>
                        <li><a href="#gallery">Gallery</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-social">
                    <h4>Follow Us</h4>
                    <div class="social-links">
                        <a href="https://www.instagram.com/the_heaven_nail_/" class="social-link" aria-label="Instagram"
                            target="_blank"><i class="fa-brands fa-instagram"></i></a>
                        <a href="#" class="social-link" aria-label="Facebook"><i class="fa-brands fa-facebook"></i></a>
                        <a href="https://wa.me/919316458160" class="social-link" aria-label="WhatsApp"><i
                                class="fa-brands fa-whatsapp"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 Heaven Nails. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="js/main.js"></script>

    <!-- Gallery Lightbox Modal -->
    <div class="lightbox" id="lightbox">
        <button class="lightbox-close" id="lightboxClose" aria-label="Close">&times;</button>
        <button class="lightbox-prev" id="lightboxPrev" aria-label="Previous">&#10094;</button>
        <div class="lightbox-content">
            <div class="lightbox-image" id="lightboxImage"></div>
            <p class="lightbox-caption" id="lightboxCaption"></p>
        </div>
        <button class="lightbox-next" id="lightboxNext" aria-label="Next">&#10095;</button>
    </div>

    <!-- Mobile Floating Action Button -->
    <a href="#booking" class="mobile-fab" id="mobileFab" aria-label="Book Now">
        <span class="fab-icon"><i class="fa-solid fa-calendar-check"></i></span>
        <span class="fab-text">Book</span>
    </a>
</body>

</html>
