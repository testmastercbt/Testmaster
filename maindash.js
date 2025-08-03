    if (window.innerWidth < 768) {
        alert("You're viewing the desktop version. For a better experience, rotate your screen or view on a larger screen. Mobile view wiil be available soon.");
    }
    
function openNotificationPanel() {
    document.getElementById('notificationOverlay').style.display = 'flex';
  }

  function closeNotificationPanel() {
    document.getElementById('notificationOverlay').style.display = 'none';
  }

  // Optional: close on ESC key
  window.addEventListener('keydown', function(e) {
    if (e.key === "Escape") {
      closeNotificationPanel();
    }
  });