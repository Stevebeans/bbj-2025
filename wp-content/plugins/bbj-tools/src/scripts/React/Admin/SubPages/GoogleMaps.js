import React, { useState } from "react";
import { GoogleMap, useJsApiLoader } from "@react-google-maps/api";
import { GOOGLE_API_KEY } from "./constants";

function MapComponent() {
  console.log("google api key: ", GOOGLE_API_KEY);
  const { isLoaded } = useJsApiLoader({
    id: "google-map-script",
    googleMapsApiKey: { GOOGLE_API_KEY }
  });

  const [latLng, setLatLng] = useState(null);

  const mapContainerStyle = {
    height: "400px",
    width: "800px"
  };

  const center = {
    lat: -3.745,
    lng: -38.523
  };

  const handleClick = e => {
    setLatLng(e.latLng.toJSON());
  };

  return isLoaded ? (
    <div>
      <GoogleMap mapContainerStyle={mapContainerStyle} center={center} zoom={10} onClick={handleClick} />
      {latLng && (
        <div>
          Latitude: {latLng.lat}, Longitude: {latLng.lng}
        </div>
      )}
    </div>
  ) : (
    <div>Loading...</div>
  );
}

export default MapComponent;
