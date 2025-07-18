class AutoFill {
  constructor() {
    this.button = document.getElementById("active-fill");
    this.check = document.getElementsByName("active[]");
    this._init();
  }

  _init() {
    this.button.addEventListener("click", e => {
      e.preventDefault();

      this.autoFill();
    });
  }

  autoFill() {
    this.check.forEach(item => {
      // Check the box if the data-active attribute is equal to "1"
      if (item.getAttribute("data-active") === "1") {
        item.checked = true;
      }
    });
  }
}

export default AutoFill;
