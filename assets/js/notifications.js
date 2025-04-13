/**
 * Notifications - Handles the notification functionality
 */

document.addEventListener("DOMContentLoaded", () => {
    // Notification button
    const notificationButton = document.getElementById("notificationButton")
    const notificationModal = document.getElementById("notificationModal")
    const closeNotificationModal = document.getElementById("closeNotificationModal")
    const modalOverlay = document.getElementById("modalOverlay")
  
    // Open notification modal
    if (notificationButton && notificationModal) {
      notificationButton.addEventListener("click", function () {
        notificationModal.classList.add("show")
        document.body.classList.add("modal-open")
  
        // Mark notifications as read via AJAX when opened
        const userId = this.getAttribute("data-user-id")
        if (userId) {
          fetch("index.php?page=mark-notifications-read", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded",
              "X-Requested-With": "XMLHttpRequest",
            },
            body: `user_id=${userId}`,
          })
            .then((response) => response.json())
            .then((data) => {
              if (data.success) {
                // Remove notification badge
                const badge = document.querySelector(".notification-badge")
                if (badge) {
                  badge.style.display = "none"
                }
              }
            })
            .catch((error) => console.error("Error marking notifications as read:", error))
        }
      })
    }
  
    // Close notification modal
    if (closeNotificationModal && notificationModal) {
      closeNotificationModal.addEventListener("click", () => {
        notificationModal.classList.remove("show")
        document.body.classList.remove("modal-open")
      })
    }
  
    // Close modal when clicking on overlay
    if (modalOverlay && notificationModal) {
      modalOverlay.addEventListener("click", () => {
        notificationModal.classList.remove("show")
        document.body.classList.remove("modal-open")
      })
    }
  
    // Toast notification system
    window.showToast = (message, type = "success") => {
      const toast = document.createElement("div")
      toast.className = `toast toast-${type}`
      toast.innerHTML = `
        <div class="toast-icon">
          <i class="fas ${type === "success" ? "fa-check-circle" : "fa-exclamation-circle"}"></i>
        </div>
        <div class="toast-content">${message}</div>
        <button class="toast-close"><i class="fas fa-times"></i></button>
      `
  
      document.body.appendChild(toast)
  
      // Show toast with animation
      setTimeout(() => {
        toast.classList.add("show")
      }, 10)
  
      // Auto hide after 5 seconds
      const hideTimeout = setTimeout(() => {
        hideToast(toast)
      }, 5000)
  
      // Close button
      const closeBtn = toast.querySelector(".toast-close")
      if (closeBtn) {
        closeBtn.addEventListener("click", () => {
          clearTimeout(hideTimeout)
          hideToast(toast)
        })
      }
    }
  
    function hideToast(toast) {
      toast.classList.remove("show")
      toast.classList.add("hide")
  
      // Remove from DOM after animation
      setTimeout(() => {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast)
        }
      }, 300)
    }
  })
  
  