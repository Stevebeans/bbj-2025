import React, { useContext } from "react";
import AdminHeader from "./AdminHeader.js";
import { useNavigate } from "react-router-dom";
import { AdminContext } from "../AdminContext.js";

function SeasonList() {
  const navigate = useNavigate();
  const { loading, seasonList } = useContext(AdminContext);

  const sortedSeasons = [...seasonList].sort((b, a) => a.season_number.localeCompare(b.season_number));

  console.log("Season List:", seasonList);

  return (
    <React.Fragment>
      <AdminHeader text={"Season List"} />

      <div className="admin-content">
        <h2>Season List:</h2>

        {loading && <div>Loading...</div>}

        <div className="w-60 mt-2">
          {sortedSeasons.map((season, index) => (
            <div key={index} className="w-full flex justify-between mb-2 border-b border-gray-300">
              <div className="">{season.full_name}</div>
              {/* Navigate to the proper route */}
              <button
                onClick={() => {
                  navigate(`/add-season/${season.ID}`);
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

export default SeasonList;
