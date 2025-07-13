<?php
// ====================[ GET FOOTER DATA FROM DATABASE ]====================
try {
    $footer_content = $db->query("
        SELECT setting_key, setting_value 
        FROM system_settings 
        WHERE setting_key IN ('contact_email', 'contact_phone', 'contact_address', 'social_facebook', 'social_twitter', 'social_instagram', 'social_youtube', 'company_description')
    ")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $footer_content = [];
}

$company_description = $footer_content['company_description'] ?? 'Discover the world with our luxury group travel experiences. From adventure tours to cultural exchanges, we create unforgettable journeys that connect you with amazing destinations and fellow travelers.';
$contact_address = $footer_content['contact_address'] ?? 'Kigali, Rwanda';
$contact_phone = $footer_content['contact_phone'] ?? '+250 788 123 456';
$contact_email = $footer_content['contact_email'] ?? 'info@foreveryoungtours.com';
$clean_phone = preg_replace('/\D+/', '', $contact_phone);
$current_year = date('Y');
?>

<style>
    /* ====================[ FOOTER CSS ]==================== */
    .footer {
        background-color: var(--black-primary);
        color: var(--white-primary);
        padding: 5rem 0 2.5rem;
        font-family: 'Inter', Arial, sans-serif;
        position: relative;
        line-height: 1.6;
    }

    .footer-container {
        max-width: 1300px;
        margin: 0 auto;
        padding: 0 2rem;
    }

    .footer-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 3rem;
        margin-bottom: 3rem;
    }

    .footer-section {
        margin-bottom: 2rem;
    }

    .footer-section h3 {
        color: var(--gold-primary);
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
        position: relative;
        padding-bottom: 0.75rem;
        font-weight: 600;
    }

    .footer-section h3::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 3rem;
        height: 2px;
        background: var(--gold-primary);
    }

    .footer-logo {
        display: flex;
        align-items: center;
        margin-bottom: 1.5rem;
        gap: 1rem;
    }

    .footer-logo img {
        height: 3rem;
        width: auto;
    }

    .footer-logo-text h2 {
        margin: 0;
        font-size: 1.75rem;
        color: var(--gold-primary);
        line-height: 1.2;
    }

    .footer-logo-text p {
        margin: 0.5rem 0 0;
        color: var(--black-lighter);
        font-style: italic;
        font-size: 0.9rem;
    }

    .footer-description {
        margin-bottom: 1.5rem;
        color: var(--black-lighter);
    }

    /* Newsletter Form */
    .footer-newsletter {
        margin-bottom: 2rem;
    }

    .newsletter-form {
        display: flex;
        margin-top: 1rem;
    }

    .newsletter-input {
        flex: 1;
        padding: 0.75rem 1.25rem;
        border: none;
        border-radius: 4px 0 0 4px;
        font-size: 1rem;
        background: rgba(255, 255, 255, 0.95);
        color: var(--black-primary);
    }

    .newsletter-button {
        padding: 0 1.5rem;
        background-color: var(--gold-primary);
        color: var(--black-primary);
        border: none;
        border-radius: 0 4px 4px 0;
        cursor: pointer;
        font-weight: 600;
        transition: var(--transition);
    }

    .newsletter-button:hover {
        background-color: var(--gold-dark);
    }

    /* Social Links */
    .social-links {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .social-link {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.1);
        color: var(--white-primary);
        font-size: 1.1rem;
        transition: var(--transition);
    }

    .social-link:hover {
        background-color: var(--gold-primary);
        color: var(--black-primary);
        transform: translateY(-3px);
    }

    /* Footer Links */
    .footer-links {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .footer-links li {
        margin-bottom: 0.75rem;
    }

    .footer-links a {
        color: var(--white-primary);
        text-decoration: none;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .footer-links a:hover {
        color: var(--gold-primary);
        padding-left: 0.25rem;
    }

    .footer-links i {
        color: var(--gold-primary);
        font-size: 0.9rem;
    }

    /* Contact Info */
    .contact-info {
        margin-top: 1.5rem;
    }

    .contact-item {
        display: flex;
        margin-bottom: 1rem;
        align-items: flex-start;
    }

    .contact-icon {
        color: var(--gold-primary);
        margin-right: 0.75rem;
        font-size: 1.1rem;
        margin-top: 0.2rem;
    }

    .contact-text {
        flex: 1;
        color: var(--white-primary);
    }

    .contact-text a {
        color: var(--white-primary);
        text-decoration: none;
        transition: var(--transition);
    }

    .contact-text a:hover {
        color: var(--gold-primary);
    }

    /* Footer Bottom */
    .footer-bottom {
        border-top: 1px solid var(--black-light);
        padding-top: 2rem;
        text-align: center;
    }

    .footer-bottom p {
        margin: 0.5rem 0;
        color: var(--black-lighter);
        font-size: 0.9rem;
    }

    .footer-bottom-links {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 1rem;
        margin: 1rem 0;
    }

    .footer-bottom-links a {
        color: var(--white-primary);
        text-decoration: none;
        transition: var(--transition);
        font-size: 0.9rem;
    }

    .footer-bottom-links a:hover {
        color: var(--gold-primary);
    }

    .footer-heart {
        color: var(--green-accent);
        animation: heartbeat 1.5s infinite;
        display: inline-block;
    }

    /* Payment Methods */
    .payment-methods {
        margin-top: 1.5rem;
    }

    .payment-methods h5 {
        color: var(--black-lighter);
        font-size: 0.85rem;
        margin-bottom: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .payment-icons {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .payment-icons i {
        font-size: 1.75rem;
        color: var(--black-lighter);
        transition: var(--transition);
    }

    .payment-icons i:hover {
        color: var(--gold-primary);
    }

    /* Back to Top Button */
    .back-to-top {
        position: fixed;
        bottom: 5rem;
        right: 2rem;
        background-color: var(--gold-primary);
        color: var(--black-primary);
        border: none;
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        box-shadow: var(--shadow-gold);
        transition: var(--transition);
        z-index: 999;
    }

    .back-to-top:hover {
        background-color: var(--gold-dark);
        transform: translateY(-0.25rem);
    }

    .back-to-top.visible {
        display: flex;
    }

    /* WhatsApp Button */
    .whatsapp-float {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        z-index: 999;
    }

    .whatsapp-button {
        background-color: var(--green-accent);
        color: var(--white-primary);
        padding: 0.75rem 1.5rem;
        border-radius: 2rem;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 500;
        box-shadow: 0 0.25rem 0.75rem rgba(34, 139, 34, 0.3);
        transition: var(--transition);
        animation: float 3s ease-in-out infinite;
    }

    .whatsapp-button:hover {
        background-color: var(--green-dark);
        transform: translateY(-0.25rem);
        box-shadow: 0 0.5rem 1rem rgba(34, 139, 34, 0.4);
    }

    .whatsapp-icon {
        font-size: 1.5rem;
    }

    .whatsapp-flag {
        position: absolute;
        top: -0.5rem;
        right: -0.5rem;
        background-color: var(--green-light);
        color: var(--white-primary);
        font-size: 0.75rem;
        font-weight: bold;
        padding: 0.25rem 0.5rem;
        border-radius: 1rem;
        animation: pulse 2s infinite;
    }

    /* Animations */
    @keyframes heartbeat {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.2);
        }
    }

    @keyframes float {

        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-0.5rem);
        }
    }

    @keyframes pulse {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }
    }

    /* Responsive Styles */
    @media (max-width: 992px) {
        .footer-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
        }

        .footer-section {
            margin-bottom: 1.5rem;
        }
    }

    @media (max-width: 768px) {
        .footer {
            padding: 3rem 0 2rem;
        }

        .footer-grid {
            grid-template-columns: 1fr;
            gap: 2.5rem;
        }

        .footer-container {
            padding: 0 1.5rem;
        }

        .whatsapp-button span {
            display: none;
        }

        .whatsapp-button {
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            padding: 0;
            justify-content: center;
        }

        .back-to-top {
            bottom: 4rem;
            right: 1.5rem;
            width: 2.5rem;
            height: 2.5rem;
            font-size: 1rem;
        }
    }

    @media (max-width: 480px) {
        .footer {
            padding: 2.5rem 0 1.5rem;
        }

        .footer-logo {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .newsletter-form {
            flex-direction: column;
        }

        .newsletter-input {
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }

        .newsletter-button {
            border-radius: 4px;
            padding: 0.75rem;
        }
    }
</style>

<!-- ====================[ FOOTER SECTION START ]==================== -->
<footer class="footer">
    <div class="footer-container">
        <div class="footer-grid">
            <!-- 1. Company Info -->
            <div class="footer-section">
                <div class="footer-logo">
                    <img src="assets/images/logo.png" alt="Logo" style="height: 60px; margin-right: 10px;">
                    <div class="footer-logo-text">
                        <h3>Forever Young Tours</h3>
                        <p>Travel Bold. Stay Forever Young.</p>
                    </div>
                </div>
                <p class="footer-description"><?= htmlspecialchars($company_description); ?></p>

                <div class="payment-methods">
                    <h5>We Accept:</h5>
                    <div class="payment-icons">
                        <i class="fab fa-cc-visa"></i>
                        <i class="fab fa-cc-mastercard"></i>
                        <i class="fab fa-cc-paypal"></i>
                        <i class="fab fa-cc-apple-pay"></i>
                    </div>
                </div>
            </div>



            <!-- 3. Travel Services -->
            <div class="footer-section">
                <h4>Travel Services</h4>
                <ul class="footer-links">
                    <li><a href="/webtour/tours.php?category=adventure"><i class="fas fa-mountain"></i> Adventure
                            Tours</a></li>
                    <li><a href="/webtour/tours.php?category=luxury"><i class="fas fa-crown"></i> Luxury Travel</a></li>
                    <li><a href="/webtour/tours.php?category=cultural"><i class="fas fa-theater-masks"></i> Cultural
                            Tours</a>
                    </li>
                    <li><a href="/webtour/tours.php?category=wildlife"><i class="fas fa-paw"></i> Wildlife Safari</a>
                    </li>
                    <li><a href="/webtour/tours.php?category=agro"><i class="fas fa-seedling"></i> Agro-Tourism</a></li>
                    <li><a href="/webtour/store.php"><i class="fas fa-shopping-bag"></i> Travel Store</a></li>
                </ul>
            </div>

            <!-- 4. Resources -->
            <div class="footer-section">
                <h4>Resources</h4>
                <ul class="footer-links">
                    <li><a href="/webtour/resources.php"><i class="fas fa-book"></i> Travel Resources</a></li>
                    <li><a href="/webtour/resources.php#visa"><i class="fas fa-passport"></i> Visa Information</a></li>
                    <li><a href="/webtour/resources.php#safety"><i class="fas fa-shield-alt"></i> Travel Safety</a></li>
                    <li><a href="/webtour/faq.php"><i class="fas fa-question-circle"></i> FAQ</a></li>
                    <li><a href="/webtour/terms.php"><i class="fas fa-file-contract"></i> Terms & Conditions</a></li>
                    <li><a href="/webtour/privacy.php"><i class="fas fa-user-shield"></i> Privacy Policy</a></li>
                </ul>
            </div>

            <!-- 5. Contact Info -->
            <div class="footer-section">
                <div class="contact-section">
                    <h4 class="contact-title">Contact Info</h4>

                    <div class="contact-details">
                        <div class="contact-item">
                            <div class="contact-label">Location:</div>
                            <div class="contact-value">Kigali, Rwanda</div>
                        </div>

                        <div class="contact-item">
                            <div class="contact-label">Phone:</div>
                            <div class="contact-value">
                                <a href="tel:+250123456789">+250 123 456 789</a>
                            </div>
                        </div>

                        <div class="contact-item">
                            <div class="contact-label">Email:</div>
                            <div class="contact-value">
                                <a href="mailto:info@foreveryoungtours.com">info@foreveryoungtours.com</a>
                            </div>
                        </div>
                    </div>


                </div>

                <h4 style="margin-top: 25px;">Stay Connected</h4>
                <form class="newsletter-form">
                    <input type="email" placeholder="Your email" class="newsletter-input" required>
                    <button type="submit" class="newsletter-button"><i class="fas fa-paper-plane"></i></button>
                </form>

                <div class="social-links">
                    <?php if (!empty($footer_content['social_facebook'])): ?>
                        <a href="<?= htmlspecialchars($footer_content['social_facebook']); ?>" class="social-link"
                            target="_blank"><i class="fab fa-facebook-f"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($footer_content['social_twitter'])): ?>
                        <a href="<?= htmlspecialchars($footer_content['social_twitter']); ?>" class="social-link"
                            target="_blank"><i class="fab fa-twitter"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($footer_content['social_instagram'])): ?>
                        <a href="<?= htmlspecialchars($footer_content['social_instagram']); ?>" class="social-link"
                            target="_blank"><i class="fab fa-instagram"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($footer_content['social_youtube'])): ?>
                        <a href="<?= htmlspecialchars($footer_content['social_youtube']); ?>" class="social-link"
                            target="_blank"><i class="fab fa-youtube"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <p>&copy; <?= $current_year; ?> Forever Young Tours Ltd. All rights reserved.</p>
            <p>
                <a href="/terms.php">Terms of Service</a> |
                <a href="/privacy.php">Privacy Policy</a> |
                <a href="/sitemap.php">Sitemap</a>
            </p>
            <p>Designed with <i class="fas fa-heart footer-heart"></i> in Rwanda</p>
        </div>
    </div>

    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop"><i class="fas fa-chevron-up"></i></button>

    <!-- WhatsApp Floating Button -->
    <div class="whatsapp-float">
        <a href="https://wa.me/<?= $clean_phone; ?>" class="whatsapp-button" target="_blank">
            <i class="fab fa-whatsapp"></i>
            <span>Chat with us</span>
            <div class="whatsapp-flag">Live Chat</div>
        </a>
    </div>
</footer>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Back to Top Button
        const backToTop = document.getElementById('backToTop');
        window.addEventListener('scroll', function () {
            if (window.scrollY > 300) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        });

        backToTop.addEventListener('click', function () {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Newsletter Form
        const newsletterForm = document.querySelector('.newsletter-form');
        if (newsletterForm) {
            newsletterForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const email = this.querySelector('input[type="email"]').value;
                const button = this.querySelector('button');
                const originalContent = button.innerHTML;

                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                button.disabled = true;

                // Simulate API call
                setTimeout(function () {
                    alert('Thank you for subscribing!');
                    newsletterForm.reset();
                    button.innerHTML = originalContent;
                    button.disabled = false;
                }, 1000);
            });
        }

        // WhatsApp flag animation
        const whatsappFlag = document.querySelector('.whatsapp-flag');
        if (whatsappFlag) {
            setInterval(function () {
                whatsappFlag.style.display = whatsappFlag.style.display === 'none' ? 'block' : 'none';
            }, 3000);
        }
    });
</script>