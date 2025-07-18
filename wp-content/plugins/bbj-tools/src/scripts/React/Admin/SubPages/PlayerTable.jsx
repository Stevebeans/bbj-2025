import React, { useContext } from "react";
import { AdminContext } from "../AdminContext";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { faMinus } from "@fortawesome/free-solid-svg-icons";

const PlayerTable = ({ showPlayers, dynamicFilteredPlayerList, handleInputChange }) => {
  const { playerChanges, voteToWinList, startDate, endDate, handleDelete } = useContext(AdminContext);

  return (
    <tbody>
      {dynamicFilteredPlayerList.map(player => (
        <tr key={player.ID}>
          <td>
            <img src={player.featured_image_url} alt="" className=" h-8 w-8 border rounded-md object-cover" />
          </td>
          <td className="border-b border-gray-300 py-2">
            {player.first_name} {player.last_name}
          </td>
          <td className="border-b border-gray-300 text-center py-2">
            <input type="checkbox" checked={player.current_hoh == 1} onChange={handleInputChange(player.ID, "current_hoh")} />
          </td>
          {/* POV */}
          <td className="border-b border-gray-300 text-center py-2">
            <input type="checkbox" checked={player.current_pov == 1} onChange={handleInputChange(player.ID, "current_pov")} />
          </td>
          {/* NOM */}
          <td className="border-b border-gray-300 text-center py-2">
            <input type="checkbox" checked={player.current_nom == 1} onChange={handleInputChange(player.ID, "current_nom")} />
          </td>
          {/* Jury */}
          <td className="border-b border-gray-300 text-center py-2">
            <input type="checkbox" checked={player.jury == 1} onChange={handleInputChange(player.ID, "jury")} />
          </td>
          {/* Evicted */}
          <td className="border-b border-gray-300 text-center py-2">
            <input type="checkbox" checked={player.current_evicted == 1} onChange={handleInputChange(player.ID, "current_evicted")} />
          </td>
          {/* Misc */}
          <td className="border-b border-gray-300 text-center py-2">
            <input type="text" value={(playerChanges[player.ID] && playerChanges[player.ID].current_misc) || player.current_misc || ""} onChange={e => handleInputChange(player.ID, "current_misc")(e)} />
          </td>
          {/* Evic Date */}
          <td className="border-b border-gray-300 text-center py-2 border-r">
            <input type="date" min={startDate} max={endDate} value={player.evict_date || ""} onChange={e => handleInputChange(player.ID, "evict_date")(e)} />
          </td>
          {/* Winner */}
          <td className="border-b border-gray-300 text-center py-2 bg-gray-50">
            <input type="checkbox" checked={player.winner == 1} onChange={handleInputChange(player.ID, "winner")} />
          </td>
          {/* Runner-Up */}
          <td className="border-b border-gray-300 text-center py-2 bg-gray-50">
            <input type="checkbox" checked={player.runner_up == 1} onChange={handleInputChange(player.ID, "runner_up")} />
          </td>
          {/* AFP */}
          <td className="border-b border-gray-300 text-center py-2 bg-gray-50">
            <input type="checkbox" checked={player.afp == 1} onChange={handleInputChange(player.ID, "afp")} />
          </td>
          {/* Vote to Win */}
          <td className="border-b border-gray-300 text-center py-2 bg-gray-50 border-r">
            {player.jury == 1 && (
              <select value={player.vote_to_win || ""} onChange={handleInputChange(player.ID, "vote_to_win")}>
                <option value="">Select Player</option>
                {voteToWinList.map(votePlayer => (
                  <option key={votePlayer.ID} value={votePlayer.ID}>
                    {votePlayer.first_name} {votePlayer.last_name}
                  </option>
                ))}
              </select>
            )}
          </td>
          {/* Delete */}
          <td className="border-b border-gray-300 py-2 text-right">
            <button onClick={() => handleDelete(player.ID)}>
              <FontAwesomeIcon icon={faMinus} className="text-red-500" />
            </button>
          </td>
        </tr>
      ))}
    </tbody>
  );
};

export default PlayerTable;
