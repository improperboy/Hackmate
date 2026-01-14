// Main JavaScript file for hackathon management system

// Global variables
let currentUser = null
let notifications = []

// Initialize application
document.addEventListener("DOMContentLoaded", () => {
  initializeApp()
  setupEventListeners()
  loadNotifications()
})

// Initialize application
function initializeApp() {
  // Check if user is logged in
  checkAuthStatus()

  // Initialize tooltips
  initializeTooltips()

  // Initialize modals
  initializeModals()

  // Setup CSRF token for AJAX requests
  setupCSRFToken()
}

// Setup event listeners
function setupEventListeners() {
  // Form submissions
  document.querySelectorAll('form[data-ajax="true"]').forEach((form) => {
    form.addEventListener("submit", handleAjaxForm)
  })

  // Confirmation dialogs
  document.querySelectorAll("[data-confirm]").forEach((element) => {
    element.addEventListener("click", handleConfirmation)
  })

  // Auto-refresh elements
  document.querySelectorAll("[data-auto-refresh]").forEach((element) => {
    const interval = Number.parseInt(element.dataset.autoRefresh) || 30000
    setInterval(() => refreshElement(element), interval)
  })

  // Search functionality
  const searchInputs = document.querySelectorAll("[data-search]")
  searchInputs.forEach((input) => {
    input.addEventListener("input", debounce(handleSearch, 300))
  })
}

