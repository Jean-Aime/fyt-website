// Forever Young Tours - Main JavaScript
// Enhanced with modern features and animations

document.addEventListener("DOMContentLoaded", () => {
  // Initialize all components
  initNavbar()
  initHero()
  initAnimations()
  initForms()
  initModals()
  initCarousels()
  initLazyLoading()
  initScrollEffects()
  initTooltips()
  initSearchFunctionality()
})

// Navbar functionality
function initNavbar() {
  const navbar = document.querySelector(".navbar")
  const navbarToggler = document.querySelector(".navbar-toggler")
  const navbarCollapse = document.querySelector(".navbar-collapse")
  const navLinks = document.querySelectorAll(".nav-link")

  // Navbar scroll effect
  window.addEventListener("scroll", () => {
    if (window.scrollY > 50) {
      navbar.classList.add("scrolled")
    } else {
      navbar.classList.remove("scrolled")
    }
  })

  // Mobile menu toggle
  if (navbarToggler) {
    navbarToggler.addEventListener("click", () => {
      navbarCollapse.classList.toggle("show")
    })
  }

  // Active nav link highlighting
  navLinks.forEach((link) => {
    link.addEventListener("click", function () {
      navLinks.forEach((l) => l.classList.remove("active"))
      this.classList.add("active")
    })
  })

  // Close mobile menu when clicking outside
  document.addEventListener("click", (e) => {
    if (!navbar.contains(e.target) && navbarCollapse.classList.contains("show")) {
      navbarCollapse.classList.remove("show")
    }
  })
}

// Hero section functionality
function initHero() {
  const heroScroll = document.querySelector(".hero-scroll")
  const heroVideo = document.querySelector(".hero-video")

  // Smooth scroll to next section
  if (heroScroll) {
    heroScroll.addEventListener("click", () => {
      const nextSection = document.querySelector(".section")
      if (nextSection) {
        nextSection.scrollIntoView({
          behavior: "smooth",
          block: "start",
        })
      }
    })
  }

  // Video autoplay with fallback
  if (heroVideo) {
    heroVideo.addEventListener("loadeddata", function () {
      this.play().catch(() => {
        console.log("Video autoplay prevented by browser")
      })
    })
  }

  // Parallax effect for hero background
  window.addEventListener("scroll", () => {
    const scrolled = window.pageYOffset
    const heroBackground = document.querySelector(".hero-background")
    if (heroBackground) {
      heroBackground.style.transform = `translateY(${scrolled * 0.5}px)`
    }
  })
}

// Animation functionality
function initAnimations() {
  // Intersection Observer for scroll animations
  const observerOptions = {
    threshold: 0.1,
    rootMargin: "0px 0px -50px 0px",
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("fade-in")
        observer.unobserve(entry.target)
      }
    })
  }, observerOptions)

  // Observe elements for animation
  const animateElements = document.querySelectorAll(".tour-card, .feature-card, .testimonial-card, .stat-card")
  animateElements.forEach((el) => {
    observer.observe(el)
  })

  // Counter animation for statistics
  const counters = document.querySelectorAll(".stat-value, .price-amount")
  const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        animateCounter(entry.target)
        counterObserver.unobserve(entry.target)
      }
    })
  }, observerOptions)

  counters.forEach((counter) => {
    counterObserver.observe(counter)
  })
}

// Counter animation function
function animateCounter(element) {
  const target = Number.parseInt(element.textContent.replace(/[^0-9]/g, ""))
  const duration = 2000
  const step = target / (duration / 16)
  let current = 0

  const timer = setInterval(() => {
    current += step
    if (current >= target) {
      current = target
      clearInterval(timer)
    }

    const prefix = element.textContent.match(/[^0-9]/g)
    const suffix = element.textContent.match(/[^0-9]+$/)
    element.textContent = (prefix ? prefix[0] : "") + Math.floor(current) + (suffix ? suffix[0] : "")
  }, 16)
}

