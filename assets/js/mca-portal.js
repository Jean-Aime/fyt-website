import { Chart } from "@/components/ui/chart"
// Forever Young Tours - MCA Portal JavaScript

document.addEventListener("DOMContentLoaded", () => {
  // Initialize MCA portal functionality
  initializeMCADashboard()
  initializeCommissionTracking()
  initializeTrainingProgress()
  initializeClientManagement()
  initializeReferralTracking()
})

// Initialize MCA Dashboard
function initializeMCADashboard() {
  // Load dashboard statistics
  loadDashboardStats()

  // Initialize commission charts
  initializeCommissionCharts()

  // Set up real-time updates
  setInterval(loadDashboardStats, 300000) // Update every 5 minutes
}

// Load dashboard statistics
function loadDashboardStats() {
  fetch("../api/mca/get-dashboard-stats.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        updateStatCards(data.stats)
        updateRecentActivity(data.recent_activity)
      }
    })
    .catch((error) => {
      console.error("Error loading dashboard stats:", error)
    })
}

// Update stat cards
function updateStatCards(stats) {
  const statCards = {
    "total-earnings": stats.total_earnings,
    "confirmed-bookings": stats.confirmed_bookings,
    "commission-rate": stats.commission_rate + "%",
    "training-progress": stats.training_progress + "%",
  }

  Object.keys(statCards).forEach((cardId) => {
    const card = document.getElementById(cardId)
    if (card) {
      const valueElement = card.querySelector(".stat-value, h3")
      if (valueElement) {
        valueElement.textContent = cardId.includes("earnings") ? formatCurrency(statCards[cardId]) : statCards[cardId]
      }
    }
  })
}

// Update recent activity
function updateRecentActivity(activity) {
  const activityContainer = document.getElementById("recentActivity")
  if (!activityContainer) return

  activityContainer.innerHTML = activity
    .map(
      (item) => `
        <div class="activity-item">
            <strong>${escapeHtml(item.action)}</strong>
            <p>${escapeHtml(item.description)}</p>
            <small>${formatDate(item.timestamp)}</small>
        </div>
    `,
    )
    .join("")
}

// Initialize commission tracking
function initializeCommissionTracking() {
  // Load commission history
  loadCommissionHistory()

  // Set up commission filters
  setupCommissionFilters()

  // Initialize commission export
  setupCommissionExport()
}

// Load commission history
function loadCommissionHistory() {
  const commissionTable = document.getElementById("commissionTable")
  if (!commissionTable) return

  fetch("../api/mca/get-commission-history.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        renderCommissionTable(data.commissions)
      }
    })
    .catch((error) => {
      console.error("Error loading commission history:", error)
    })
}

// Render commission table
function renderCommissionTable(commissions) {
  const tbody = document.querySelector("#commissionTable tbody")
  if (!tbody) return

  tbody.innerHTML = commissions
    .map(
      (commission) => `
        <tr>
            <td>
                <div class="booking-info">
                    <strong>#${commission.booking_id}</strong>
                    <small>${commission.tour_title}</small>
                </div>
            </td>
            <td>
                <div class="client-info">
                    <strong>${escapeHtml(commission.client_name)}</strong>
                    <small>${escapeHtml(commission.client_email)}</small>
                </div>
            </td>
            <td>${formatCurrency(commission.booking_amount)}</td>
            <td>${commission.commission_rate}%</td>
            <td>${formatCurrency(commission.commission_amount)}</td>
            <td>
                <span class="status-badge ${commission.status}">
                    ${commission.status.charAt(0).toUpperCase() + commission.status.slice(1)}
                </span>
            </td>
            <td>${formatDate(commission.created_at)}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-outline" onclick="viewCommissionDetails(${commission.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${
                      commission.status === "pending"
                        ? `
                        <button class="btn btn-sm btn-primary" onclick="requestCommissionApproval(${commission.id})">
                            <i class="fas fa-check"></i>
                        </button>
                    `
                        : ""
                    }
                </div>
            </td>
        </tr>
    `,
    )
    .join("")
}

// Setup commission filters
function setupCommissionFilters() {
  // Implementation for setting up commission filters
  console.log("Commission filters set up")
}

// Setup commission export
function setupCommissionExport() {
  // Implementation for setting up commission export
  console.log("Commission export set up")
}

// Initialize commission charts
function initializeCommissionCharts() {
  // Monthly commission chart
  const monthlyChartCanvas = document.getElementById("monthlyCommissionChart")
  if (monthlyChartCanvas) {
    loadMonthlyCommissionChart(monthlyChartCanvas)
  }

  // Commission breakdown chart
  const breakdownChartCanvas = document.getElementById("commissionBreakdownChart")
  if (breakdownChartCanvas) {
    loadCommissionBreakdownChart(breakdownChartCanvas)
  }
}