// Handle AJAX form submissions
function handleAjaxForm(event) {
  event.preventDefault()

  const form = event.target
  const formData = new FormData(form)
  const submitButton = form.querySelector('button[type="submit"]')

  // Show loading state
  showLoading(submitButton)

  fetch(form.action, {
    method: "POST",
    body: formData,
    headers: {
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then((response) => response.json())
    .then((data) => {
      hideLoading(submitButton)

      if (data.success) {
        showNotification(data.message, "success")

        // Reset form if specified
        if (form.dataset.resetOnSuccess === "true") {
          form.reset()
        }

        // Redirect if specified
        if (data.redirect) {
          setTimeout(() => {
            window.location.href = data.redirect
          }, 1500)
        }

        // Refresh page section if specified
        if (form.dataset.refreshTarget) {
          refreshElement(document.querySelector(form.dataset.refreshTarget))
        }
      } else {
        showNotification(data.message, "error")
      }
    })
    .catch((error) => {
      hideLoading(submitButton)
      showNotification("An error occurred. Please try again.", "error")
      console.error("Form submission error:", error)
    })
}

// Handle confirmation dialogs
function handleConfirmation(event) {
  const message = event.target.dataset.confirm
  if (!confirm(message)) {
    event.preventDefault()
    return false
  }
}

// Show loading state
function showLoading(element) {
  if (element) {
    element.disabled = true
    element.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...'
  }
}

// Hide loading state
function hideLoading(element) {
  if (element) {
    element.disabled = false
    // Restore original text (you might want to store this)
    element.innerHTML = element.dataset.originalText || "Submit"
  }
}

// Show notification
function showNotification(message, type = "info") {
  const notification = document.createElement("div")
  notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transition-all duration-300 ${getNotificationClasses(type)}`
  notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${getNotificationIcon(type)} mr-3"></i>
            <span class="flex-1">${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-3 text-lg">&times;</button>
        </div>
    `

  document.body.appendChild(notification)

  // Auto remove after 5 seconds
  setTimeout(() => {
    if (notification.parentElement) {
      notification.remove()
    }
  }, 5000)
}

// Get notification classes based on type
function getNotificationClasses(type) {
  const classes = {
    success: "bg-green-500 text-white",
    error: "bg-red-500 text-white",
    warning: "bg-yellow-500 text-white",
    info: "bg-blue-500 text-white",
  }
  return classes[type] || classes.info
}

// Get notification icon based on type
function getNotificationIcon(type) {
  const icons = {
    success: "fa-check-circle",
    error: "fa-exclamation-circle",
    warning: "fa-exclamation-triangle",
    info: "fa-info-circle",
  }
  return icons[type] || icons.info
}

// Handle search functionality
function handleSearch(event) {
  const input = event.target
  const searchTerm = input.value.toLowerCase()
  const targetSelector = input.dataset.search
  const targets = document.querySelectorAll(targetSelector)

  targets.forEach((target) => {
    const text = target.textContent.toLowerCase()
    if (text.includes(searchTerm)) {
      target.style.display = ""
    } else {
      target.style.display = "none"
    }
  })
}

// Debounce function
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

// Refresh element content
function refreshElement(element) {
  if (!element || !element.dataset.refreshUrl) return

  fetch(element.dataset.refreshUrl)
    .then((response) => response.text())
    .then((html) => {
      element.innerHTML = html
    })
    .catch((error) => {
      console.error("Refresh error:", error)
    })
}

// Initialize tooltips
function initializeTooltips() {
  document.querySelectorAll("[data-tooltip]").forEach((element) => {
    element.addEventListener("mouseenter", showTooltip)
    element.addEventListener("mouseleave", hideTooltip)
  })
}

// Show tooltip
function showTooltip(event) {
  const element = event.target
  const text = element.dataset.tooltip

  const tooltip = document.createElement("div")
  tooltip.className = "absolute z-50 px-2 py-1 text-sm text-white bg-gray-800 rounded shadow-lg"
  tooltip.textContent = text
  tooltip.id = "tooltip"

  document.body.appendChild(tooltip)

  // Position tooltip
  const rect = element.getBoundingClientRect()
  tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + "px"
  tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + "px"
}

// Hide tooltip
function hideTooltip() {
  const tooltip = document.getElementById("tooltip")
  if (tooltip) {
    tooltip.remove()
  }
}

// Initialize modals
function initializeModals() {
  document.querySelectorAll("[data-modal-target]").forEach((trigger) => {
    trigger.addEventListener("click", openModal)
  })

  document.querySelectorAll("[data-modal-close]").forEach((closeBtn) => {
    closeBtn.addEventListener("click", closeModal)
  })
}

// Open modal
function openModal(event) {
  const targetId = event.target.dataset.modalTarget
  const modal = document.getElementById(targetId)
  if (modal) {
    modal.classList.remove("hidden")
    document.body.style.overflow = "hidden"
  }
}

// Close modal
function closeModal(event) {
  const modal = event.target.closest(".modal")
  if (modal) {
    modal.classList.add("hidden")
    document.body.style.overflow = ""
  }
}

// Check authentication status
function checkAuthStatus() {
  // This would typically make an AJAX call to check if user is still logged in
  // For now, we'll just check if we're on a protected page
  const protectedPages = ["/admin/", "/mentor/", "/participant/", "/volunteer/"]
  const currentPath = window.location.pathname

  if (protectedPages.some((page) => currentPath.includes(page))) {
    // User is on a protected page, assume they're authenticated
    currentUser = {
      authenticated: true,
      role: getCurrentRole(),
    }
  }
}

// Get current user role from URL
function getCurrentRole() {
  const path = window.location.pathname
  if (path.includes("/admin/")) return "admin"
  if (path.includes("/mentor/")) return "mentor"
  if (path.includes("/participant/")) return "participant"
  if (path.includes("/volunteer/")) return "volunteer"
  return null
}

// Setup CSRF token for AJAX requests
function setupCSRFToken() {
  const token = document.querySelector('meta[name="csrf-token"]')
  if (token) {
    // Set default headers for fetch requests
    const originalFetch = window.fetch
    window.fetch = (url, options = {}) => {
      options.headers = options.headers || {}
      options.headers["X-CSRF-Token"] = token.getAttribute("content")
      return originalFetch(url, options)
    }
  }
}

// Load notifications
function loadNotifications() {
  // This would typically load notifications from the server
  // For now, we'll just initialize an empty array
  notifications = []
}

// Utility functions
const utils = {
  // Format date
  formatDate: (dateString) => {
    const date = new Date(dateString)
    return date.toLocaleDateString() + " " + date.toLocaleTimeString()
  },

  // Format file size
  formatFileSize: (bytes) => {
    if (bytes === 0) return "0 Bytes"
    const k = 1024
    const sizes = ["Bytes", "KB", "MB", "GB"]
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return Number.parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i]
  },

  // Validate email
  validateEmail: (email) => {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return re.test(email)
  },

  // Validate URL
  validateURL: (url) => {
    try {
      new URL(url)
      return true
    } catch {
      return false
    }
  },
}

// Export utils for global access
window.hackathonUtils = utils

// Countdown timer functionality
function initializeCountdown(endTime, elementId) {
  const countdownElement = document.getElementById(elementId)
  if (!countdownElement) return

  const timer = setInterval(() => {
    const now = new Date().getTime()
    const distance = new Date(endTime).getTime() - now

    if (distance < 0) {
      countdownElement.innerHTML = '<div class="text-red-600 font-bold">TIME EXPIRED</div>'
      clearInterval(timer)
      return
    }

    const days = Math.floor(distance / (1000 * 60 * 60 * 24))
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60))
    const seconds = Math.floor((distance % (1000 * 60)) / 1000)

    const daysEl = countdownElement.querySelector("#days")
    const hoursEl = countdownElement.querySelector("#hours")
    const minutesEl = countdownElement.querySelector("#minutes")
    const secondsEl = countdownElement.querySelector("#seconds")

    if (daysEl) daysEl.textContent = days.toString().padStart(2, "0")
    if (hoursEl) hoursEl.textContent = hours.toString().padStart(2, "0")
    if (minutesEl) minutesEl.textContent = minutes.toString().padStart(2, "0")
    if (secondsEl) secondsEl.textContent = seconds.toString().padStart(2, "0")
  }, 1000)
}

// Make countdown function globally available
window.initializeCountdown = initializeCountdown
