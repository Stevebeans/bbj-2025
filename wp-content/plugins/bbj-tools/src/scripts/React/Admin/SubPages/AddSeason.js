import React, { useState, useEffect, useContext } from "react";
import axios from "axios";
import SeasonAddPlayers from "./SeasonAddPlayers";
import AdminHeader from "./AdminHeader";
import SeasonWeeks from "./SeasonWeeks";
import { useParams, useNavigate } from "react-router-dom";
import { AdminContext } from "../AdminContext";
import { addSeason } from "./Functions";

function AddSeason() {
  const { seasonList, playerList } = useContext(AdminContext);
  const navigate = useNavigate();
  const { seasonId } = useParams();
  const isEditMode = Boolean(seasonId);

  const [fullName, setFullName] = useState("");
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");
  const [abbrv, setAbbrv] = useState("");
  const [featuredImage, setFeaturedImage] = useState(null);
  const [seasonNum, setSeasonNum] = useState(null);
  const [displayImage, setDisplayImage] = useState(null);
  const [statusMessage, setStatusMessage] = useState(null);

  useEffect(() => {
    if (isEditMode && seasonList?.length) {
      const seasonData = seasonList.find(season => season.ID === parseInt(seasonId, 10));
      if (seasonData) {
        setFullName(seasonData.full_name);
        setStartDate(seasonData.start_date);
        setEndDate(seasonData.end_date);
        setAbbrv(seasonData.abbreviation);
        setSeasonNum(seasonData.season_number);
        setDisplayImage(seasonData.season_picture);
      }
    } else {
      setFullName("");
      setStartDate("");
      setEndDate("");
      setAbbrv("");
      setSeasonNum("");
      setDisplayImage(null);
    }
  }, [seasonId, seasonList]);

  // 2) Call the addSeason function from Functions.js
  const handleSeasonSubmit = () => {
    addSeason({
      fullName,
      startDate,
      endDate,
      abbrv,
      seasonNum,
      featuredImage,
      setStatusMessage,
      setDisplayImage
    });
  };

  return (
    <React.Fragment>
      <AdminHeader text={isEditMode ? "Edit Season" : "Add Season"} />

      <div className="admin-content">
        <div onClick={() => navigate(`/season-list/`)} className="div-link">
          Return to Season List
        </div>
        <div>
          <div className="grid grid-cols-4 gap-2">
            <div className="flex items-center">Full Name</div>
            <input type="text" value={fullName} onChange={e => setFullName(e.target.value)} className="admin-input" />

            <div className="flex items-center">Abbreviation (BB24)</div>
            <input type="text" value={abbrv} onChange={e => setAbbrv(e.target.value)} className="admin-input" />

            <div className="flex items-center">Start Date</div>
            <input type="date" value={startDate} onChange={e => setStartDate(e.target.value)} className="admin-input" />

            <div className="flex items-center">End Date</div>
            <input type="date" value={endDate} onChange={e => setEndDate(e.target.value)} className="admin-input" />

            <div className="flex items-center">Season Number</div>
            <input type="number" value={seasonNum} onChange={e => setSeasonNum(e.target.value)} className="admin-input" />

            <div className="flex flex-col">
              <div>Image Upload:</div>
              <input type="file" onChange={e => setFeaturedImage(e.target.files[0])} />
            </div>

            <div className="flex flex-col">
              <div>Image Preview:</div>
              {displayImage && <img src={displayImage} alt="Season" className="w-48" />}
            </div>
          </div>
        </div>

        {isEditMode && (
          <React.Fragment>
            <SeasonAddPlayers seasonId={seasonId} startDate={startDate} endDate={endDate} />
            <SeasonWeeks seasonId={seasonId} startDate={startDate} endDate={endDate} />
          </React.Fragment>
        )}
      </div>
      {!isEditMode && (
        <div className="admin-btn mt-4" onClick={handleSeasonSubmit}>
          Submit Season
        </div>
      )}
      {statusMessage && <div className="mt-2">{statusMessage}</div>}
    </React.Fragment>
  );
}

export default AddSeason;
