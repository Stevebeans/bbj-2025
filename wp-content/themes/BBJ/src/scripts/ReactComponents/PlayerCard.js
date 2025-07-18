import React from "react";

const PlayerCard = ({ player }) => {
  return (
    <div className="bbj-player-card-container group">
      <div className="flex w-full">
        <div className="h-28 w-40">
          <img src={player.profile} className="h-full w-40" alt={`${player.first_name} ${player.last_name}`} />
        </div>
        <div className="flex w-full flex-col justify-between py-1 px-2">
          <div>
            <h3 className="font-ibm text-sm text-primary500 group-hover:text-secondHard">{player.first_name}</h3>
            <h2 className="font-ibm text-base font-semibold leading-4 text-primary500 group-hover:text-secondHard">{player.last_name}</h2>
            <h4 className="font-ibm text-xs">{player.season}</h4>
          </div>

          <div className="mt-2 grid grid-cols-2 align-bottom text-xs text-primary500" style={{ gridTemplateColumns: "0.5fr 1.5fr" }}>
            <div>Age</div>
            <div className="group-hover:text-secondHard">{player.then_age}</div>
            <div>Gender</div>
            <div className="capitalize group-hover:text-secondHard">{player.gender}</div>
            <div>Loc</div>
            <div className="group-hover:text-secondHard">{player.location}</div>
          </div>
        </div>
      </div>

      <div className="px-2 py-1">
        <div className="overflow-hidden  rounded-xl">
          <div className="bg-primary500 text-center text-white">Season Stats</div>
          <div className="grid grid-cols-4 justify-around gap-1 bg-slate-300 p-1">
            <div className="player-card-stat-header">HOH</div>
            <div className="player-card-stat-header">POV</div>
            <div className="player-card-stat-header">NOM</div>
            <div className="player-card-stat-header">SAVED</div>

            <div className="text-center text-xs group-hover:text-secondHard">{player.hoh}</div>
            <div className="text-center text-xs group-hover:text-secondHard">{player.pov}</div>
            <div className="text-center text-xs group-hover:text-secondHard">{player.nom}</div>
            <div className="text-center text-xs group-hover:text-secondHard">{player.saved}</div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PlayerCard;
