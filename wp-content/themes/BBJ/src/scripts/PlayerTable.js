import axios from "axios";

class PlayerTable {
  constructor() {
    this.mainTable = document.getElementById("player-table");
    this.spinner = document.getElementById("spinner");
    this.searchBar = document.getElementById("searchBar");
    this.genderFilter = document.getElementById("gender_filter");
    axios.defaults.headers.common["X-WP-Nonce"] = playerData.nonce;

    console.log(this.response);

    console.log(this.searchBar);

    if (this.mainTable) {
      this.isLoaded = false;
      this.pageLoad();
      this.events();
    }
  }

  events() {
    this.searchBar.addEventListener("keyup", e => {
      this.searchString = e.target.value.toLowerCase();
      this.new_table();
    });

    this.genderFilter.addEventListener("change", e => {
      this.genderOption = e.target.value;
      this.new_table();
    });

    console.log(this.searchBar);
  }

  new_table(data) {
    const searchText = this.searchString;
    let char = this.searchString;

    let combinedFilter = this.response.filter(char => {
      const name = char.first_name.toLowerCase().includes(searchText) || char.last_name.toLowerCase().includes(searchText);
      //console.log(name);
      this.newGender = this.genderOption;

      console.log(this.genderOption);

      if (this.genderOption === "both" || this.genderOption == undefined) {
        return true;
      }

      return name && char.gender == this.newGender;
    });

    console.log(combinedFilter);

    //console.log(combinedFilter);
    this.build_table(combinedFilter);
  }

  filter_gender() {
    let data = this.response;
    this.genderTrue = data.filter(char => {
      const gender = char.gender;
      return gender;
    });
    return data;
  }

  async pageLoad() {
    let response = [];
    this.set_loading();
    this.response = await axios
      .get(playerData.root_url + "/wp-json/bbj/v1/player_info/")
      .then(res => {
        this.close_load();
        this.build_table(res.data);
        return res.data;
      })
      .catch(error => console.log(error));
  }

  filter_results(data) {
    console.log(data);
  }

  build_table(data) {
    this.mainTable.innerHTML = `
    <div class="pt-player-card-contain">
      ${data
        .map(
          p => `
        <a href="${p.player_link}">
          <div class="pt-player-card">
          
          <div class="pt-player-card-img"><img src="${p.profile}" alt="${p.first_name} ${p.last_name} player card"></div>
          <div class="pt-player-card-info">

            <div class="pt-header"><h3>${p.first_name}</h3><h2>${p.last_name}</h2>
              <h4>${p.season}<span> (${p.finished} place)</span></h4>
            </div>
            <div class="pt-body">
              <div>Age</div>
              <div>${p.then_age}</div>
              <div>Age <span>(now)</span></div>
              <div>${p.current_age}</div>
              <div>Location</div>
              <div>${p.location}</div>

            </div>

            
          </div>
          <div class="pt-player-card-bottom">
            <div class="pt-stats">
              <div class="pt-stats-header">Season Stats</div>
              <div class="pt-stats-body">
                <div class="pt-stats-hd">HOH</div>   
                <div class="pt-stats-hd">POV</div>
                <div class="pt-stats-hd">NOM</div>
                <div class="pt-stats-hd">MISC</div>
                <div class="pt-stats-hd">SAVED</div>
                <div class="pt-stats-bd">${p.hoh_wins}</div>             
                <div class="pt-stats-bd">${p.pov_wins}</div>
                <div class="pt-stats-bd">${p.nom}</div>
                <div class="pt-stats-bd">${p.misc_wins}</div>
                <div class="pt-stats-bd">${p.saved}</div>
              </div>

            </div>
          </div>
        </div>

        </a>

      `
        )
        .join("")}
    </div>`;
  }

  set_loading() {
    this.spinner.style.display = "block";
  }

  close_load() {
    this.spinner.style.display = "none";
  }
}

export default PlayerTable;