// Form functionality
function initForms() {
  const forms = document.querySelectorAll("form")

  forms.forEach((form) => {
    // Form validation
    form.addEventListener("submit", function (e) {
      if (!validateForm(this)) {
        e.preventDefault()
        e.stopPropagation()
      }
      this.classList.add("was-validated")
    })

    // Real-time validation
    const inputs = form.querySelectorAll("input, textarea, select")
    inputs.forEach((input) => {
      input.addEventListener("blur", function () {
        validateField(this)
      })

      input.addEventListener("input", function () {
        if (this.classList.contains("is-invalid")) {
          validateField(this)
        }
      })
    })
  })

  // Newsletter form
  const newsletterForm = document.querySelector(".newsletter-form")
  if (newsletterForm) {
    newsletterForm.addEventListener("submit", function (e) {
      e.preventDefault()
      handleNewsletterSubmission(this)
    })
  }

  // Contact form
  const contactForm = document.querySelector("#contact-form")
  if (contactForm) {
    contactForm.addEventListener("submit", function (e) {
      e.preventDefault()
      handleContactSubmission(this)
    })
  }
}

// Form validation functions
function validateForm(form) {
  let isValid = true
  const inputs = form.querySelectorAll("input[required], textarea[required], select[required]")

  inputs.forEach((input) => {
    if (!validateField(input)) {
      isValid = false
    }
  })

  return isValid
}

function validateField(field) {
  const value = field.value.trim()
  const type = field.type
  let isValid = true
  let message = ""

  // Required field validation
  if (field.hasAttribute("required") && !value) {
    isValid = false
    message = "This field is required"
  }

  // Email validation
  else if (type === "email" && value) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(value)) {
      isValid = false
      message = "Please enter a valid email address"
    }
  }

  // Phone validation
  else if (field.name === "phone" && value) {
    const phoneRegex = /^[+]?[1-9][\d]{0,15}$/
    if (!phoneRegex.test(value.replace(/[\s\-$$$$]/g, ""))) {
      isValid = false
      message = "Please enter a valid phone number"
    }
  }

  // Password validation
  else if (type === "password" && value) {
    if (value.length < 8) {
      isValid = false
      message = "Password must be at least 8 characters long"
    }
  }

  // Update field state
  if (isValid) {
    field.classList.remove("is-invalid")
    field.classList.add("is-valid")
  } else {
    field.classList.remove("is-valid")
    field.classList.add("is-invalid")
  }

  // Update feedback message
  const feedback = field.parentNode.querySelector(".invalid-feedback")
  if (feedback) {
    feedback.textContent = message
  }

  return isValid
}

// Newsletter submission
function handleNewsletterSubmission(form) {
  const email = form.querySelector('input[type="email"]').value
  const button = form.querySelector("button")
  const originalText = button.textContent

  // Show loading state
  button.textContent = "Subscribing..."
  button.disabled = true

  // Simulate API call
  fetch("/api/newsletter.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ email: email }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showAlert("success", "Thank you for subscribing to our newsletter!")
        form.reset()
      } else {
        showAlert("error", data.message || "An error occurred. Please try again.")
      }
    })
    .catch((error) => {
      showAlert("error", "An error occurred. Please try again.")
    })
    .finally(() => {
      button.textContent = originalText
      button.disabled = false
    })
}

// Contact form submission
function handleContactSubmission(form) {
  const formData = new FormData(form)
  const button = form.querySelector('button[type="submit"]')
  const originalText = button.textContent

  // Show loading state
  button.textContent = "Sending..."
  button.disabled = true

  fetch("/api/contact.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showAlert("success", "Thank you for your message! We'll get back to you soon.")
        form.reset()
        form.classList.remove("was-validated")
      } else {
        showAlert("error", data.message || "An error occurred. Please try again.")
      }
    })
    .catch((error) => {
      showAlert("error", "An error occurred. Please try again.")
    })
    .finally(() => {
      button.textContent = originalText
      button.disabled = false
    })
}

