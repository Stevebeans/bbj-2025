import React from "react";
import AdminHeader from "./AdminHeader.js";
import { useNavigate } from "react-router-dom";

function PlayerList({ loadingSeasons, playerList }) {
  const navigate = useNavigate();
  console.log("player list");
  console.log(playerList);

  // sort players by first name

  const sortedPlayers = [...playerList].sort((a, b) => a.first_name.localeCompare(b.first_name));

  return (
    <React.Fragment>
      <AdminHeader text={"Player List"} />

      <div className="admin-content">
        <h2>Player Lists:</h2>

        {
          // Using the passed down loading state to display a loading message
          loadingSeasons && <div>Loading...</div>
        }

        <div className="w-60 mt-2">
          {sortedPlayers.map((player, index) => (
            <div key={index} className="w-full flex justify-between mb-2 border-b border-gray-300">
              <div className="">
                {player.first_name} {player.last_name}
              </div>
              {/* Using setActivePage function to switch to AddSeason and passing season ID */}
              <button
                onClick={() => {
                  navigate(`/add-player/${player.ID}`);
                }}
              >
                <div className="div-link">Edit</div>
              </button>
            </div>
          ))}
        </div>
      </div>
    </React.Fragment>
  );
}

export default PlayerList;
