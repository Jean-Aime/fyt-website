// Dashboard JavaScript functionality

class Dashboard {
  constructor() {
    this.init()
  }

  init() {
    this.bindEvents()
    this.loadNotifications()
    this.initCharts()
    this.setupRealTimeUpdates()
  }

  bindEvents() {
    // Mobile sidebar toggle
    const sidebarToggle = document.querySelector(".sidebar-toggle")
    if (sidebarToggle) {
      sidebarToggle.addEventListener("click", this.toggleSidebar)
    }

    // Notification dropdown
    const notificationBtn = document.querySelector(".notification-btn")
    if (notificationBtn) {
      notificationBtn.addEventListener("click", this.toggleNotifications)
    }

    // Quick action cards
    document.querySelectorAll(".action-card").forEach((card) => {
      card.addEventListener("click", this.handleQuickAction)
    })

    // Booking actions
    document.querySelectorAll(".booking-action").forEach((btn) => {
      btn.addEventListener("click", this.handleBookingAction)
    })

    // Profile image upload
    const profileUpload = document.querySelector("#profileImageUpload")
    if (profileUpload) {
      profileUpload.addEventListener("change", this.handleProfileImageUpload)
    }
  }

  toggleSidebar() {
    const sidebar = document.querySelector(".dashboard-sidebar")
    const main = document.querySelector(".dashboard-main")

    sidebar.classList.toggle("collapsed")
    main.classList.toggle("expanded")
  }

  toggleNotifications() {
    const dropdown = document.querySelector(".notifications-dropdown")
    dropdown.classList.toggle("show")
  }

  handleQuickAction(e) {
    const action = e.currentTarget.dataset.action

    switch (action) {
      case "browse-tours":
        window.location.href = "/tours.php"
        break
      case "book-tour":
        window.location.href = "/book.php"
        break
      case "view-bookings":
        window.location.href = "/my-bookings.php"
        break
      case "contact-support":
        window.location.href = "/support.php"
        break
    }
  }

  handleBookingAction(e) {
    const action = e.target.dataset.action
    const bookingId = e.target.dataset.bookingId

    switch (action) {
      case "cancel":
        this.cancelBooking(bookingId)
        break
      case "modify":
        this.modifyBooking(bookingId)
        break
      case "pay":
        this.completePayment(bookingId)
        break
    }
  }

