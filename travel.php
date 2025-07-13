<?php
require_once 'config/config.php';
require_once 'includes/secure_auth.php';

$page_title = 'Travel Experiences & Tours';
$meta_description = 'Explore our diverse range of travel experiences including luxury tours, adventure trips, cultural exchanges, and agro-tourism packages across Africa, Caribbean, and beyond.';

// Get filters
$category_filter = $_GET['category'] ?? '';
$country_filter = $_GET['country'] ?? '';
$region_filter = $_GET['region'] ?? '';
$duration_filter = $_GET['duration'] ?? '';
$price_filter = $_GET['price'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'featured';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_conditions = ['t.status = "active"'];
$params = [];

if ($category_filter) {
    $where_conditions[] = 'tc.slug = ?';
    $params[] = $category_filter;
}

if ($country_filter) {
    $where_conditions[] = 'c.name = ?'; // or use id if you're passing ID in the URL
    $params[] = $country_filter;
}


if ($region_filter) {
    $where_conditions[] = 'r.slug = ?';
    $params[] = $region_filter;
}

if ($duration_filter) {
    switch ($duration_filter) {
        case '1-3':
            $where_conditions[] = 't.duration_days BETWEEN 1 AND 3';
            break;
        case '4-7':
            $where_conditions[] = 't.duration_days BETWEEN 4 AND 7';
            break;
        case '8-14':
            $where_conditions[] = 't.duration_days BETWEEN 8 AND 14';
            break;
        case '15+':
            $where_conditions[] = 't.duration_days >= 15';
            break;
    }
}

if ($price_filter) {
    switch ($price_filter) {
        case '0-1000':
            $where_conditions[] = 't.price_from <= 1000';
            break;
        case '1000-3000':
            $where_conditions[] = 't.price_from BETWEEN 1000 AND 3000';
            break;
        case '3000-5000':
            $where_conditions[] = 't.price_from BETWEEN 3000 AND 5000';
            break;
        case '5000+':
            $where_conditions[] = 't.price_from >= 5000';
            break;
    }
}

if ($search) {
    $where_conditions[] = '(t.title LIKE ? OR t.description LIKE ? OR c.name LIKE ?)';
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(' AND ', $where_conditions);

// Determine sort order
$order_clause = 'ORDER BY ';
switch ($sort) {
    case 'price_low':
        $order_clause .= 't.price_from ASC';
        break;
    case 'price_high':
        $order_clause .= 't.price_from DESC';
        break;
    case 'duration':
        $order_clause .= 't.duration_days ASC';
        break;
    case 'newest':
        $order_clause .= 't.created_at DESC';
        break;
    case 'popular':
        $order_clause .= 'booking_count DESC';
        break;
    default: // featured
        $order_clause .= 't.is_featured DESC, t.created_at DESC';
        break;
}

// Get total count
$count_query = "
    SELECT COUNT(DISTINCT t.id)
    FROM tours t
    LEFT JOIN tour_categories tc ON t.category_id = tc.id
    LEFT JOIN countries c ON t.country_id = c.id
    LEFT JOIN regions r ON t.region_id = r.id
    WHERE $where_clause
";

$total_stmt = $db->prepare($count_query);
$total_stmt->execute($params);
$total_tours = $total_stmt->fetchColumn();

// Get tours
$tours_query = "
    SELECT t.*, tc.name as category_name, tc.slug as category_slug,
           c.name as country_name, c.slug as country_slug,
           r.name as region_name, r.slug as region_slug,
           (SELECT COUNT(*) FROM bookings WHERE tour_id = t.id AND status IN ('confirmed', 'completed')) as booking_count,
           (SELECT AVG(rating) FROM tour_reviews WHERE tour_id = t.id AND status = 'approved') as avg_rating,
           (SELECT COUNT(*) FROM tour_reviews WHERE tour_id = t.id AND status = 'approved') as review_count
    FROM tours t
    LEFT JOIN tour_categories tc ON t.category_id = tc.id
    LEFT JOIN countries c ON t.country_id = c.id
    LEFT JOIN regions r ON t.region_id = r.id
    WHERE $where_clause
    $order_clause
    LIMIT $per_page OFFSET $offset
";

$tours_stmt = $db->prepare($tours_query);
$tours_stmt->execute($params);
$tours = $tours_stmt->fetchAll();

$total_pages = ceil($total_tours / $per_page);

// Get filter options
$categories = $db->query("
    SELECT tc.*, COUNT(t.id) as tour_count
    FROM tour_categories tc
    LEFT JOIN tours t ON tc.id = t.category_id AND t.status = 'active'
    WHERE tc.status = 'active'
    GROUP BY tc.id
    ORDER BY tc.name
")->fetchAll();

$countries = $db->query("
    SELECT c.*, COUNT(t.id) as tour_count
    FROM countries c
    LEFT JOIN tours t ON c.id = t.country_id AND t.status = 'active'
    WHERE c.status = 'active'
    GROUP BY c.id
    HAVING tour_count > 0
    ORDER BY c.name
")->fetchAll();

$regions = $db->query("
    SELECT r.*, COUNT(t.id) as tour_count
    FROM regions r
    LEFT JOIN countries c ON r.id = c.region_id
    LEFT JOIN tours t ON c.id = t.country_id AND t.status = 'active'
    WHERE r.status = 'active'
    GROUP BY r.id
    HAVING tour_count > 0
    ORDER BY r.name
")->fetchAll();

// Get featured tours for hero section
$featured_tours = $db->query("
    SELECT t.*, c.name as country_name
    FROM tours t
    LEFT JOIN countries c ON t.country_id = c.id
    WHERE t.status = 'active' AND t.is_featured = 1
    ORDER BY t.created_at DESC
    LIMIT 3
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $page_title; ?> - Forever Young Tours
    </title>
    <meta name="description" content="<?php echo $meta_description; ?>">

    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo $page_title; ?>">
    <meta property=" og:description" content="<?php echo $meta_description; ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo getCurrentUrl(); ?>">

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/travel.css">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

</head>

<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="travel-hero">
        <div class="container">
            <h1>Discover Amazing Experiences</h1>
            <p style="color: white;">From luxury getaways to cultural adventures, explore our curated collection of
                unforgettable travel
                experiences across Africa, Caribbean, and beyond</p>
        </div>
    </section>

    <!-- Booking Tabs -->
    <div class="container booking-tabs-container">
        <h2 class="text-center mb-4">Book Your Trip Now</h2>
        <div class="booking-tabs">
            <ul class="nav nav-tabs" id="bookingTabs">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#cars">Cars</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#cruises">Cruises</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#flights">Flights</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#stays">Stays</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#activities">Activities</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#insure">Insure It</a></li>
            </ul>

            <div class="tab-content">
                <!-- Cars -->
                <div class="tab-pane fade show active" id="cars">
                    <form>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="pickupLocation" class="form-label">Pick-Up Location</label>
                                <input type="text" id="pickupLocation" class="form-control"
                                    placeholder="Pick-Up Location">
                            </div>
                            <div class="col-md-6">
                                <label for="dropoffLocation" class="form-label">Drop-Off Location</label>
                                <input type="text" id="dropoffLocation" class="form-control"
                                    placeholder="Drop-Off Location">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="pickupDate" class="form-label">Pick-Up Date</label>
                                <input type="date" id="pickupDate" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label for="pickupTime" class="form-label">Pick-Up Time</label>
                                <input type="time" id="pickupTime" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="dropoffDate" class="form-label">Drop-Off Date</label>
                                <input type="date" id="dropoffDate" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label for="dropoffTime" class="form-label">Drop-Off Time</label>
                                <input type="time" id="dropoffTime" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="driverAge" class="form-label">Driver's Age</label>
                            <input type="number" id="driverAge" class="form-control" placeholder="Driver's Age">
                        </div>
                        <button class="btn btn-primary w-100">Search and Register Now</button>
                    </form>
                </div>

                <!-- Cruises -->
                <div class="tab-pane fade" id="cruises">
                    <p class="mb-3">User must be logged in to search cruises.</p>
                    <button class="btn btn-primary">Register Now</button>
                </div>

                <!-- Flights -->
                <div class="tab-pane fade" id="flights">
                    <form>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="departureAirport" class="form-label">Departure Airport</label>
                                <input type="text" id="departureAirport" class="form-control"
                                    placeholder="Departure Airport">
                            </div>
                            <div class="col-md-6">
                                <label for="arrivalAirport" class="form-label">Arrival Airport</label>
                                <input type="text" id="arrivalAirport" class="form-control"
                                    placeholder="Arrival Airport">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="departingOn" class="form-label">Departing On</label>
                                <input type="date" id="departingOn" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label for="returningOn" class="form-label">Returning On</label>
                                <input type="date" id="returningOn" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="adults" class="form-label">Adults (12-65 Yrs)</label>
                                <select id="adults" class="form-control">
                                    <option>1</option>
                                    <option>2</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="children" class="form-label">Children (Below 12 Yrs)</label>
                                <select id="children" class="form-control">
                                    <option>0</option>
                                    <option>1</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="seniors" class="form-label">Seniors (65+ Yrs)</label>
                                <select id="seniors" class="form-control">
                                    <option>0</option>
                                    <option>1</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="lapInfants" class="form-label">Lap Infants (Below 2 Yrs)</label>
                                <select id="lapInfants" class="form-control">
                                    <option>0</option>
                                    <option>1</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="cabinClass" class="form-label">Cabin Class</label>
                                <select id="cabinClass" class="form-control">
                                    <option>Economy</option>
                                    <option>Business</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="maxConnections" class="form-label">Max Connections</label>
                                <select id="maxConnections" class="form-control">
                                    <option>Non-stop</option>
                                    <option>1 Stop</option>
                                </select>
                            </div>
                        </div>
                        <button class="btn btn-primary w-100">Search and Register Now</button>
                    </form>
                </div>

                <!-- Stays -->
                <div class="tab-pane fade" id="stays">
                    <form>
                        <div class="mb-3">
                            <label for="hotelCity" class="form-label">City or Hotel Name</label>
                            <input type="text" id="hotelCity" class="form-control" placeholder="City or Hotel Name">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="checkInDate" class="form-label">Check-In Date</label>
                                <input type="date" id="checkInDate" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label for="checkOutDate" class="form-label">Check-Out Date</label>
                                <input type="date" id="checkOutDate" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="numRooms" class="form-label">Number of Rooms</label>
                                <input type="number" id="numRooms" class="form-control" placeholder="Number of Rooms">
                            </div>
                            <div class="col-md-6">
                                <label for="radiusMiles" class="form-label">Radius (Miles)</label>
                                <input type="number" id="radiusMiles" class="form-control" placeholder="Radius (Miles)">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="numAdults" class="form-label">Adults</label>
                                <input type="number" id="numAdults" class="form-control" placeholder="Adults">
                            </div>
                            <div class="col-md-6">
                                <label for="numChildren" class="form-label">Children</label>
                                <input type="number" id="numChildren" class="form-control" placeholder="Children">
                            </div>
                        </div>
                        <button class="btn btn-primary w-100">Search and Register Now</button>
                    </form>
                </div>

                <!-- Activities -->
                <div class="tab-pane fade" id="activities">
                    <form>
                        <div class="mb-3">
                            <label for="activityCity" class="form-label">City or Location</label>
                            <input type="text" id="activityCity" class="form-control" placeholder="City or Location">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="activityStartDate" class="form-label">Start Date</label>
                                <input type="date" id="activityStartDate" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label for="activityEndDate" class="form-label">End Date</label>
                                <input type="date" id="activityEndDate" class="form-control">
                            </div>
                        </div>
                        <button class="btn btn-primary w-100">Search and Register Now</button>
                    </form>
                </div>

                <!-- Insure It -->
                <div class="tab-pane fade" id="insure">
                    <p class="mb-3">Learn more about the importance of travel insurance and get started.</p>
                    <button class="btn btn-primary">Protect Your Trip</button>
                </div>
            </div>
        </div>
    </div>


    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2>Ready for Your Next Adventure?</h2>
            <p>Let us help you create unforgettable memories with our expertly crafted travel experiences</p>
            <div class="cta-buttons">
                <a href="contact.php" class="btn btn-white btn-lg">Plan My Trip</a>
                <a href="book.php" class="btn btn-outline-white btn-lg">Book Now</a>
            </div>
        </div>
    </section>
    <!-- Enhanced Cruise Registration Modal -->
    <div class="modal fade" id="cruiseRegisterModal" tabindex="-1" aria-labelledby="cruiseRegisterModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cruiseRegisterModalLabel">Register as a free user</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="cruiseRegistrationForm">
                        <div class="form-group">
                            <div class="row">
                                <div class="col-md-2">
                                    <label for="salutation" class="form-label required-field">Salutation</label>
                                    <select id="salutation" class="form-select" required>
                                        <option value="">--</option>
                                        <option value="Mr">Mr</option>
                                        <option value="Mrs">Mrs</option>
                                        <option value="Ms">Ms</option>
                                        <option value="Dr">Dr</option>
                                    </select>
                                    <div class="error-message">Please select a salutation</div>
                                </div>
                                <div class="col-md-5">
                                    <label for="firstName" class="form-label required-field">First Name</label>
                                    <input type="text" class="form-control" id="firstName" required>
                                    <div class="error-message">Please enter your first name</div>
                                </div>
                                <div class="col-md-5">
                                    <label for="lastName" class="form-label required-field">Last Name</label>
                                    <input type="text" class="form-control" id="lastName" required>
                                    <div class="error-message">Please enter your last name</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="email" class="form-label required-field">Email</label>
                                    <input type="email" class="form-control" id="email" required>
                                    <div class="error-message">Please enter a valid email address</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="birthDate" class="form-label required-field">Birth Date</label>
                                    <input type="date" class="form-control" id="birthDate" required>
                                    <div class="error-message">Please enter your birth date</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section-heading">Account Security</div>

                        <div class="form-group">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="password" class="form-label required-field">Password</label>
                                    <input type="password" class="form-control" id="password" required>
                                    <div class="password-strength">
                                        <small>Strength: <span id="strengthIndicator">None</span></small>
                                        <div class="progress" style="height: 6px;">
                                            <div id="strengthBar" class="progress-bar" role="progressbar"
                                                style="width: 0%"></div>
                                        </div>
                                    </div>
                                    <div class="error-message">Password must be at least 8 characters</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="confirmPassword" class="form-label required-field">Confirm
                                        Password</label>
                                    <input type="password" class="form-control" id="confirmPassword" required>
                                    <div class="error-message">Passwords must match</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section-heading">Contact Information</div>

                        <div class="form-group">
                            <label for="address" class="form-label">Address</label>
                            <input type="text" class="form-control" id="address">
                        </div>

                        <div class="form-group">
                            <label for="address2" class="form-label">Address 2</label>
                            <input type="text" class="form-control" id="address2">
                        </div>

                        <div class="form-group">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city">
                                </div>
                                <div class="col-md-3">
                                    <label for="state" class="form-label">State/Region</label>
                                    <select id="state" class="form-select">
                                        <option value="">--</option>
                                        <!-- Add states here -->
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="zipCode" class="form-label">Zip Code</label>
                                    <input type="text" class="form-control" id="zipCode">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="country" class="form-label">Country</label>
                            <select id="country" class="form-select">
                                <option value="US" selected>United States</option>
                                <!-- Add countries here -->
                            </select>
                        </div>

                        <div class="form-group">
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="homePhone" class="form-label">Home Phone</label>
                                    <div class="input-group phone-input-group">

                                        <input type="tel" class="form-control" id="homePhone">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label for="workPhone" class="form-label">Work Phone</label>
                                    <div class="input-group phone-input-group">

                                        <input type="tel" class="form-control" id="workPhone">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label for="cellPhone" class="form-label">Cell Phone</label>
                                    <div class="input-group phone-input-group">

                                        <input type="tel" class="form-control" id="cellPhone">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="termsAgreement" required>
                                <label class="form-check-label" for="termsAgreement">
                                    I agree to the <a href="#" class="text-gold">Terms and Conditions</a> and <a
                                        href="#" class="text-gold">Privacy Policy</a>
                                </label>
                                <div class="error-message">You must agree to the terms</div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i> Register Now
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateSort(sortValue) {
            const url = new URL(window.location);
            url.searchParams.set('sort', sortValue);
            window.location.href = url.toString();
        }
        // Initialize modal
        const cruiseRegisterModal = new bootstrap.Modal(document.getElementById('cruiseRegisterModal'));

        // Add click handler for cruise register button
        document.querySelector('#cruises .btn-primary').addEventListener('click', function (e) {
            e.preventDefault();
            cruiseRegisterModal.show();
        });

        // Password strength checker
        document.getElementById('password').addEventListener('input', function () {
            const password = this.value;
            const strengthIndicator = document.getElementById('strengthIndicator');
            const strengthBar = document.getElementById('strengthBar');

            // Reset classes
            strengthIndicator.className = '';
            strengthBar.className = 'progress-bar';

            if (password.length === 0) {
                strengthIndicator.textContent = 'None';
                strengthBar.style.width = '0%';
                strengthBar.classList.add('bg-secondary');
                return;
            }

            // Calculate strength
            let strength = 0;
            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
            if (password.match(/\d/)) strength += 1;
            if (password.match(/[^a-zA-Z\d]/)) strength += 1;

            // Update UI
            if (strength <= 1) {
                strengthIndicator.textContent = 'Weak';
                strengthIndicator.classList.add('strength-weak');
                strengthBar.style.width = '25%';
                strengthBar.classList.add('bg-danger');
            } else if (strength === 2) {
                strengthIndicator.textContent = 'Medium';
                strengthIndicator.classList.add('strength-medium');
                strengthBar.style.width = '50%';
                strengthBar.classList.add('bg-warning');
            } else if (strength === 3) {
                strengthIndicator.textContent = 'Strong';
                strengthIndicator.classList.add('strength-strong');
                strengthBar.style.width = '75%';
                strengthBar.classList.add('bg-success');
            } else {
                strengthIndicator.textContent = 'Very Strong';
                strengthIndicator.classList.add('strength-strong');
                strengthBar.style.width = '100%';
                strengthBar.classList.add('bg-success');
            }
        });

        // Form submission handler
        document.getElementById('cruiseRegistrationForm').addEventListener('submit', function (e) {
            e.preventDefault();

            // Validate passwords match
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return;
            }

            // Here you would typically send the form data to your server
            alert('Registration submitted successfully!');
            cruiseRegisterModal.hide();
        });

        // Update sort functionality
        function updateSort(sortValue) {
            const url = new URL(window.location);
            url.searchParams.set('sort', sortValue);
            window.location.href = url.toString();
        }
    </script>
</body>

</html>