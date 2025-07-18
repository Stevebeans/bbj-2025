class SpoilerBarNew {
  constructor() {
    this.toggleButton = document.getElementById("toggleSpoiler");
    this.playerDiv = document.querySelector(".playerDiv");

    this.initialState();
    this.addEventListeners();
  }

  addEventListeners() {
    this.toggleButton.addEventListener("click", () => this.spoilerToggle());
  }

  initialState() {
    const bar = localStorage.getItem("showbar");
    if (bar === null) {
      localStorage.setItem("showbar", true);
    }

    const toggleCheck = localStorage.getItem("showbar");
    if (toggleCheck === "true") {
      this.toggleButton.classList.add("fa-toggle-on");
      this.toggleButton.classList.remove("fa-toggle-off");
      this.playerDiv.classList.remove("player-div-hide");
    } else {
      this.toggleButton.classList.remove("fa-toggle-on");
      this.toggleButton.classList.add("fa-toggle-off");
      this.playerDiv.classList.add("player-div-hide");
    }
  }

  spoilerToggle() {
    const toggleCheck = localStorage.getItem("showbar");
    if (toggleCheck === "true") {
      this.toggleButton.classList.add("fa-toggle-off");
      this.toggleButton.classList.remove("fa-toggle-on");
      this.playerDiv.classList.add("player-div-hide");
      localStorage.setItem("showbar", false);
    } else {
      this.toggleButton.classList.remove("fa-toggle-off");
      this.toggleButton.classList.add("fa-toggle-on");
      this.playerDiv.classList.remove("player-div-hide");
      localStorage.setItem("showbar", true);
    }
  }
}

export default SpoilerBarNew;
