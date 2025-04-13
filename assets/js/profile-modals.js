/**
 * Profile Modals - Handles the modal functionality for profile editing and room changing
 */

document.addEventListener("DOMContentLoaded", () => {
  // Edit Profile Button
  const editProfileBtn = document.getElementById("editProfileBtn")
  if (editProfileBtn) {
    editProfileBtn.addEventListener("click", function (e) {
      e.preventDefault()

      // Show loading state
      this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...'
      this.disabled = true

      // Fetch the profile edit modal via AJAX
      fetch("index.php?page=profile-edit-modal", {
        method: "POST",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error("Network response was not ok: " + response.statusText)
          }
          return response.text()
        })
        .then((html) => {
          // Check if the response is actually HTML for the modal
          if (html.includes("profileEditModal")) {
            // Insert the modal HTML into the document
            document.body.insertAdjacentHTML("beforeend", html)
          } else {
            console.error("Invalid response format:", html)
            alert("Failed to load profile edit form. Please try again.")
          }

          // Reset button state
          this.innerHTML = '<i class="fas fa-edit"></i> Edit Profile'
          this.disabled = false
        })
        .catch((error) => {
          console.error("Error loading profile edit modal:", error)

          // Reset button state
          this.innerHTML = '<i class="fas fa-edit"></i> Edit Profile'
          this.disabled = false

          // Show error message
          alert("Failed to load profile edit form. Please try again.")
        })
    })
  }

  // Change Room Button
  const changeRoomBtn = document.getElementById("changeRoomBtn")
  if (changeRoomBtn) {
    changeRoomBtn.addEventListener("click", function (e) {
      e.preventDefault()

      // Show loading state
      this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...'
      this.disabled = true

      // Fetch the change room modal via AJAX
      fetch("index.php?page=change-room-modal", {
        method: "POST",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      })
        .then((response) => response.text())
        .then((html) => {
          // Insert the modal HTML into the document
          document.body.insertAdjacentHTML("beforeend", html)

          // Reset button state
          this.innerHTML = '<i class="fas fa-exchange-alt"></i> Change Room'
          this.disabled = false
        })
        .catch((error) => {
          console.error("Error loading change room modal:", error)

          // Reset button state
          this.innerHTML = '<i class="fas fa-exchange-alt"></i> Change Room'
          this.disabled = false

          // Show error message
          alert("Failed to load room change form. Please try again.")
        })
    })
  }
})

