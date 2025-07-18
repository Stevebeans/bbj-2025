import React, { useState, useEffect } from "react";
import axios from "axios";
import GooglePlacesAutocomplete from "react-google-places-autocomplete";
import AdminHeader from "./AdminHeader.js";
import { useParams } from "react-router-dom";
import { useLocation } from "react-router-dom";

import { useNavigate } from "react-router-dom";

import { GOOGLE_API_KEY } from "./constants";

function AddPlayer({ playerList, seasonList }) {
  const navigate = useNavigate();
  const location = useLocation();
  console.log("Current path is: ", location.pathname);

  const { playerId } = useParams();
  const isEditMode = Boolean(playerId);

  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [fullName, setFullName] = useState("");
  const [nickName, setNickName] = useState("");
  const [dob, setDob] = useState("");
  const [gender, setGender] = useState("");
  const [occupation, setOccupation] = useState("");
  const [facebook, setFacebook] = useState("");
  const [instagram, setInstagram] = useState("");
  const [twitter, setTwitter] = useState("");
  const [tiktok, setTiktok] = useState("");
  const [city, setCity] = useState("");
  const [state, setState] = useState("");
  const [lat, setLat] = useState("");
  const [lng, setLng] = useState("");
  const [playerImage, setPlayerImage] = useState("");
  const [featuredImage, setFeaturedImage] = useState(null);

  const [statusMessage, setStatusMessage] = useState(null);

  //console.log("GOOGLE_API_KEY: ", GOOGLE_API_KEY);

  useEffect(() => {
    if (isEditMode) {
      console.log("player list");
      console.log(playerList);
      const playerData = playerList.find(player => {
        return player.ID === playerId;
      });

      if (playerData) {
        console.log(playerData);
        setFirstName(playerData.first_name);
        setLastName(playerData.last_name);
        setFullName(playerData.first_name + " " + playerData.last_name);
        setNickName(playerData.official_nickname);
        setDob(playerData.date_of_birth);
        setGender(playerData.player_gender);
        setOccupation(playerData.occupation);
        setFacebook(playerData.facebook);
        setInstagram(playerData.instagram);
        setTwitter(playerData.twitter);
        setTiktok(playerData.tiktok);
        setCity(playerData.good_city);
        setState(playerData.good_state);
        setLat(playerData.good_lat);
        setLng(playerData.good_lng);
        setPlayerImage(playerData.featured_image_url);
      }
    } else {
    }

    // if (playerID) {
    //   const url = `/wp-json/bbj/v1/get-player/${playerID}`;

    //   axios
    //     .get(url)
    //     .then(res => {
    //       console.log(res);
    //       let full_name = res.data.first_name + " " + res.data.last_name;
    //       setFirstName(res.data.first_name);
    //       setLastName(res.data.last_name);
    //       setFullName(full_name);
    //       setNickName(res.data.nick_name);
    //       setDob(res.data.date_of_birth);
    //       setCity(res.data.city);
    //     })
    //     .catch(err => {
    //       console.log(err);
    //     });
    // }
  }, [playerId]);

  useEffect(() => {
    setFullName(`${firstName} ${lastName}`);
  }, [firstName, lastName]);

  useEffect(() => {
    const script = document.querySelector('script[src*="https://maps.googleapis.com/maps/api/js?key="]');
    if (script) {
      script.onload = () => setScriptLoaded(true);
    } else {
      setScriptLoaded(true);
    }
  }, []);

  function getPlaceDetails(place_id, setLat, setLng, setCity, setState) {
    const apiUrl = `https://maps.googleapis.com/maps/api/place/details/json?placeid=${place_id}&key=${GOOGLE_API_KEY}`;

    fetch(apiUrl)
      .then(response => response.json())
      .then(data => {
        console.log("place details: ", data);
        const location = data.result.geometry.location;
        const cityState = data.result.address_components;
        setLat(location.lat);
        setLng(location.lng);
        setCity(cityState[0].short_name);
        setState(cityState[3].long_name);
      })
      .catch(error => {
        console.error("An error occurred while fetching place details:", error);
      });
  }

  function addPlayer() {
    setStatusMessage("Saving...");
    console.log("add player");

    const url = "/wp-json/bbj/v1/create-player";

    // Create FormData object
    const formData = new FormData();
    formData.append("first_name", firstName);
    formData.append("last_name", lastName);
    formData.append("full_name", fullName);
    formData.append("nick_name", nickName);
    formData.append("dob", dob);
    formData.append("gender", gender);
    formData.append("occupation", occupation);
    formData.append("facebook", facebook);
    formData.append("instagram", instagram);
    formData.append("twitter", twitter);
    formData.append("tiktok", tiktok);
    formData.append("city", city);
    formData.append("state", state);
    formData.append("lat", lat);
    formData.append("lng", lng);

    if (featuredImage) {
      formData.append("featured_image", featuredImage);
    }

    axios
      .post(url, formData, {
        headers: {
          "Content-Type": "multipart/form-data"
        }
      })
      .then(res => {
        console.log(res);
        setStatusMessage(res.data.message);
      })
      .catch(err => {
        console.log(err);
        setStatusMessage(`Error Saving - ${err.message}`);
      });
  }

  return (
    <React.Fragment>
      <AdminHeader text={isEditMode ? "Edit Player" : "Add Player"} />

      <div className="admin-content">
        <div onClick={() => navigate(`/player-list/`)} className="div-link">
          Return to Seasons List
        </div>
        <div className="admin-sub-title">Basic Info</div>
        <div className="grid grid-cols-7 gap-2 items-center">
          <div className="flex items-center">Full Name / Title</div>
          <div className="pr-2 col-span-5">
            <input type="text" name="full_name" value={fullName} readOnly id="full_name" className="admin-input" />
          </div>
          <div className="row-span-3 flex flex-col border border-gray-200 p-2">
            <div>Image Upload:</div>

            <div>
              <input type="file" name="featured_image" id="featured_image" onChange={e => setFeaturedImage(e.target.files[0])} />
            </div>

            <div>
              <img src={playerImage} alt="" className=" w-40 h-40" />
            </div>
          </div>
          <div className="flex items-center">First Name</div>
          <div className="pr-2">
            <input type="text" name="first_name" value={firstName} id="first_name" className="admin-input" onChange={e => setFirstName(e.target.value)} />
          </div>
          <div className="flex items-center">Last Name</div>
          <div className="pr-2">
            <input type="text" name="last_name" value={lastName} id="last_name" className="admin-input" onChange={e => setLastName(e.target.value)} />
          </div>

          <div className="flex items-center">Nick Name</div>
          <div className="pr-2">
            <input type="text" name="nick_name" value={nickName} id="nick_name" className="admin-input" onChange={e => setNickName(e.target.value)} />
          </div>

          <div className="flex items-center">DOB</div>
          <div className="pr-2">
            <input type="date" name="dob" id="dob" value={dob} className="admin-input" onChange={e => setDob(e.target.value)} />
          </div>

          <div className="flex items-center">Gender</div>

          <div className="pr-2">
            <select name="gender" id="gender" value={gender} onChange={e => setGender(e.target.value)} className="admin-input">
              <option value="">Select Gender</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="non-binary">Non-Binary</option>
              <option value="other">Other</option>
            </select>
          </div>

          <div className="flex items-center">Occupation</div>
          <div className="pr-2">
            <input type="text" name="occupation" value={occupation} id="occupation" className="admin-input" onChange={e => setOccupation(e.target.value)} />
          </div>
        </div>

        <div className="admin-sub-title mt-2">Socials</div>

        <div className="flex justify-between">
          <div className="flex items-center">
            Facebook <input type="text" name="facebook" value={facebook} id="facebook" className="ml-2 admin-input" onChange={e => setFacebook(e.target.value)} />
          </div>

          <div className="flex items-center">
            Instagram <input type="text" name="instagram" value={instagram} id="instagram" className="ml-2 admin-input" onChange={e => setInstagram(e.target.value)} />
          </div>

          <div className="flex items-center">
            Twitter <input type="text" name="twitter" id="twitter" value={twitter} className="ml-2 admin-input" onChange={e => setTwitter(e.target.value)} />
          </div>

          <div className="flex items-center">
            TikTok <input type="text" name="tiktok" id="tiktok" value={tiktok} className="ml-2 admin-input" onChange={e => setTiktok(e.target.value)} />
          </div>
        </div>

        <div className="admin-sub-title mt-2">Location</div>

        <div className="grid grid-cols-10 gap-2 items-center">
          <div className="flex items-center">Lookup</div>
          <div className="pr-2">
            <GooglePlacesAutocomplete
              apiKey={GOOGLE_API_KEY}
              selectProps={{
                value: null,
                onChange: location => {
                  const placeId = location.value.place_id;

                  getPlaceDetails(placeId, setLat, setLng, setCity, setState);
                }
              }}
            />
          </div>

          <div className="flex items-center">City</div>
          <div className="pr-2">
            <input type="text" name="city" value={city} readOnly className="admin-input" />
          </div>

          <div className="flex items-center">State</div>
          <div className="pr-2">
            <input type="text" name="state" value={state} readOnly className="admin-input" />
          </div>

          <div className="flex items-center">LAT</div>
          <div className="pr-2">
            <input type="text" name="lat" value={lat} readOnly className="admin-input" />
          </div>

          <div className="flex items-center">LNG</div>
          <div className="pr-2">
            <input type="text" name="lng" value={lng} readOnly className="admin-input" />
          </div>
        </div>
      </div>

      <button className="admin-btn mt-4" onClick={addPlayer}>
        Submit Player
      </button>
      {statusMessage && <div className="mt-2">{statusMessage}</div>}
    </React.Fragment>
  );
}

export default AddPlayer;
