import { Chart } from "@/components/ui/chart"
// Forever Young Tours - Client Portal JavaScript

document.addEventListener("DOMContentLoaded", () => {
  // Initialize portal functionality
  initializeSidebar()
  initializeNotifications()
  initializeSearch()
  initializeCharts()
  initializeRealTimeUpdates()
})

// Sidebar functionality
function initializeSidebar() {
  const sidebar = document.getElementById("sidebar")
  const sidebarToggle = document.getElementById("sidebarToggle")
  const mobileSidebarToggle = document.getElementById("mobileSidebarToggle")

  // Desktop sidebar toggle
  if (sidebarToggle) {
    sidebarToggle.addEventListener("click", () => {
      sidebar.classList.toggle("collapsed")
      document.querySelector(".main-content").classList.toggle("sidebar-collapsed")
    })
  }

  // Mobile sidebar toggle
  if (mobileSidebarToggle) {
    mobileSidebarToggle.addEventListener("click", () => {
      sidebar.classList.toggle("mobile-open")
    })
  }

  // Close mobile sidebar when clicking outside
  document.addEventListener("click", (e) => {
    if (window.innerWidth <= 768) {
      if (!sidebar.contains(e.target) && !mobileSidebarToggle.contains(e.target)) {
        sidebar.classList.remove("mobile-open")
      }
    }
  })

  // Update active menu item based on current page
  updateActiveMenuItem()
}

// Update active menu item
function updateActiveMenuItem() {
  const currentPage = window.location.pathname.split("/").pop().replace(".php", "")
  const menuLinks = document.querySelectorAll(".nav-link")

  menuLinks.forEach((link) => {
    const href = link.getAttribute("href")
    if (href && href.includes(currentPage)) {
      link.classList.add("active")
    } else {
      link.classList.remove("active")
    }
  })
}

// Notifications functionality
function initializeNotifications() {
  const notificationBtn = document.getElementById("notificationBtn")
  const notificationDropdown = document.getElementById("notificationDropdown")

  if (notificationBtn && notificationDropdown) {
    notificationBtn.addEventListener("click", (e) => {
      e.stopPropagation()
      notificationDropdown.classList.toggle("show")

      if (notificationDropdown.classList.contains("show")) {
        loadNotifications()
      }
    })

    // Close dropdown when clicking outside
    document.addEventListener("click", () => {
      notificationDropdown.classList.remove("show")
    })

    // Prevent dropdown from closing when clicking inside
    notificationDropdown.addEventListener("click", (e) => {
      e.stopPropagation()
    })
  }

  // Auto-refresh notifications every 30 seconds
  setInterval(() => {
    if (document.querySelector(".notification-badge")) {
      updateNotificationCount()
    }
  }, 30000)
}

// Load notifications via AJAX
function loadNotifications() {
  const content = document.getElementById("notificationContent")
  if (!content) return

  content.innerHTML = '<div class="loading">Loading notifications...</div>'

  fetch("../api/get-notifications.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.notifications) {
        if (data.notifications.length > 0) {
          content.innerHTML = data.notifications
            .map(
              (notification) => `
                        <div class="notification-item ${notification.read_at ? "read" : "unread"}" data-id="${notification.id}">
                            <div class="notification-icon">
                                <i class="fas fa-${getNotificationIcon(notification.type)}"></i>
                            </div>
                            <div class="notification-content">
                                <h4>${escapeHtml(notification.title)}</h4>
                                <p>${escapeHtml(notification.message)}</p>
                                <span class="notification-time">${notification.time_ago}</span>
                            </div>
                            ${!notification.read_at ? '<button class="mark-read-btn" onclick="markNotificationRead(' + notification.id + ')"><i class="fas fa-check"></i></button>' : ""}
                        </div>
                    `,
            )
            .join("")

          // Add click handlers for notifications
          content.querySelectorAll(".notification-item").forEach((item) => {
            item.addEventListener("click", function () {
              const notificationId = this.dataset.id
              if (!this.classList.contains("read")) {
                markNotificationRead(notificationId)
              }
            })
          })
        } else {
          content.innerHTML = '<div class="no-notifications">No new notifications</div>'
        }
      } else {
        content.innerHTML = '<div class="error">Error loading notifications</div>'
      }
    })
    .catch((error) => {
      console.error("Error loading notifications:", error)
      content.innerHTML = '<div class="error">Error loading notifications</div>'
    })
}

