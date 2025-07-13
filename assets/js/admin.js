// Forever Young Tours - Admin Panel JavaScript
// Main JavaScript file for admin functionality

;(() => {
  // Global admin object
  const AdminPanel = {
    config: {
      baseUrl: window.location.origin,
      adminUrl: window.location.origin + "/admin",
      apiUrl: window.location.origin + "/admin/api",
      csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "",
    },

    // Initialize admin panel
    init: function () {
      this.setupEventListeners()
      this.initializeComponents()
      this.setupAjaxDefaults()
      this.startPeriodicTasks()
    },

    // Setup global event listeners
    setupEventListeners: function () {
      // Auto-hide alerts
      this.setupAutoHideAlerts()

      // Confirm delete actions
      this.setupDeleteConfirmations()

      // Form validation
      this.setupFormValidation()

      // Table sorting
      this.setupTableSorting()

      // Search functionality
      this.setupSearchFunctionality()

      // Keyboard shortcuts
      this.setupKeyboardShortcuts()
    },

    // Initialize components
    initializeComponents: function () {
      // Initialize tooltips
      this.initTooltips()

      // Initialize modals
      this.initModals()

      // Initialize dropdowns
      this.initDropdowns()

      // Initialize file uploads
      this.initFileUploads()

      // Initialize date pickers
      this.initDatePickers()
    },

    // Setup AJAX defaults
    setupAjaxDefaults: () => {
      // Add CSRF token to all AJAX requests
      const originalFetch = window.fetch
      window.fetch = (url, options = {}) => {
        if (!options.headers) {
          options.headers = {}
        }

        if (AdminPanel.config.csrfToken) {
          options.headers["X-CSRF-Token"] = AdminPanel.config.csrfToken
        }

        return originalFetch(url, options)
      }
    },

    // Auto-hide alerts after 5 seconds
    setupAutoHideAlerts: function () {
      const alerts = document.querySelectorAll(".alert:not(.alert-permanent)")
      alerts.forEach((alert) => {
        setTimeout(() => {
          this.fadeOut(alert)
        }, 5000)
      })
    },

    // Setup delete confirmations
    setupDeleteConfirmations: () => {
      document.addEventListener("click", (e) => {
        const deleteBtn = e.target.closest("[data-confirm-delete]")
        if (deleteBtn) {
          e.preventDefault()

          const message =
            deleteBtn.getAttribute("data-confirm-delete") ||
            "Are you sure you want to delete this item? This action cannot be undone."

          if (confirm(message)) {
            if (deleteBtn.tagName === "A") {
              window.location.href = deleteBtn.href
            } else if (deleteBtn.tagName === "BUTTON" && deleteBtn.form) {
              deleteBtn.form.submit()
            }
          }
        }
      })
    },

    // Setup form validation
    setupFormValidation: () => {
      const forms = document.querySelectorAll("form[data-validate]")
      forms.forEach((form) => {
        form.addEventListener("submit", function (e) {
          if (!AdminPanel.validateForm(this)) {
            e.preventDefault()
          }
        })
      })
    },

    // Setup table sorting
    setupTableSorting: () => {
      const sortableHeaders = document.querySelectorAll("th[data-sort]")
      sortableHeaders.forEach((header) => {
        header.style.cursor = "pointer"
        header.addEventListener("click", function () {
          AdminPanel.sortTable(this)
        })
      })
    },

    // Setup search functionality
    setupSearchFunctionality: () => {
      const searchInputs = document.querySelectorAll("[data-search-table]")
      searchInputs.forEach((input) => {
        input.addEventListener("input", function () {
          AdminPanel.searchTable(this)
        })
      })
    },

    // Setup keyboard shortcuts
    setupKeyboardShortcuts: () => {
      document.addEventListener("keydown", (e) => {
        // Ctrl/Cmd + S to save forms
        if ((e.ctrlKey || e.metaKey) && e.key === "s") {
          const form = document.querySelector("form")
          if (form) {
            e.preventDefault()
            form.submit()
          }
        }

        // Escape to close modals
        if (e.key === "Escape") {
          AdminPanel.closeAllModals()
        }
      })
    },

    // Initialize tooltips
    initTooltips: () => {
      const tooltipElements = document.querySelectorAll("[data-tooltip]")
      tooltipElements.forEach((element) => {
        element.addEventListener("mouseenter", function () {
          AdminPanel.showTooltip(this)
        })

        element.addEventListener("mouseleave", function () {
          AdminPanel.hideTooltip(this)
        })
      })
    },

    // Initialize modals
    initModals: () => {
      const modalTriggers = document.querySelectorAll("[data-modal]")
      modalTriggers.forEach((trigger) => {
        trigger.addEventListener("click", function (e) {
          e.preventDefault()
          const modalId = this.getAttribute("data-modal")
          AdminPanel.openModal(modalId)
        })
      })

      // Close modal when clicking outside
      document.addEventListener("click", (e) => {
        if (e.target.classList.contains("modal")) {
          AdminPanel.closeModal(e.target.id)
        }
      })
    },

    // Initialize dropdowns
    initDropdowns: () => {
      const dropdownTriggers = document.querySelectorAll("[data-dropdown]")
      dropdownTriggers.forEach((trigger) => {
        trigger.addEventListener("click", function (e) {
          e.preventDefault()
          e.stopPropagation()
          AdminPanel.toggleDropdown(this)
        })
      })

      // Close dropdowns when clicking outside
      document.addEventListener("click", () => {
        AdminPanel.closeAllDropdowns()
      })
    },

    // Initialize file uploads
    initFileUploads: () => {
      const fileInputs = document.querySelectorAll('input[type="file"][data-preview]')
      fileInputs.forEach((input) => {
        input.addEventListener("change", function () {
          AdminPanel.previewFile(this)
        })
      })
    },

    // Initialize date pickers
    initDatePickers: () => {
      const dateInputs = document.querySelectorAll('input[type="date"], input[data-datepicker]')
      dateInputs.forEach((input) => {
        // Add date picker functionality if needed
        // This can be extended with a date picker library
      })
    },

    // Start periodic tasks
    startPeriodicTasks: function () {
      // Check for notifications every 30 seconds
      setInterval(() => {
        this.checkNotifications()
      }, 30000)

      // Auto-save drafts every 2 minutes
      setInterval(() => {
        this.autoSaveDrafts()
      }, 120000)
    },

    // Utility functions
    fadeOut: (element) => {
      element.style.transition = "opacity 0.5s ease"
      element.style.opacity = "0"
      setTimeout(() => {
        element.style.display = "none"
      }, 500)
    },

    fadeIn: (element) => {
      element.style.display = "block"
      element.style.opacity = "0"
      element.style.transition = "opacity 0.5s ease"
      setTimeout(() => {
        element.style.opacity = "1"
      }, 10)
    },

    // Form validation
    validateForm: function (form) {
      let isValid = true
      const requiredFields = form.querySelectorAll("[required]")

      requiredFields.forEach((field) => {
        if (!field.value.trim()) {
          this.showFieldError(field, "This field is required")
          isValid = false
        } else {
          this.clearFieldError(field)
        }
      })

      // Email validation
      const emailFields = form.querySelectorAll('input[type="email"]')
      emailFields.forEach((field) => {
        if (field.value && !this.isValidEmail(field.value)) {
          this.showFieldError(field, "Please enter a valid email address")
          isValid = false
        }
      })

      return isValid
    },

    showFieldError: (field, message) => {
      field.classList.add("is-invalid")

      let errorElement = field.parentNode.querySelector(".invalid-feedback")
      if (!errorElement) {
        errorElement = document.createElement("div")
        errorElement.className = "invalid-feedback"
        field.parentNode.appendChild(errorElement)
      }
      errorElement.textContent = message
    },

    clearFieldError: (field) => {
      field.classList.remove("is-invalid")
      const errorElement = field.parentNode.querySelector(".invalid-feedback")
      if (errorElement) {
        errorElement.remove()
      }
    },

    isValidEmail: (email) => {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
      return emailRegex.test(email)
    },

    // Table sorting
    sortTable: (header) => {
      const table = header.closest("table")
      const tbody = table.querySelector("tbody")
      const rows = Array.from(tbody.querySelectorAll("tr"))
      const columnIndex = Array.from(header.parentNode.children).indexOf(header)
      const sortDirection = header.getAttribute("data-sort-direction") || "asc"
      const newDirection = sortDirection === "asc" ? "desc" : "asc"

      // Clear other sort indicators
      table.querySelectorAll("th").forEach((th) => {
        th.removeAttribute("data-sort-direction")
        th.classList.remove("sort-asc", "sort-desc")
      })

      // Set new sort direction
      header.setAttribute("data-sort-direction", newDirection)
      header.classList.add(`sort-${newDirection}`)

      // Sort rows
      rows.sort((a, b) => {
        const aValue = a.children[columnIndex].textContent.trim()
        const bValue = b.children[columnIndex].textContent.trim()

        if (newDirection === "asc") {
          return aValue.localeCompare(bValue, undefined, { numeric: true })
        } else {
          return bValue.localeCompare(aValue, undefined, { numeric: true })
        }
      })

      // Reorder rows in DOM
      rows.forEach((row) => tbody.appendChild(row))
    },

    // Table search
    searchTable: (input) => {
      const tableId = input.getAttribute("data-search-table")
      const table = document.getElementById(tableId)
      const tbody = table.querySelector("tbody")
      const rows = tbody.querySelectorAll("tr")
      const searchTerm = input.value.toLowerCase()

      rows.forEach((row) => {
        const text = row.textContent.toLowerCase()
        if (text.includes(searchTerm)) {
          row.style.display = ""
        } else {
          row.style.display = "none"
        }
      })
    },

    // Modal functions
    openModal: (modalId) => {
      const modal = document.getElementById(modalId)
      if (modal) {
        modal.style.display = "block"
        document.body.style.overflow = "hidden"

        // Focus first input
        const firstInput = modal.querySelector("input, textarea, select")
        if (firstInput) {
          setTimeout(() => firstInput.focus(), 100)
        }
      }
    },

    closeModal: (modalId) => {
      const modal = document.getElementById(modalId)
      if (modal) {
        modal.style.display = "none"
        document.body.style.overflow = ""
      }
    },

    closeAllModals: () => {
      const modals = document.querySelectorAll(".modal")
      modals.forEach((modal) => {
        modal.style.display = "none"
      })
      document.body.style.overflow = ""
    },

    // Dropdown functions
    toggleDropdown: function (trigger) {
      const dropdownId = trigger.getAttribute("data-dropdown")
      const dropdown = document.getElementById(dropdownId)

      if (dropdown) {
        const isOpen = dropdown.classList.contains("show")
        this.closeAllDropdowns()

        if (!isOpen) {
          dropdown.classList.add("show")
        }
      }
    },

    closeAllDropdowns: () => {
      const dropdowns = document.querySelectorAll(".dropdown-menu")
      dropdowns.forEach((dropdown) => {
        dropdown.classList.remove("show")
      })
    },

    // File preview
    previewFile: (input) => {
      const file = input.files[0]
      const previewContainer = input.getAttribute("data-preview")
      const preview = document.getElementById(previewContainer)

      if (file && preview) {
        const reader = new FileReader()

        reader.onload = (e) => {
          if (file.type.startsWith("image/")) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 200px;">`
          } else {
            preview.innerHTML = `<p>File selected: ${file.name}</p>`
          }
        }

        reader.readAsDataURL(file)
      }
    },

    // Tooltip functions
    showTooltip: (element) => {
      const text = element.getAttribute("data-tooltip")
      const tooltip = document.createElement("div")
      tooltip.className = "tooltip"
      tooltip.textContent = text
      tooltip.style.cssText = `
                position: absolute;
                background: #333;
                color: white;
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 1000;
                pointer-events: none;
            `

      document.body.appendChild(tooltip)

      const rect = element.getBoundingClientRect()
      tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + "px"
      tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + "px"

      element._tooltip = tooltip
    },

    hideTooltip: (element) => {
      if (element._tooltip) {
        element._tooltip.remove()
        delete element._tooltip
      }
    },

    // Notification functions
    checkNotifications: function () {
      fetch(this.config.apiUrl + "/notifications.php")
        .then((response) => response.json())
        .then((data) => {
          if (data.count > 0) {
            this.updateNotificationBadge(data.count)
          }
        })
        .catch((error) => console.error("Error checking notifications:", error))
    },

    updateNotificationBadge: (count) => {
      const badge = document.querySelector(".notification-badge")
      if (badge) {
        badge.textContent = count
        badge.style.display = count > 0 ? "block" : "none"
      }
    },

    // Auto-save functionality
    autoSaveDrafts: () => {
      const forms = document.querySelectorAll("form[data-autosave]")
      forms.forEach((form) => {
        const formData = new FormData(form)
        const data = Object.fromEntries(formData.entries())

        // Save to localStorage
        localStorage.setItem(`draft_${form.id}`, JSON.stringify(data))
      })
    },

    // AJAX helpers
    ajax: function (url, options = {}) {
      const defaultOptions = {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      }

      if (this.config.csrfToken) {
        defaultOptions.headers["X-CSRF-Token"] = this.config.csrfToken
      }

      return fetch(url, { ...defaultOptions, ...options }).then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`)
        }
        return response.json()
      })
    },

    // Show loading state
    showLoading: (element) => {
      const spinner = document.createElement("div")
      spinner.className = "spinner"
      spinner.innerHTML = '<div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div>'

      element.style.position = "relative"
      element.appendChild(spinner)
      element._loading = spinner
    },

    hideLoading: (element) => {
      if (element._loading) {
        element._loading.remove()
        delete element._loading
      }
    },

    // Show success message
    showSuccess: function (message) {
      this.showAlert(message, "success")
    },

    // Show error message
    showError: function (message) {
      this.showAlert(message, "danger")
    },

    // Show alert
    showAlert: function (message, type = "info") {
      const alert = document.createElement("div")
      alert.className = `alert alert-${type} alert-dismissible`
      alert.innerHTML = `
                ${message}
                <button type="button" class="close" onclick="this.parentElement.remove()">
                    <span>&times;</span>
                </button>
            `

      const container = document.querySelector(".content") || document.body
      container.insertBefore(alert, container.firstChild)

      // Auto-hide after 5 seconds
      setTimeout(() => {
        if (alert.parentNode) {
          this.fadeOut(alert)
        }
      }, 5000)
    },
  }

  // Initialize when DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
      AdminPanel.init()
    })
  } else {
    AdminPanel.init()
  }

  // Export for global use
  window.AdminPanel = AdminPanel
})()

// Additional utility functions
function confirmDelete(message) {
  return confirm(message || "Are you sure you want to delete this item? This action cannot be undone.")
}

function toggleStatus(id, type, currentStatus) {
  const newStatus = currentStatus === "active" ? "inactive" : "active"

  window.AdminPanel.ajax(`${window.AdminPanel.config.apiUrl}/toggle-status.php`, {
    method: "POST",
    body: JSON.stringify({
      id: id,
      type: type,
      status: newStatus,
    }),
  })
    .then((data) => {
      if (data.success) {
        location.reload()
      } else {
        window.AdminPanel.showError(data.message || "Failed to update status")
      }
    })
    .catch((error) => {
      window.AdminPanel.showError("An error occurred while updating status")
      console.error("Error:", error)
    })
}

function bulkAction(action, selectedIds) {
  if (selectedIds.length === 0) {
    window.AdminPanel.showError("Please select at least one item")
    return
  }

  if (action === "delete" && !confirmDelete(`Are you sure you want to delete ${selectedIds.length} items?`)) {
    return
  }

  window.AdminPanel.ajax(`${window.AdminPanel.config.apiUrl}/bulk-action.php`, {
    method: "POST",
    body: JSON.stringify({
      action: action,
      ids: selectedIds,
    }),
  })
    .then((data) => {
      if (data.success) {
        window.AdminPanel.showSuccess(data.message || "Action completed successfully")
        setTimeout(() => location.reload(), 1000)
      } else {
        window.AdminPanel.showError(data.message || "Action failed")
      }
    })
    .catch((error) => {
      window.AdminPanel.showError("An error occurred while performing the action")
      console.error("Error:", error)
    })
}

// Export for global use
window.confirmDelete = confirmDelete
window.toggleStatus = toggleStatus
window.bulkAction = bulkAction
