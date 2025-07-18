import React, { useState, useEffect } from "react";
import AddWeek from "./AddWeek";

function SeasonWeeks({ startDate, endDate }) {
  const storedShowWeeks = localStorage.getItem("showWeeks");
  const initialShowWeeks = storedShowWeeks ? JSON.parse(storedShowWeeks) : true;
  const [showWeeks, setShowWeeks] = useState(initialShowWeeks);

  /* 
  Notes - 

  Need to finish the add week button 

  */
  useEffect(() => {
    localStorage.setItem("showWeeks", JSON.stringify(showWeeks));
  }, [showWeeks]);

  return (
    <React.Fragment>
      <div className="flex justify-between items-center border-b border-gray-500 bg-gray-50  hover:cursor-pointer" onClick={() => setShowWeeks(!showWeeks)}>
        <div className="pl-4">
          <h2>Week List:</h2>
        </div>
        <div className="pr-4 text-4xl font-bold text-blue-600">
          <button
            className="ml-2" // Adding margin for spacing
          >
            {showWeeks ? <i className="fas fa-angle-down text-3xl"></i> : <i className="fas fa-angle-up text-3xl"></i>}
          </button>
        </div>
      </div>
      {showWeeks && (
        <div className="border-b border-gray-400 mb-4 pb-4">
          <AddWeek startDate={startDate} endDate={endDate} />
        </div>
      )}
    </React.Fragment>
  );
}

export default SeasonWeeks;