// Get notification icon based on type
function getNotificationIcon(type) {
  const icons = {
    booking: "calendar-check",
    payment: "credit-card",
    system: "cog",
    promotion: "tag",
    reminder: "bell",
    default: "info-circle",
  }
  return icons[type] || icons.default
}

// Mark notification as read
function markNotificationRead(notificationId) {
  fetch("../api/mark-notification-read.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ notification_id: notificationId }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const notificationItem = document.querySelector(`[data-id="${notificationId}"]`)
        if (notificationItem) {
          notificationItem.classList.remove("unread")
          notificationItem.classList.add("read")

          const markReadBtn = notificationItem.querySelector(".mark-read-btn")
          if (markReadBtn) {
            markReadBtn.remove()
          }
        }

        updateNotificationCount()
      }
    })
    .catch((error) => {
      console.error("Error marking notification as read:", error)
    })
}

// Update notification count
function updateNotificationCount() {
  fetch("../api/get-notification-count.php")
    .then((response) => response.json())
    .then((data) => {
      const badges = document.querySelectorAll(".notification-badge, .badge")
      badges.forEach((badge) => {
        if (data.count > 0) {
          badge.textContent = data.count
          badge.style.display = "inline-block"
        } else {
          badge.style.display = "none"
        }
      })
    })
    .catch((error) => {
      console.error("Error updating notification count:", error)
    })
}

// Search functionality
function initializeSearch() {
  const searchInput = document.querySelector(".search-input")
  const searchBtn = document.querySelector(".search-btn")

  if (searchInput && searchBtn) {
    let searchTimeout

    searchInput.addEventListener("input", function () {
      clearTimeout(searchTimeout)
      searchTimeout = setTimeout(() => {
        performSearch(this.value)
      }, 500)
    })

    searchBtn.addEventListener("click", () => {
      performSearch(searchInput.value)
    })

    searchInput.addEventListener("keypress", function (e) {
      if (e.key === "Enter") {
        performSearch(this.value)
      }
    })
  }
}

// Perform search
function performSearch(query) {
  if (query.trim().length < 2) return

  // Show search results in a dropdown or redirect to search page
  window.location.href = `search.php?q=${encodeURIComponent(query)}`
}

// Initialize charts
function initializeCharts() {
  // Booking status chart
  const bookingChartCanvas = document.getElementById("bookingStatusChart")
  if (bookingChartCanvas) {
    initializeBookingStatusChart(bookingChartCanvas)
  }

  // Spending chart
  const spendingChartCanvas = document.getElementById("spendingChart")
  if (spendingChartCanvas) {
    initializeSpendingChart(spendingChartCanvas)
  }
}

// Initialize booking status chart
function initializeBookingStatusChart(canvas) {
  fetch("../api/get-booking-stats.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        new Chart(canvas, {
          type: "doughnut",
          data: {
            labels: ["Confirmed", "Pending", "Cancelled", "Completed"],
            datasets: [
              {
                data: [data.stats.confirmed, data.stats.pending, data.stats.cancelled, data.stats.completed],
                backgroundColor: ["#28a745", "#ffc107", "#dc3545", "#17a2b8"],
                borderWidth: 0,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                position: "bottom",
              },
            },
          },
        })
      }
    })
    .catch((error) => {
      console.error("Error loading booking stats:", error)
    })
}

// Initialize spending chart
function initializeSpendingChart(canvas) {
  fetch("../api/get-spending-stats.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        new Chart(canvas, {
          type: "line",
          data: {
            labels: data.stats.months,
            datasets: [
              {
                label: "Monthly Spending",
                data: data.stats.amounts,
                borderColor: "#667eea",
                backgroundColor: "rgba(102, 126, 234, 0.1)",
                borderWidth: 2,
                fill: true,
                tension: 0.4,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                  callback: (value) => "$" + value.toLocaleString(),
                },
              },
            },
            plugins: {
              legend: {
                display: false,
              },
            },
          },
        })
      }
    })
    .catch((error) => {
      console.error("Error loading spending stats:", error)
    })
}

