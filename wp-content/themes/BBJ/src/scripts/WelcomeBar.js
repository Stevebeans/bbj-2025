// Function to set a cookie
function setCookie(name, value, days) {
  var date = new Date();
  date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
  var expires = "expires=" + date.toUTCString();
  document.cookie = name + "=" + value + ";" + expires + ";path=/";
}

// Function to get a cookie
function getCookie(name) {
  var nameEQ = name + "=";
  var ca = document.cookie.split(";");
  for (var i = 0; i < ca.length; i++) {
    var c = ca[i];
    while (c.charAt(0) === " ") c = c.substring(1, c.length);
    if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
  }
  return null;
}

// Function to handle the welcome bar
function handleWelcomeBar() {
  console.log("welcome bar!");
  var welcomeBar = document.getElementById("welcome-bar");
  var closeButton = document.getElementById("close-bar");

  if (!welcomeBar || !closeButton) {
    return; // If either element doesn't exist, exit the function
  }

  // Check if the welcomeBar cookie is already set
  if (getCookie("welcomeBar") === "closed") {
    welcomeBar.style.display = "none";
  }

  // Add click event listener to the close button
  closeButton.addEventListener("click", function () {
    welcomeBar.style.display = "none"; // Hide the welcome bar
    setCookie("welcomeBar", "closed", 7); // Set the cookie to expire in 7 days
  });
}

// Call the handleWelcomeBar function when the page loads
window.addEventListener("load", handleWelcomeBar);

export { setCookie, getCookie, handleWelcomeBar };