// Modal functionality
function initModals() {
  const modalTriggers = document.querySelectorAll('[data-bs-toggle="modal"]')
  const modals = document.querySelectorAll(".modal")

  modalTriggers.forEach((trigger) => {
    trigger.addEventListener("click", function (e) {
      e.preventDefault()
      const targetModal = document.querySelector(this.getAttribute("data-bs-target"))
      if (targetModal) {
        showModal(targetModal)
      }
    })
  })

  modals.forEach((modal) => {
    const closeButtons = modal.querySelectorAll('[data-bs-dismiss="modal"]')
    closeButtons.forEach((button) => {
      button.addEventListener("click", () => {
        hideModal(modal)
      })
    })

    // Close modal when clicking outside
    modal.addEventListener("click", function (e) {
      if (e.target === this) {
        hideModal(this)
      }
    })
  })
}

function showModal(modal) {
  modal.style.display = "block"
  modal.classList.add("show")
  document.body.classList.add("modal-open")

  // Focus management
  const focusableElements = modal.querySelectorAll(
    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
  )
  if (focusableElements.length > 0) {
    focusableElements[0].focus()
  }
}

function hideModal(modal) {
  modal.classList.remove("show")
  setTimeout(() => {
    modal.style.display = "none"
    document.body.classList.remove("modal-open")
  }, 300)
}

// Carousel functionality
function initCarousels() {
  const carousels = document.querySelectorAll(".carousel")

  carousels.forEach((carousel) => {
    let currentSlide = 0
    const slides = carousel.querySelectorAll(".carousel-item")
    const totalSlides = slides.length

    if (totalSlides === 0) return

    // Auto-advance carousel
    setInterval(() => {
      slides[currentSlide].classList.remove("active")
      currentSlide = (currentSlide + 1) % totalSlides
      slides[currentSlide].classList.add("active")
    }, 5000)

    // Navigation buttons
    const prevBtn = carousel.querySelector(".carousel-control-prev")
    const nextBtn = carousel.querySelector(".carousel-control-next")

    if (prevBtn) {
      prevBtn.addEventListener("click", () => {
        slides[currentSlide].classList.remove("active")
        currentSlide = currentSlide === 0 ? totalSlides - 1 : currentSlide - 1
        slides[currentSlide].classList.add("active")
      })
    }

    if (nextBtn) {
      nextBtn.addEventListener("click", () => {
        slides[currentSlide].classList.remove("active")
        currentSlide = (currentSlide + 1) % totalSlides
        slides[currentSlide].classList.add("active")
      })
    }
  })
}

// Lazy loading functionality
function initLazyLoading() {
  const lazyImages = document.querySelectorAll("img[data-src]")

  const imageObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        const img = entry.target
        img.src = img.dataset.src
        img.classList.remove("lazy")
        imageObserver.unobserve(img)
      }
    })
  })

  lazyImages.forEach((img) => {
    imageObserver.observe(img)
  })
}

// Scroll effects
function initScrollEffects() {
  let ticking = false

  function updateScrollEffects() {
    const scrolled = window.pageYOffset

    // Parallax backgrounds
    const parallaxElements = document.querySelectorAll(".parallax")
    parallaxElements.forEach((element) => {
      const speed = element.dataset.speed || 0.5
      element.style.transform = `translateY(${scrolled * speed}px)`
    })

    // Progress bar
    const progressBar = document.querySelector(".scroll-progress")
    if (progressBar) {
      const windowHeight = document.documentElement.scrollHeight - window.innerHeight
      const progress = (scrolled / windowHeight) * 100
      progressBar.style.width = `${progress}%`
    }

    ticking = false
  }

  window.addEventListener("scroll", () => {
    if (!ticking) {
      requestAnimationFrame(updateScrollEffects)
      ticking = true
    }
  })
}

// Tooltip functionality
function initTooltips() {
  const tooltipElements = document.querySelectorAll('[data-bs-toggle="tooltip"]')

  tooltipElements.forEach((element) => {
    element.addEventListener("mouseenter", function () {
      showTooltip(this)
    })

    element.addEventListener("mouseleave", function () {
      hideTooltip(this)
    })
  })
}