  cancelBooking(bookingId) {
    if (confirm("Are you sure you want to cancel this booking?")) {
      fetch("/api/cancel-booking.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ booking_id: bookingId }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            this.showNotification("Booking cancelled successfully", "success")
            location.reload()
          } else {
            this.showNotification(data.message, "error")
          }
        })
        .catch((error) => {
          console.error("Error:", error)
          this.showNotification("Error cancelling booking", "error")
        })
    }
  }

  modifyBooking(bookingId) {
    window.location.href = `/modify-booking.php?id=${bookingId}`
  }

  completePayment(bookingId) {
    window.location.href = `/payment.php?booking=${bookingId}`
  }

  handleProfileImageUpload(e) {
    const file = e.target.files[0]
    if (!file) return

    // Validate file type
    if (!file.type.startsWith("image/")) {
      this.showNotification("Please select an image file", "error")
      return
    }

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      this.showNotification("Image size must be less than 5MB", "error")
      return
    }

    const formData = new FormData()
    formData.append("profile_image", file)

    fetch("/api/upload-profile-image.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          document.querySelector(".user-avatar img").src = data.image_url
          this.showNotification("Profile image updated successfully", "success")
        } else {
          this.showNotification(data.message, "error")
        }
      })
      .catch((error) => {
        console.error("Error:", error)
        this.showNotification("Error uploading image", "error")
      })
  }

  loadNotifications() {
    fetch("/api/get-notifications.php")
      .then((response) => response.json())
      .then((data) => {
        this.updateNotificationBadge(data.unread_count)
        this.renderNotifications(data.notifications)
      })
      .catch((error) => {
        console.error("Error loading notifications:", error)
      })
  }

  updateNotificationBadge(count) {
    const badge = document.querySelector(".notification-badge")
    if (badge) {
      if (count > 0) {
        badge.textContent = count > 99 ? "99+" : count
        badge.style.display = "block"
      } else {
        badge.style.display = "none"
      }
    }
  }

  renderNotifications(notifications) {
    const container = document.querySelector(".notifications-list")
    if (!container) return

    if (notifications.length === 0) {
      container.innerHTML = `
                <div class="empty-notifications">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications</p>
                </div>
            `
      return
    }

    const html = notifications
      .map(
        (notification) => `
            <div class="notification-item ${notification.read_at ? "" : "unread"}" 
                 data-id="${notification.id}">
                <div class="notification-icon">
                    <i class="${this.getNotificationIcon(notification.type)}"></i>
                </div>
                <div class="notification-content">
                    <h4>${notification.title}</h4>
                    <p>${notification.message}</p>
                    <span class="notification-time">${this.timeAgo(notification.created_at)}</span>
                </div>
                ${!notification.read_at ? '<div class="unread-indicator"></div>' : ""}
            </div>
        `,
      )
      .join("")

    container.innerHTML = html

    // Add click handlers
    container.querySelectorAll(".notification-item").forEach((item) => {
      item.addEventListener("click", () => {
        this.markNotificationAsRead(item.dataset.id)
      })
    })
  }

  getNotificationIcon(type) {
    const icons = {
      booking: "fas fa-calendar-check",
      payment: "fas fa-credit-card",
      tour: "fas fa-map-marked-alt",
      system: "fas fa-cog",
      promotion: "fas fa-tag",
    }
    return icons[type] || "fas fa-bell"
  }

  markNotificationAsRead(notificationId) {
    fetch("/api/mark-notification-read.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ notification_id: notificationId }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          const item = document.querySelector(`[data-id="${notificationId}"]`)
          if (item) {
            item.classList.remove("unread")
            const indicator = item.querySelector(".unread-indicator")
            if (indicator) indicator.remove()
          }
          this.loadNotifications() // Refresh to update badge
        }
      })
      .catch((error) => {
        console.error("Error marking notification as read:", error)
      })
  }

  initCharts() {
    // Booking trends chart
    this.initBookingTrendsChart()

    // Spending chart
    this.initSpendingChart()
  }

  initBookingTrendsChart() {
    const canvas = document.getElementById("bookingTrendsChart")
    if (!canvas) return

    fetch("/api/booking-trends.php")
      .then((response) => response.json())
      .then((data) => {
        // Chart implementation would go here
        // Using Chart.js or similar library
        console.log("Booking trends data:", data)
      })
      .catch((error) => {
        console.error("Error loading booking trends:", error)
      })
  }

  initSpendingChart() {
    const canvas = document.getElementById("spendingChart")
    if (!canvas) return

    fetch("/api/spending-data.php")
      .then((response) => response.json())
      .then((data) => {
        // Chart implementation would go here
        console.log("Spending data:", data)
      })
      .catch((error) => {
        console.error("Error loading spending data:", error)
      })
  }

  setup

  setupRealTimeUpdates() {
    // Check for updates every 30 seconds
    setInterval(() => {
      this.loadNotifications()
      this.updateBookingStatuses()
    }, 30000)
  }

  updateBookingStatuses() {
    const bookingCards = document.querySelectorAll(".booking-card")
    if (bookingCards.length === 0) return

    const bookingIds = Array.from(bookingCards)
      .map((card) => card.dataset.bookingId)
      .filter((id) => id)

    if (bookingIds.length === 0) return

    fetch("/api/booking-statuses.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ booking_ids: bookingIds }),
    })
      .then((response) => response.json())
      .then((data) => {
        data.bookings.forEach((booking) => {
          const card = document.querySelector(`[data-booking-id="${booking.id}"]`)
          if (card) {
            const statusBadge = card.querySelector(".status-badge")
            if (statusBadge) {
              statusBadge.className = `status-badge status-${booking.status}`
              statusBadge.textContent = booking.status.charAt(0).toUpperCase() + booking.status.slice(1)
            }
          }
        })
      })
      .catch((error) => {
        console.error("Error updating booking statuses:", error)
      })
  }

  timeAgo(dateString) {
    const date = new Date(dateString)
    const now = new Date()
    const diffInSeconds = Math.floor((now - date) / 1000)

    if (diffInSeconds < 60) return "Just now"
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
    if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`

    return date.toLocaleDateString()
  }

  showNotification(message, type = "info") {
    const notification = document.createElement("div")
    notification.className = `dashboard-notification notification-${type}`
    notification.innerHTML = `
            <div class="notification-content">
                <i class="fas ${this.getNotificationTypeIcon(type)}"></i>
                <span>${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `

    document.body.appendChild(notification)

    // Auto remove after 5 seconds
    setTimeout(() => {
      if (notification.parentElement) {
        notification.remove()
      }
    }, 5000)

    // Animate in
    setTimeout(() => {
      notification.classList.add("show")
    }, 100)
  }

  getNotificationTypeIcon(type) {
    const icons = {
      success: "fa-check-circle",
      error: "fa-exclamation-circle",
      warning: "fa-exclamation-triangle",
      info: "fa-info-circle",
    }
    return icons[type] || icons.info
  }
}

// Initialize dashboard
document.addEventListener("DOMContentLoaded", () => {
  new Dashboard()
})

// Utility functions
function formatCurrency(amount) {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
  }).format(amount)
}

function formatDate(dateString) {
  return new Date(dateString).toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  })
}

// Export for global use
window.Dashboard = Dashboard
