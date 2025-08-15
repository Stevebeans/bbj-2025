class PlayerAdmin {
  constructor() {
    // variables
    this.googleField = document.querySelector("#address_street");
    this.latField = document.querySelector("#lat");
    this.lngField = document.querySelector("#lng");
    this.stateField = document.querySelector("#player_state");
    this.cityField = document.querySelector("#player_city");
    this.autoComplete = new google.maps.places.Autocomplete(this.googleField, {
      types: ["geocode"],
      componentRestrictions: { country: ["us"] },
      fields: ["address_components", "geometry", "place_id"]
    });

    this.init();
  }

  init() {
    this.setupEventListeners();
  }

  setupEventListeners() {
    // listen for key down and prevent default action
    this.googleField.addEventListener("keydown", e => {
      if (e.key === "Enter") {
        e.preventDefault();
      }
    });

    // If they edit after select, clear everything
    this.googleField.addEventListener("input", e => {
      this.latField.value = "";
      this.lngField.value = "";
      this.cityField.value = "";
      this.stateField.value = "";
    });

    this.autoComplete.addListener("place_changed", () => {
      const place = this.autoComplete.getPlace();
      if (place.geometry) {
        this.latField.value = place.geometry.location.lat();
        this.lngField.value = place.geometry.location.lng();
        this.setCityAndState(place);
      } else {
        console.error("No details available for input: '" + place.name + "'");
      }
    });
  }

  setCityAndState(place) {
    const ac = place.address_components || [];
    const get = (type, useShort = false) => {
      const comp = ac.find(c => c.types.includes(type));
      return comp ? (useShort ? comp.short_name : comp.long_name) : "";
    };

    // prefer locality, but fallback to postal_town or sublocality
    const city = get("locality") || get("postal_town") || get("sublocality") || get("administrative_area_level_3");

    // use long name for state
    const state = get("administrative_area_level_1");

    this.cityField.value = city;
    this.stateField.value = state;
  }
}

document.addEventListener("DOMContentLoaded", function () {
  new PlayerAdmin();
});
