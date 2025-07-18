import axios from "axios";
import spinner from "./Spinner";

class BBJSearch {
  constructor() {
    this.input = document.getElementById("bbj-search");
    this.resultsDiv = document.getElementById("bbj-search-results");
    this.searchDiv = document.querySelector(".searchDiv");

    this.spinner = document.createElement("div");
    this.spinner.innerHTML = spinner();
    this.spinner.style.display = "none";
    this.spinner.style.position = "absolute";
    this.spinner.style.right = "0px";
    this.spinner.style.top = "50%";
    this.spinner.style.transform = "translateY(-50%)";

    this.resultsDiv.style.display = "none";

    this.searchDiv.appendChild(this.spinner);

    this.timeout = null;

    this.init();
  }

  displayValue(results) {
    console.log("results");
    console.log(results);

    this.spinner.style.display = "none";

    this.resultsDiv.style.display = "block";
    this.resultsDiv.innerHTML = "";

    results.general.forEach(result => {
      let resultDiv = document.createElement("div");
      resultDiv.innerHTML = `<a href="${result.permalink}">${result.title}</a>`;
      this.resultsDiv.appendChild(resultDiv);
    });

    let titleDiv = document.createElement("div");
    titleDiv.innerHTML = "Search Results";
    titleDiv.style.fontWeight = "bold";
    this.resultsDiv.insertBefore(titleDiv, this.resultsDiv.firstChild);
  }

  keyUpHandler() {
    clearTimeout(this.timeout);
    this.timeout = setTimeout(() => {
      this.spinner.style.display = "inline-block";

      this.resultsDiv.innerHTML = "";

      axios
        .get("/wp-json/bbj/v1/search?query=" + this.input.value)
        .then(response => {
          this.displayValue(response.data);
        })
        .catch(error => {
          console.error(error);
        });
    }, 500);
  }

  init() {
    this.input.addEventListener("keydown", event => {
      if (event.key === "Escape") {
        this.input.value = "";
      }
      this.spinner.style.display = "inline-block";
    });

    this.input.addEventListener("keyup", event => {
      this.keyUpHandler();
    });
  }
}

export default BBJSearch;
