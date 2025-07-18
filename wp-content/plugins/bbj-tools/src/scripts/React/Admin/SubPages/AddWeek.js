import React, { useState } from "react";

function AddWeek({ startDate, endDate }) {
  const [weekNumber, setWeekNumber] = useState("");
  const [weekStartDate, setweekStartDate] = useState("");
  const [weekEndDate, setweekEndDate] = useState("");

  const AddWeek = () => {
    console.log("add week");
    console.log("week number: ", weekNumber);
    console.log("start date: ", startDate);
    console.log("end date: ", endDate);
  };

  return (
    <div className="p-4">
      <div className="text-lg font-bold">Add Week</div>
      <div className="w-full flex">
        <div>
          <input type="text" name="week_number" id="week_number" placeholder="#" className="w-10 mr-4" onChange={e => setWeekNumber(e.target.value)} />
        </div>
        <div>
          <input type="date" name="start_date" id="start_date" min={startDate} max={endDate} placeholder="Start" className="mr-4" onChange={e => setweekStartDate(e.target.value)} />
        </div>
        <div>
          <input type="date" name="end_date" id="end_date" min={startDate} max={endDate} placeholder="End" className="mr-4" onChange={e => setweekEndDate(e.target.value)} />
        </div>
        <div>
          <button class="admin-btn" onClick={() => AddWeek()}>
            Add Week
          </button>
        </div>
      </div>
    </div>
  );
}

export default AddWeek;
