import axios from "axios";
import spinner from "./Spinner";

class BBJSearch {
  constructor(rootElement) {
    this.root = rootElement;
    this.input = this.root.querySelector(".bbj-search__input");
    this.resultsDiv = this.root.querySelector(".bbj-search__results");
    this.searchBox = this.root.querySelector(".bbj-search__box");

    // state
    this.timeout = null;
    this.abortController = null;
    this.debounceMs = 300;
    this.lastQuery = "";
    this.isOpen = false;

    // a11y hint (optional)
    this.resultsDiv.setAttribute("aria-live", "polite");

    // start closed
    this.hideResults();

    this.init();
  }

  init() {
    // typing
    this.input.addEventListener("input", () => this.onInput());
    this.input.addEventListener("keydown", e => this.onKeyDown(e));
    this.input.addEventListener("focus", () => {
      if (this.resultsDiv.innerHTML.trim()) this.showResults();
    });

    // click-outside to close
    document.addEventListener("click", e => {
      if (!this.root.contains(e.target)) this.hideResults();
    });
  }

  onKeyDown(event) {
    // Escape clears and closes
    if (event.key === "Escape") {
      this.input.value = "";
      this.cancelPending();
      this.hideResults();
    }
  }

  onInput() {
    clearTimeout(this.timeout);

    const q = this.input.value.trim();

    // if empty or too short, close + cancel
    if (q.length < 2) {
      this.cancelPending();
      this.hideResults();
      return;
    }

    // if same as last rendered query, don't re-hit
    if (q === this.lastQuery) {
      this.showResults();
      return;
    }

    // debounce
    this.timeout = setTimeout(() => this.search(q), this.debounceMs);
  }

  cancelPending() {
    if (this.abortController) {
      this.abortController.abort();
      this.abortController = null;
    }
  }

  async search(query) {
    this.cancelPending();
    this.lastQuery = query;

    // show spinner
    this.resultsDiv.innerHTML = `${spinner()} Loading results…`;
    this.showResults();

    try {
      this.abortController = new AbortController();
      const res = await axios.get(`/wp-json/bbj/v1/search?query=${encodeURIComponent(query)}`, { signal: this.abortController.signal });

      this.renderResults(res.data);
    } catch (err) {
      // ignore cancels
      if (axios.isCancel?.(err) || err?.name === "CanceledError") return;

      console.error(err);
      this.resultsDiv.innerHTML = `<div class="p-2 text-sm text-red-600">Something went wrong. Please try again.</div>`;
      this.showResults();
    } finally {
      this.abortController = null;
    }
  }

  renderResults(results) {
    // guard against undefined sections
    const general = Array.isArray(results?.general) ? results.general : [];
    const players = Array.isArray(results?.players) ? results.players : [];
    const seasons = Array.isArray(results?.seasons) ? results.seasons : [];

    const section = (title, html) =>
      html
        ? `<div class="mb-2">
             <h2 class="font-bold mb-1">${title}</h2>
             ${html}
           </div>`
        : "";

    const list = items =>
      items
        .map(
          r =>
            `<a href="${r.permalink}">
               <div class="search-result px-2 py-1 hover:bg-gray-100 rounded">${r.title}</div>
             </a>`
        )
        .join("");

    const playerList = players
      .map(
        r =>
          `<a href="${r.permalink}">
             <div class="search-result flex items-center px-2 py-1 hover:bg-gray-100 rounded">
               ${r?.player_image?.url ? `<img src="${r.player_image.url}" class="h-10 w-10 mr-2 rounded-full" alt="">` : ""}
               <span class="text-base md:text-lg">${r.title}${r?.abbreviation ? ` <span class="opacity-70">(${r.abbreviation})</span>` : ""}</span>
             </div>
           </a>`
      )
      .join("");

    const html = section("General Results", list(general)) + section(players.length ? "Player Results" : "", playerList) + section(seasons.length ? "Season Results" : "", list(seasons));

    this.resultsDiv.innerHTML = html || `<div class="p-2 text-sm opacity-75">No results for “${this.lastQuery}”.</div>`;

    this.showResults();
  }

  showResults() {
    this.resultsDiv.style.display = "block";
    this.isOpen = true;
  }

  hideResults() {
    this.resultsDiv.style.display = "none";
    this.isOpen = false;
  }
}

export default BBJSearch;