function showTooltip(element) {
  const text = element.getAttribute("title") || element.getAttribute("data-bs-title")
  if (!text) return

  const tooltip = document.createElement("div")
  tooltip.className = "tooltip fade show"
  tooltip.innerHTML = `<div class="tooltip-inner">${text}</div>`

  document.body.appendChild(tooltip)

  const rect = element.getBoundingClientRect()
  tooltip.style.position = "absolute"
  tooltip.style.top = `${rect.top - tooltip.offsetHeight - 5}px`
  tooltip.style.left = `${rect.left + (rect.width - tooltip.offsetWidth) / 2}px`

  element._tooltip = tooltip
}

function hideTooltip(element) {
  if (element._tooltip) {
    element._tooltip.remove()
    element._tooltip = null
  }
}

// Search functionality
function initSearchFunctionality() {
  const searchForms = document.querySelectorAll(".search-form")

  searchForms.forEach((form) => {
    const input = form.querySelector('input[type="search"], input[name="search"]')
    const button = form.querySelector('button[type="submit"]')

    if (input && button) {
      // Live search suggestions
      let searchTimeout
      input.addEventListener("input", function () {
        clearTimeout(searchTimeout)
        searchTimeout = setTimeout(() => {
          if (this.value.length >= 3) {
            fetchSearchSuggestions(this.value)
          }
        }, 300)
      })

      // Form submission
      form.addEventListener("submit", (e) => {
        e.preventDefault()
        performSearch(input.value)
      })
    }
  })
}

function fetchSearchSuggestions(query) {
  fetch(`/api/search-suggestions.php?q=${encodeURIComponent(query)}`)
    .then((response) => response.json())
    .then((data) => {
      displaySearchSuggestions(data.suggestions)
    })
    .catch((error) => {
      console.error("Search suggestions error:", error)
    })
}

function displaySearchSuggestions(suggestions) {
  // Implementation for displaying search suggestions
  console.log("Search suggestions:", suggestions)
}

function performSearch(query) {
  if (!query.trim()) return

  // Redirect to search results page
  window.location.href = `/search.php?q=${encodeURIComponent(query)}`
}

// Alert functionality
function showAlert(type, message, duration = 5000) {
  const alertContainer = document.querySelector(".alert-container") || createAlertContainer()

  const alert = document.createElement("div")
  alert.className = `alert alert-${type} alert-dismissible fade show`
  alert.innerHTML = `
        <i class="fas fa-${getAlertIcon(type)}"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `

  alertContainer.appendChild(alert)

  // Auto-dismiss
  setTimeout(() => {
    alert.classList.remove("show")
    setTimeout(() => {
      alert.remove()
    }, 300)
  }, duration)

  // Manual dismiss
  const closeButton = alert.querySelector(".btn-close")
  closeButton.addEventListener("click", () => {
    alert.classList.remove("show")
    setTimeout(() => {
      alert.remove()
    }, 300)
  })
}

function createAlertContainer() {
  const container = document.createElement("div")
  container.className = "alert-container"
  container.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 400px;
    `
  document.body.appendChild(container)
  return container
}

function getAlertIcon(type) {
  const icons = {
    success: "check-circle",
    error: "exclamation-circle",
    warning: "exclamation-triangle",
    info: "info-circle",
  }
  return icons[type] || "info-circle"
}

// Utility functions
function debounce(func, wait) {
  let timeout
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout)
      func(...args)
    }
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}

function throttle(func, limit) {
  let inThrottle
  return function () {
    const args = arguments
    
    if (!inThrottle) {
      func.apply(this, args)
      inThrottle = true
      setTimeout(() => (inThrottle = false), limit)
    }
  }
}

// Loading state management
function showLoading(element) {
  element.classList.add("loading")
  element.disabled = true
}

function hideLoading(element) {
  element.classList.remove("loading")
  element.disabled = false
}

// Cookie management
function setCookie(name, value, days) {
  const expires = new Date()
  expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000)
  document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`
}

function getCookie(name) {
  const nameEQ = name + "="
  const ca = document.cookie.split(";")
  for (let i = 0; i < ca.length; i++) {
    let c = ca[i]
    while (c.charAt(0) === " ") c = c.substring(1, c.length)
    if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length)
  }
  return null
}

// Export functions for use in other scripts
window.ForeverYoungTours = {
  showAlert,
  showLoading,
  hideLoading,
  setCookie,
  getCookie,
  debounce,
  throttle,
}
