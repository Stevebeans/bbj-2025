// AdditionalFilters.js
import React from "react";
import SelectSeason from "./SelectSeason";
import GenderInput from "./GenderInput";

const AdditionalFilters = ({ handleAdditionalFilters, selectedFilters, seasons, handleSeason, players, handleGender }) => {
  const handleCheckboxChange = e => {
    const { name, checked } = e.target;
    console.log("Checkbox clicked:", name, checked); // Add this line to log the clicked checkbox
    handleAdditionalFilters(name, checked);
  };

  return (
    <div className="additional-filters mb-2">
      <details>
        <summary className="">Additional Filters</summary>

        <div className="flex flex-col  items-start justify-start rounded-lg border border-slate-300 bg-slate-100 py-1 px-2 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex flex-col text-sm">
            <div>
              <label htmlFor="winners" className="inline-flex self-center">
                <input type="checkbox" id="winners" name="winners" checked={selectedFilters.winners} onChange={handleCheckboxChange} className="mr-1 h-4 w-4 self-center rounded border-primary500 bg-white text-blue-600 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-blue-600" />
                Winners
              </label>
            </div>
            <div>
              <label htmlFor="americasFavorite" className="inline-flex items-center">
                <input type="checkbox" id="americasFavorite" name="americasFavorite" checked={selectedFilters.americasFavorite} onChange={handleCheckboxChange} className="mr-1 h-4 w-4 self-center rounded border-primary500 bg-white text-blue-600 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-blue-600" />
                America's Favorite
              </label>
            </div>
            <div>
              <label htmlFor="runnerUp" className="inline-flex items-center">
                <input type="checkbox" id="runnerUp" name="runnerUp" checked={selectedFilters.runnerUp} onChange={handleCheckboxChange} className="mr-1 h-4 w-4 self-center rounded border-primary500 bg-white text-blue-600 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-blue-600" />
                Runner Up
              </label>
            </div>
          </div>
          <div className="text-sm"></div>
          <div className="flex flex-col text-sm">
            <SelectSeason seasons={seasons} handleSeason={handleSeason} players={players} />
            <GenderInput handleGender={handleGender} />
          </div>
        </div>
      </details>
    </div>
  );
};

export default AdditionalFilters;
