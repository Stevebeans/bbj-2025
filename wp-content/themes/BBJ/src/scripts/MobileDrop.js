class MobileDrop {
  constructor() {
    this.openButton = document.querySelector(".mobileMenu__icon");
    this.menu = document.querySelector(".menuShow");
    this.events();
  }

  events() {
    this.openButton.addEventListener("click", () => this.openMenu());
  }

  openMenu() {
    this.openButton.classList.toggle("fa-bars");
    this.openButton.classList.toggle("fa-window-close");
    this.menu.classList.toggle("menuShow--active");
  }
}

export default MobileDrop;
