import axios from "axios";
import spinner from "./Spinner";

class BBJSearch {
  constructor() {
    this.input = document.getElementById("bbj-search");
    this.resultsDiv = document.getElementById("bbj-search-results");
    this.searchDiv = document.querySelector(".searchDiv");

    // create spinner element
    this.createSpinner();

    //Hide the search result box and add the spinner to it
    this.resultsDiv.style.display = "none";

    // show status of spinner
    this.spinnerDisplayed = false;

    this.init();
  }

  init() {
    this.input.addEventListener("keydown", event => {
      this.keydownHandler(event);
    });

    this.input.addEventListener("keyup", event => {
      this.keyUpHandler();
    });
  }

  // create function keydownHandler to handle keydown events
  keydownHandler(event) {
    console.log("keydownHandler");
    // make the escape key clear the input
    if (event.key === "Escape") {
      this.input.value = "";
    }
  }

  keyUpHandler() {
    clearTimeout(this.timeout);

    if (this.input.value.length) {
      // Turn spinner on
      if (!this.spinnerDisplayed) {
        this.spinnerOn();
      }

      this.resultsDiv.style.display = "block";

      this.timeout = setTimeout(() => {
        this.getResults();
      }, 500);
    }
  }

  async getResults() {
    try {
      const response = await axios.get("/wp-json/bbj/v1/search?query=" + this.input.value);
      this.displayValue(response.data);
    } catch (error) {
      console.error(error);
    } finally {
      // Turn off spinner
      this.spinnerOff();
    }
  }

  displayValue(results) {
    console.log("results");
    console.log(results);

    this.resultsDiv.innerHTML = `
    
    <div id="general-container">
      <h2 class="font-bold">General Results</h2>
      ${results.general.map(result => `<a href="${result.permalink}"><div class="search-result">${result.title}</div></a>`).join("")}
    </div>

    <div>
    ${results.players.length ? `<h2 class="font-bold">Player Results</h2>` : ""}
    ${results.players.length ? results.players.map(result => `<a href="${result.permalink}"><div class="search-result flex text-xl items-center"><img src="${result.player_image.url}" class="h-10 w-10 mr-2 rounded-full">${result.title} - (${result.abbreviation})</div></a> `).join("") : ""}
    </div>

    <div>
    ${results.seasons.length ? `<h2 class="font-bold">Season Results</h2>` : ""}
    ${results.seasons.length ? results.seasons.map(result => `<a href="${result.permalink}"><div class="search-result">${result.title}</div></a>`).join("") : ""}
    </div>
    `;

    // results.general.forEach(result => {
    //   let resultDiv = document.createElement("div");
    //   resultDiv.classList.add("search-result");
    //   resultDiv.innerHTML = `<a href="${result.permalink}">${result.title}</a>`;
    //   this.resultsDiv.appendChild(resultDiv);
    // });

    // let titleDiv = document.createElement("div");
    // titleDiv.innerHTML = "Search Results";
    // titleDiv.style.fontWeight = "bold";
    // this.resultsDiv.insertBefore(titleDiv, this.resultsDiv.firstChild);
  }

  // create function to turn spinner on
  spinnerOn() {
    console.log("trigger");

    this.spinnerDisplayed = true;

    // Set the initial HTML for this.resultsDiv
    this.resultsDiv.innerHTML = `${spinner()} loading results...`;
  }

  // Create function to turn off spinner
  spinnerOff() {
    this.spinner.style.display = "none";
    this.spinnerDisplayed = false;
  }

  createSpinner() {
    this.spinner = document.createElement("div");
    this.spinner.style.display = "none";
    this.searchDiv.appendChild(this.spinner);
  }
}

export default BBJSearch;
