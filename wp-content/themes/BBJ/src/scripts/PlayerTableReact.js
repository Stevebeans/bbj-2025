import React, { useState, useEffect, Fragment } from "react";
import axios from "axios";
import Loading from "./ReactComponents/Loading";
import PlayerCard from "./ReactComponents/PlayerCard";
import SearchInput from "./ReactComponents/SearchInput";
import GenderInput from "./ReactComponents/GenderInput";
import SelectSeason from "./ReactComponents/SelectSeason";

const PlayerTableReact = () => {
  const [players, setPlayers] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState("");
  const [filteredPlayers, setFilteredPlayers] = useState([]);
  const [genderFilter, setGenderFilter] = useState("both");
  const [filteredByGenderPlayers, setFilteredByGenderPlayers] = useState(players);
  const [seasons, setSeasons] = useState([]);
  const [selectedSeason, setSelectedSeason] = useState("all seasons");

  console.log("React");
  useEffect(() => {
    axios
      .get(playerData.root_url + "/wp-json/bbj/v1/player_info/")
      .then(res => {
        setPlayers(res.data);
        setFilteredPlayers(res.data);
        setIsLoading(false);
        console.log(res.data);
      })
      .catch(err => {
        console.log(err);
      });
  }, []);

  // Pull the seasons from the DB and put it into an array to be passed
  useEffect(() => {
    if (players.length > 0) {
      const extractedSeasons = Array.from(new Set(players.map(player => player.season)));
      setSeasons(extractedSeasons);
    }
  }, [players]);

  const handleSearch = event => {
    setSearchQuery(event.target.value);
  };

  const handleGender = event => {
    setGenderFilter(event.target.value);
  };

  const handleSeason = event => {
    setSelectedSeason(event.target.value);
  };

  useEffect(() => {
    setFilteredByGenderPlayers(
      players
        .filter(player => {
          if (genderFilter === "both" || genderFilter === "Choose Gender") {
            return true;
          } else {
            return player.gender === genderFilter;
          }
        })
        .filter(player => {
          if (selectedSeason === "all seasons" || selectedSeason === "") {
            return true;
          } else {
            return player.season === selectedSeason;
          }
        })
        .filter(player => {
          return player.first_name.toLowerCase().includes(searchQuery.toLowerCase()) || player.last_name.toLowerCase().includes(searchQuery.toLowerCase()) || player.season.toLowerCase().includes(searchQuery.toLowerCase());
        })
    );
  }, [genderFilter, players, searchQuery, selectedSeason]);

  console.log(searchQuery);
  return (
    <Fragment>
      {isLoading ? (
        <Loading />
      ) : (
        <div>
          <div className="mb-2 flex">
            <SearchInput handleSearch={handleSearch} />
            <GenderInput handleGender={handleGender} />
            <SelectSeason seasons={seasons} handleSeason={handleSeason} />
          </div>
          <div className="bbj-player-card-wrap">{filteredByGenderPlayers.length > 0 ? filteredByGenderPlayers.map(player => <PlayerCard key={player.id} player={player} />) : <div>No Results Found</div>}</div>
        </div>
      )}
    </Fragment>
  );
};

export default PlayerTableReact;
