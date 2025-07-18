import React from "react";
import AdminHeader from "./AdminHeader.js";

function MainSettings({ setActivePage }) {
  return (
    <React.Fragment>
      <AdminHeader text={"Welcome to the Big Brother Junkies Control Panel"} />
      <div className="admin-content">
        <div className="flex">
          <div className="admin-card">
            <h2 className="border-b border-gray-300 mb-4">Seasons</h2>
            <div onClick={() => setActivePage("add-season")} className="hover:cursor-pointer text-sky-500 underline">
              Add Season
            </div>
          </div>

          <div className="admin-card">
            <h2 className="border-b border-gray-300 mb-4">Players</h2>
            <div onClick={() => setActivePage("add-player")} className="hover:cursor-pointer text-sky-500 underline">
              Add Player
            </div>
          </div>
        </div>
      </div>
    </React.Fragment>
  );
}

export default MainSettings;
