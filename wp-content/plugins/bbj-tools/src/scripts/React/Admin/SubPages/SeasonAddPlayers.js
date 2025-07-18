import React, { useEffect, useState, useContext } from "react";
import { AdminContext } from "../AdminContext";
import { useParams } from "react-router-dom";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { faChevronDown, faChevronUp } from "@fortawesome/free-solid-svg-icons";
import { faMinus } from "@fortawesome/free-solid-svg-icons";
import PlayerTable from "./PlayerTable";

function SeasonAddPlayers({ startDate, endDate }) {
  const {
    // from context
    dynamicFilteredPlayerList,
    dynamicNonSeasonPlayerList,
    evictedPlayers,
    voteToWinList,
    checkChanged,
    playerChanges,

    fetchSeasonPlayers,
    addPlayerToSeason,
    removePlayerFromSeason,
    savePlayerChanges,
    handlePlayerInputChange,

    setCheckChanged // if needed
    // etc...
  } = useContext(AdminContext);

  const { seasonId } = useParams();

  // local state for 'selectedPlayer'
  const [selectedPlayer, setSelectedPlayer] = useState("");

  // local state for show/hide players
  const storedShowPlayers = localStorage.getItem("showPlayers");
  const initialShowPlayers = storedShowPlayers ? JSON.parse(storedShowPlayers) : true;
  const [showPlayers, setShowPlayers] = useState(initialShowPlayers);

  // store `showPlayers` in localStorage
  useEffect(() => {
    localStorage.setItem("showPlayers", JSON.stringify(showPlayers));
  }, [showPlayers]);

  // On mount or when seasonId changes, fetch the season’s player data
  useEffect(() => {
    fetchSeasonPlayers(seasonId);
  }, [seasonId, fetchSeasonPlayers]);

  // handler for input changes
  const handleInputChange = (player_id, name) => e => {
    const inputValue = e.target.type === "checkbox" ? (e.target.checked ? 1 : 0) : e.target.value;
    handlePlayerInputChange(seasonId, player_id, name, inputValue);
  };

  // add new player
  const handleAddPlayer = () => {
    addPlayerToSeason(seasonId, selectedPlayer);
  };

  // remove player
  const handleDelete = player_id => {
    removePlayerFromSeason(seasonId, player_id);
  };

  // ...
  // The rest is mostly your render logic
  return (
    <div>
      <div>{`Season ID: ${seasonId}`}</div>

      {/* Toggle the player list */}
      <div className="flex justify-between items-center border-b border-gray-500 bg-gray-50 hover:cursor-pointer" onClick={() => setShowPlayers(!showPlayers)}>
        <div className="pl-4">
          <h2>Player List:</h2>
        </div>
        <div className="pr-4 text-4xl font-bold text-blue-900 border-2">
          sdf
          <button onClick={() => setShowPlayers(!showPlayers)} className="ml-2">
            {showPlayers ? <faChevronDown /> : <faChevronUp />}
          </button>
        </div>
      </div>

      {checkChanged && (
        <div className="border-b border-gray-400 mb-4 pb-4">
          <h2 className="text-red-500">Save Changes?</h2>
          <button onClick={savePlayerChanges} className="admin-btn">
            Save Changes
          </button>
        </div>
      )}

      {showPlayers && (
        <div className="border-b border-gray-400 mb-4 pb-4">
          <table className="w-full border-collapse">
            <thead>
              <tr>
                <th></th>
                <th></th>
                <th colSpan="7" className="bg-gray-50 border border-gray-200 py-1 text-center">
                  In-Season
                </th>
                <th colSpan="4" className="bg-gray-50 border border-gray-200 py-1 text-center">
                  Off-Season
                </th>
                <th></th>
              </tr>
              <tr>
                <th></th>
                <th className="font-bold text-center">Name</th>
                <th className="font-bold text-center">HOH</th>
                <th className="font-bold text-center">POV</th>
                <th className="font-bold text-center">NOM</th>
                <th className="font-bold text-center">Jury</th>
                <th className="font-bold text-center">Evicted</th>
                <th className="font-bold text-center">MISC</th>
                <th className="font-bold text-center border-r border-gray-300">Evic Date</th>
                <th className="font-bold text-center bg-gray-50">Winner</th>
                <th className="font-bold text-center bg-gray-50">Runner-Up</th>
                <th className="font-bold text-center bg-gray-50">AFP</th>
                <th className="font-bold text-center bg-gray-50  border-r border-gray-300">Vote to Win</th>
                <th className="font-bold text-center">Delete</th>
              </tr>
            </thead>
            <PlayerTable showPlayers={showPlayers} dynamicFilteredPlayerList={dynamicFilteredPlayerList} handleInputChange={handleInputChange} />
          </table>

          {checkChanged && (
            <div className="border-b border-gray-400 mb-4 pb-4">
              <h2 className="text-red-500">Save Changes?</h2>
              <button onClick={savePlayerChanges} className="admin-btn">
                Save Changes
              </button>
            </div>
          )}

          {/* Add New Player */}
          <div className="mt-4 border-t border-gray-300">
            <h2>Add Player</h2>
            <select onChange={e => setSelectedPlayer(e.target.value)}>
              <option value="">Select Player</option>
              {dynamicNonSeasonPlayerList
                .sort((a, b) => a.first_name.localeCompare(b.first_name))
                .map(player => (
                  <option key={player.ID} value={player.ID}>
                    {player.first_name} {player.last_name}
                  </option>
                ))}
            </select>
            <button onClick={handleAddPlayer}>Add to Season</button>
          </div>
        </div>
      )}
    </div>
  );
}

export default SeasonAddPlayers;
