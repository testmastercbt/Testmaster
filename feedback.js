function clearFormAfterSubmit() {
  // Delay clearing to let PHP submission process
  setTimeout(() => {
    document.getElementById("feedbackForm").reset();
  }, 100); // Adjust delay if needed

  return true; // Allow form to submit
}