// Real-time updates
function initializeRealTimeUpdates() {
  // Update booking statuses every 60 seconds
  setInterval(() => {
    updateBookingStatuses()
  }, 60000)

  // Update payment statuses every 30 seconds
  setInterval(() => {
    updatePaymentStatuses()
  }, 30000)
}

// Update booking statuses
function updateBookingStatuses() {
  const bookingCards = document.querySelectorAll(".booking-card")
  if (bookingCards.length === 0) return

  const bookingIds = Array.from(bookingCards)
    .map((card) => card.dataset.bookingId)
    .filter((id) => id)

  if (bookingIds.length > 0) {
    fetch("../api/get-booking-statuses.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ booking_ids: bookingIds }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          data.bookings.forEach((booking) => {
            const card = document.querySelector(`[data-booking-id="${booking.id}"]`)
            if (card) {
              const statusElement = card.querySelector(".booking-status")
              if (statusElement && statusElement.textContent.toLowerCase() !== booking.status) {
                statusElement.textContent = booking.status.charAt(0).toUpperCase() + booking.status.slice(1)
                statusElement.className = `booking-status ${booking.status}`

                // Show notification for status change
                showNotification(`Booking status updated to ${booking.status}`, "info")
              }
            }
          })
        }
      })
      .catch((error) => {
        console.error("Error updating booking statuses:", error)
      })
  }
}

// Update payment statuses
function updatePaymentStatuses() {
  const paymentElements = document.querySelectorAll("[data-payment-id]")
  if (paymentElements.length === 0) return

  const paymentIds = Array.from(paymentElements).map((el) => el.dataset.paymentId)

  fetch("../api/get-payment-statuses.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ payment_ids: paymentIds }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        data.payments.forEach((payment) => {
          const element = document.querySelector(`[data-payment-id="${payment.id}"]`)
          if (element) {
            const statusElement = element.querySelector(".payment-status")
            if (statusElement && statusElement.textContent.toLowerCase() !== payment.status) {
              statusElement.textContent = payment.status.charAt(0).toUpperCase() + payment.status.slice(1)
              statusElement.className = `payment-status ${payment.status}`

              if (payment.status === "completed") {
                showNotification("Payment completed successfully!", "success")
              }
            }
          }
        })
      }
    })
    .catch((error) => {
      console.error("Error updating payment statuses:", error)
    })
}

// Show notification
function showNotification(message, type = "info") {
  const notification = document.createElement("div")
  notification.className = `notification notification-${type}`
  notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${getNotificationTypeIcon(type)}"></i>
            <span>${escapeHtml(message)}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `

  document.body.appendChild(notification)

  // Auto-remove after 5 seconds
  setTimeout(() => {
    if (notification.parentElement) {
      notification.remove()
    }
  }, 5000)
}

// Get notification type icon
function getNotificationTypeIcon(type) {
  const icons = {
    success: "check-circle",
    error: "exclamation-triangle",
    warning: "exclamation-circle",
    info: "info-circle",
  }
  return icons[type] || icons.info
}

// Utility functions
function escapeHtml(text) {
  const map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  }
  return text.replace(/[&<>"']/g, (m) => map[m])
}

function formatCurrency(amount) {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
  }).format(amount)
}

function formatDate(dateString) {
  return new Date(dateString).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  })
}

function timeAgo(dateString) {
  const now = new Date()
  const date = new Date(dateString)
  const diffInSeconds = Math.floor((now - date) / 1000)

  if (diffInSeconds < 60) return "Just now"
  if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + " minutes ago"
  if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + " hours ago"
  if (diffInSeconds < 2592000) return Math.floor(diffInSeconds / 86400) + " days ago"

  return formatDate(dateString)
}

// Export functions for global use
window.ClientPortal = {
  markNotificationRead,
  showNotification,
  formatCurrency,
  formatDate,
  timeAgo,
}
