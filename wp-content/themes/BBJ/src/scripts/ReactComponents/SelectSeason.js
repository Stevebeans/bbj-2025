import React, { useState } from "react";

const SelectSeason = ({ seasons, handleSeason }) => {
  const [selectedSeason, setSelectedSeason] = useState("all seasons");
  return (
    <select
      onChange={e => {
        handleSeason(e);
        setSelectedSeason(e.target.value);
      }}
      value={selectedSeason}
      class="block w-full rounded-lg border border-gray-300 bg-white p-2.5 text-sm text-gray-900 focus:border-slate-500 focus:ring-slate-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-400 dark:placeholder-gray-400 dark:focus:border-slate-500 dark:focus:ring-slate-500"
    >
      <option value="all seasons">All Seasons</option>
      {seasons.map(season => (
        <option key={season} value={season}>
          {season}
        </option>
      ))}
    </select>
  );
};

export default SelectSeason;
