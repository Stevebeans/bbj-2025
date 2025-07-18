class SpoilerBar {
  constructor() {
    this.toggleButton = document.getElementById("toggleSpoiler");
    this.spoilerBar = document.querySelector(".spoilerBar");
    this.playerDiv = document.querySelector(".playerDiv");
    this.body = document.querySelector(".bodyContainer");
    this.showBar = true;

    this.initialState();
    this.addEventListeners();
  }

  addEventListeners() {
    this.toggleButton.addEventListener("click", () => this.spoilerToggle());
  }

  initialState() {
    if (localStorage.getItem("showbar") === null) {
      localStorage.setItem("showbar", this.showBar);
    }
  }

  spoilerToggle() {
    const buttonCheck = localStorage.getItem("showbar");

    if (buttonCheck === "true") {
      this.toggleButton.classList.add("fa-toggle-off");
      this.toggleButton.classList.remove("fa-toggle-on");
      this.playerDiv.classList.add("slideUp");
      this.playerDiv.classList.remove("slideDown");
      localStorage.setItem("showbar", "false");
    } else {
      this.toggleButton.classList.add("fa-toggle-on");
      this.toggleButton.classList.remove("fa-toggle-off");
      this.playerDiv.classList.add("slideDown");
      this.playerDiv.classList.remove("slideUp");
    }
  }
}

export default SpoilerBar;