// Load monthly commission chart
function loadMonthlyCommissionChart(canvas) {
  fetch("../api/mca/get-monthly-commission-data.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        new Chart(canvas, {
          type: "line",
          data: {
            labels: data.months,
            datasets: [
              {
                label: "Commission Earned",
                data: data.amounts,
                borderColor: "#28a745",
                backgroundColor: "rgba(40, 167, 69, 0.1)",
                borderWidth: 3,
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
              tooltip: {
                callbacks: {
                  label: (context) => "Commission: $" + context.parsed.y.toLocaleString(),
                },
              },
            },
          },
        })
      }
    })
    .catch((error) => {
      console.error("Error loading monthly commission chart:", error)
    })
}

// Load commission breakdown chart
function loadCommissionBreakdownChart(canvas) {
  // Implementation for loading commission breakdown chart
  console.log("Commission breakdown chart loaded")
}

// Initialize training progress
function initializeTrainingProgress() {
  // Load training modules
  loadTrainingModules()

  // Set up training completion tracking
  setupTrainingTracking()

  // Initialize training certificates
  setupCertificateDownload()
}

// Load training modules
function loadTrainingModules() {
  const trainingContainer = document.getElementById("trainingModules")
  if (!trainingContainer) return

  fetch("../api/mca/get-training-modules.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        renderTrainingModules(data.modules)
      }
    })
    .catch((error) => {
      console.error("Error loading training modules:", error)
    })
}

// Render training modules
function renderTrainingModules(modules) {
  const container = document.getElementById("trainingModules")
  if (!container) return

  container.innerHTML = modules
    .map(
      (module) => `
        <div class="training-module ${module.status}" data-module-id="${module.id}">
            <div class="module-header">
                <h4>${escapeHtml(module.title)}</h4>
                <span class="module-status ${module.status}">
                    ${
                      module.status === "completed"
                        ? `<i class="fas fa-check-circle"></i>`
                        : module.status === "in_progress"
                          ? `<i class="fas fa-play-circle"></i>`
                          : `<i class="fas fa-lock"></i>`
                    }
                </span>
            </div>
            <div class="module-content">
                <p>${escapeHtml(module.description)}</p>
                <div class="module-meta">
                    <span><i class="fas fa-clock"></i> ${module.duration} minutes</span>
                    <span><i class="fas fa-star"></i> ${module.difficulty}</span>
                    ${module.score ? `<span><i class="fas fa-trophy"></i> Score: ${module.score}%</span>` : ""}
                </div>
            </div>
            <div class="module-actions">
                ${
                  module.status === "available"
                    ? `
                    <button class="btn btn-primary" onclick="startTrainingModule(${module.id})">
                        Start Module
                    </button>
                `
                    : module.status === "in_progress"
                      ? `
                    <button class="btn btn-secondary" onclick="continueTrainingModule(${module.id})">
                        Continue
                    </button>
                `
                      : `
                    <button class="btn btn-outline" onclick="reviewTrainingModule(${module.id})">
                        Review
                    </button>
                    ${
                      module.certificate_url
                        ? `
                        <a href="${module.certificate_url}" class="btn btn-success" download>
                            <i class="fas fa-download"></i> Certificate
                        </a>
                    `
                        : ""
                    }
                `
                }
            </div>
            ${
              module.progress !== undefined
                ? `
                <div class="module-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${module.progress}%"></div>
                    </div>
                    <span class="progress-text">${module.progress}% Complete</span>
                </div>
            `
                : ""
            }
        </div>
    `,
    )
    .join("")
}

// Setup training tracking
function setupTrainingTracking() {
  // Implementation for setting up training tracking
  console.log("Training tracking set up")
}

// Setup certificate download
function setupCertificateDownload() {
  // Implementation for setting up certificate download
  console.log("Certificate download set up")
}

// Start training module
function startTrainingModule(moduleId) {
  fetch("../api/mca/start-training-module.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ module_id: moduleId }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        window.open(data.module_url, "_blank")
        showNotification("Training module started successfully!", "success")
        loadTrainingModules() // Refresh modules
      } else {
        showNotification(data.message || "Error starting training module", "error")
      }
    })
    .catch((error) => {
      console.error("Error starting training module:", error)
      showNotification("Error starting training module", "error")
    })
}

// Initialize client management
function initializeClientManagement() {
  // Load client list
  loadClientList()

  // Set up client search
  setupClientSearch()

  // Initialize client communication tools
  setupClientCommunication()
}

// Load client list
function loadClientList() {
  const clientContainer = document.getElementById("clientList")
  if (!clientContainer) return

  fetch("../api/mca/get-clients.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        renderClientList(data.clients)
      }
    })
    .catch((error) => {
      console.error("Error loading client list:", error)
    })
}

