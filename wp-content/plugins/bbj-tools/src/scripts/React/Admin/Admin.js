import React, { useContext } from "react";
import MainSettings from "./SubPages/Main.js";
import AddPlayer from "./SubPages/AddPlayer.js";
import AddSeason from "./SubPages/AddSeason.js";
import SeasonList from "./SubPages/SeasonList.js";
import PlayerList from "./SubPages/PlayerList.js";
import { HashRouter as Router, Route, Link, Routes } from "react-router-dom";
import { AdminContext } from "./AdminContext.js";

function BBJAdmin() {
  const { loading } = useContext(AdminContext);

  {
    /* Notes for the confusing database structure I put in...
  // wp_bbj_play_season_rel = this is what links players to seasons (I believe).  Yes, it is the relationship table. Also tracks their overall results that season
  // wp_bbj_weeks = This tracks the weeks of the season.  It has a season ID, week number, and start/end dates.  This is the 'weeks' page
  // wp_bbj_weeks_players = This tracks the players each week using the week_id.  This is what you see when you edit the week.

  // Season List - This should have 'edit season' which will allow you to modify the season information. It will also allow you to add players to the season which will tie into the bbj_play_season_rel table.  It should also have a button 'Show Weeks' which will take you to the weeks page. You can see a list of the weeks and click on them to edit them.

  // Edit Wekes - This should have a list of players for the season each week, but the 'fill in active' button will check if they're evicted. If not, it'll automatically check those still in the house.  This counts the weeks a player was active.

  // Edit Season should have a list of players on the season that you get when you click on edit season. It should have:

  // Player Name - Winner - HoH - Veto - Evicted - Jury - AFP - Runner-Up - Finish Place (this helps during double evictions) - evicted date
  // Note - this is good for the spoiler bar to call on for the seaosn information.  Rather than sort it indivuaally, just sort it based on the checkmarks.  Winner, America's Favorite, Runner Up, HoH, PoV, Nominated, Evicted, Jury.

  // Load some basic stuff
  */
  }

  return (
    <Router>
      {loading ? (
        <div className="w-full h-full flex justify-center items-center">
          <div className="text-2xl">Loading...</div>
        </div>
      ) : (
        <div className="w-full p-2 flex mb-8">
          <div className="flex-shrink-0 w-48 border-r border-sky-600 flex flex-col">
            <Link to="/main-settings" className="admin-link">
              Main Settings
            </Link>
            <Link to="/season-list" className="admin-link">
              Season List
            </Link>
            <Link to="/player-list" className="admin-link">
              Player List
            </Link>
            <Link to="/add-season" className="admin-link">
              Add Season
            </Link>
            <Link to="/add-player" className="admin-link">
              Add Player
            </Link>
          </div>

          <div className="flex-1 p-2">
            <Routes>
              <Route path="/main-settings" element={<MainSettings />} />
              <Route path="/add-player/:playerId" element={<AddPlayer />} />
              <Route path="/add-player" element={<AddPlayer />} />
              <Route path="/add-season/:seasonId" element={<AddSeason />} />
              <Route path="/add-season" element={<AddSeason />} />
              <Route path="/season-list" element={<SeasonList />} />
              <Route path="/player-list" element={<PlayerList />} />
              <Route path="/" element={<MainSettings />} />
            </Routes>
          </div>
        </div>
      )}
    </Router>
  );
}

export default BBJAdmin;