// Render client list
function renderClientList(clients) {
  const container = document.getElementById("clientList")
  if (!container) return

  container.innerHTML = clients
    .map(
      (client) => `
        <div class="client-card" data-client-id="${client.id}">
            <div class="client-avatar">
                ${
                  client.profile_image
                    ? `<img src="../${client.profile_image}" alt="${client.name}">`
                    : `<div class="avatar-placeholder">${client.name.charAt(0)}</div>`
                }
            </div>
            <div class="client-info">
                <h4>${escapeHtml(client.name)}</h4>
                <p>${escapeHtml(client.email)}</p>
                <div class="client-stats">
                    <span><i class="fas fa-calendar"></i> ${client.total_bookings} bookings</span>
                    <span><i class="fas fa-dollar-sign"></i> ${formatCurrency(client.total_spent)}</span>
                </div>
            </div>
            <div class="client-actions">
                <button class="btn btn-sm btn-outline" onclick="viewClientDetails(${client.id})">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm btn-primary" onclick="contactClient(${client.id})">
                    <i class="fas fa-envelope"></i>
                </button>
                <button class="btn btn-sm btn-secondary" onclick="createBookingForClient(${client.id})">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
    `,
    )
    .join("")
}

// Setup client search
function setupClientSearch() {
  // Implementation for setting up client search
  console.log("Client search set up")
}

// Setup client communication
function setupClientCommunication() {
  // Implementation for setting up client communication
  console.log("Client communication set up")
}

// Initialize referral tracking
function initializeReferralTracking() {
  // Generate referral link
  generateReferralLink()

  // Load referral statistics
  loadReferralStats()

  // Set up social sharing
  setupSocialSharing()
}

// Generate referral link
function generateReferralLink() {
  const referralLinkElement = document.getElementById("referralLink")
  if (!referralLinkElement) return

  fetch("../api/mca/get-referral-link.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        referralLinkElement.value = data.referral_link

        // Add copy functionality
        const copyBtn = document.getElementById("copyReferralLink")
        if (copyBtn) {
          copyBtn.addEventListener("click", () => {
            referralLinkElement.select()
            document.execCommand("copy")
            showNotification("Referral link copied to clipboard!", "success")
          })
        }
      }
    })
    .catch((error) => {
      console.error("Error generating referral link:", error)
    })
}

// Load referral statistics
function loadReferralStats() {
  // Implementation for loading referral statistics
  console.log("Referral statistics loaded")
}

// Setup social sharing
function setupSocialSharing() {
  const shareButtons = document.querySelectorAll(".social-share-btn")
  shareButtons.forEach((btn) => {
    btn.addEventListener("click", function () {
      const platform = this.dataset.platform
      const referralLink = document.getElementById("referralLink").value
      const message = encodeURIComponent("Check out these amazing tours with Forever Young Tours!")

      let shareUrl = ""
      switch (platform) {
        case "facebook":
          shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(referralLink)}`
          break
        case "twitter":
          shareUrl = `https://twitter.com/intent/tweet?text=${message}&url=${encodeURIComponent(referralLink)}`
          break
        case "linkedin":
          shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(referralLink)}`
          break
        case "whatsapp":
          shareUrl = `https://wa.me/?text=${message}%20${encodeURIComponent(referralLink)}`
          break
      }

      if (shareUrl) {
        window.open(shareUrl, "_blank", "width=600,height=400")
      }
    })
  })
}

// Utility functions specific to MCA portal
function viewCommissionDetails(commissionId) {
  window.location.href = `commission-details.php?id=${commissionId}`
}

function requestCommissionApproval(commissionId) {
  if (confirm("Request approval for this commission?")) {
    fetch("../api/mca/request-commission-approval.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ commission_id: commissionId }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          showNotification("Commission approval requested successfully!", "success")
          loadCommissionHistory()
        } else {
          showNotification(data.message || "Error requesting approval", "error")
        }
      })
      .catch((error) => {
        console.error("Error requesting commission approval:", error)
        showNotification("Error requesting approval", "error")
      })
  }
}

function viewClientDetails(clientId) {
  window.location.href = `client-details.php?id=${clientId}`
}

function contactClient(clientId) {
  window.location.href = `contact-client.php?id=${clientId}`
}

function createBookingForClient(clientId) {
  window.location.href = `../book.php?client=${clientId}&agent=${getCurrentAgentId()}`
}

function getCurrentAgentId() {
  // This would be set in the page or retrieved from session
  return document.body.dataset.agentId || ""
}

// Show notification (reuse from client portal)
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

  setTimeout(() => {
    if (notification.parentElement) {
      notification.remove()
    }
  }, 5000)
}

function getNotificationTypeIcon(type) {
  const icons = {
    success: "check-circle",
    error: "exclamation-triangle",
    warning: "exclamation-circle",
    info: "info-circle",
  }
  return icons[type] || icons.info
}

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

// Export functions for global use
window.MCAPortal = {
  viewCommissionDetails,
  requestCommissionApproval,
  startTrainingModule,
  viewClientDetails,
  contactClient,
  createBookingForClient,
  showNotification,
  formatCurrency,
  formatDate,
}
