// AdminContext.js

import React, { createContext, useState, useEffect } from "react";
import axios from "axios";

export const AdminContext = createContext();

export const AdminProvider = ({ children }) => {
  const [seasonList, setSeasonList] = useState([]);
  const [playerList, setPlayerList] = useState([]);
  const [loading, setLoading] = useState(true);

  // -- ADD THESE STATES FOR SEASON PLAYERS --
  const [dynamicFilteredPlayerList, setDynamicFilteredPlayerList] = useState([]);
  const [dynamicNonSeasonPlayerList, setDynamicNonSeasonPlayerList] = useState([]);
  const [fetchDataFlag, setFetchDataFlag] = useState(true);

  // If you want to track evictions / winners across the entire app
  const [evictedPlayers, setEvictedPlayers] = useState([]);
  const [voteToWinList, setVoteToWinList] = useState([]);

  // Player changes - so we can keep track across tabs:
  const [checkChanged, setCheckChanged] = useState(false);
  const [playerChanges, setPlayerChanges] = useState({});

  // Data fetching on mount
  useEffect(() => {
    setLoading(true);

    // You can fetch seasons/players or anything else you need
    Promise.all([axios.get("/wp-json/bbj/v1/get-seasons"), axios.get("/wp-json/bbj/v1/get-players")])
      .then(([seasons, players]) => {
        setSeasonList(seasons.data);
        setPlayerList(players.data);
      })
      .catch(err => {
        console.error("Error fetching data:", err);
      })
      .finally(() => {
        setLoading(false);
      });
  }, []);

  // -------------
  // HELPER FUNCTIONS
  // -------------
  // 1) Fetch additional data for a given season’s players:
  const fetchSeasonPlayers = async seasonId => {
    if (!fetchDataFlag) return;

    try {
      const response = await axios.get(`/wp-json/bbj/v1/get-season-players/${seasonId}`);
      const data = response.data;
      if (!data || !Array.isArray(data)) return;

      // Filter the global playerList by season
      const filtered = playerList.filter(player => {
        const seasonIds = Array.isArray(player.season_ids) ? player.season_ids : [];
        return seasonIds.includes(seasonId.toString());
      });

      // Non-season players
      const nonFiltered = playerList.filter(player => {
        const seasonIds = Array.isArray(player.season_ids) ? player.season_ids : [];
        return !seasonIds.includes(seasonId.toString());
      });

      // Merge data from GET request into filtered
      const updatedPlayers = filtered.map(player => {
        const fetchedPlayer = data.find(p => p.player_id === player.ID);
        if (fetchedPlayer) {
          return { ...player, ...fetchedPlayer };
        }
        return player;
      });

      setDynamicFilteredPlayerList(updatedPlayers);
      setDynamicNonSeasonPlayerList(nonFiltered);

      // Evicted players
      const evicted = updatedPlayers.filter(p => p.current_evicted == 1).map(p => p.ID);
      setEvictedPlayers(evicted);

      // voteToWinList is basically “filtered minus evicted”
      const notEvicted = updatedPlayers.filter(p => !evicted.includes(p.ID));
      setVoteToWinList(notEvicted);
    } catch (error) {
      console.error("Error fetching season players:", error);
    } finally {
      setFetchDataFlag(false);
    }
  };

  // 2) Add player to a season
  const addPlayerToSeason = async (seasonId, selectedPlayer) => {
    if (!selectedPlayer) return;
    try {
      const data = {
        season_id: seasonId,
        player_id: selectedPlayer
      };
      const res = await axios.post("/wp-json/bbj/v1/add-player-to-season", data);
      console.log(res);

      // On success, we must update our state
      const addedPlayer = dynamicNonSeasonPlayerList.find(p => p.ID === selectedPlayer);

      // Move that player from the dynamicNonSeasonPlayerList -> dynamicFilteredPlayerList
      const updatedNonSeasonList = dynamicNonSeasonPlayerList.filter(p => p.ID !== selectedPlayer);
      const updatedFilteredList = [...dynamicFilteredPlayerList, addedPlayer];

      setDynamicNonSeasonPlayerList(updatedNonSeasonList);
      setDynamicFilteredPlayerList(updatedFilteredList);
    } catch (err) {
      console.error(err);
    }
  };

  // 3) Remove player from a season
  const removePlayerFromSeason = async (seasonId, playerId) => {
    try {
      const data = {
        season_id: seasonId,
        player_id: playerId
      };
      const res = await axios.post("/wp-json/bbj/v1/remove-player-from-season", data);
      console.log(res);

      // On success, move that player from filtered -> nonSeason
      const removedPlayer = dynamicFilteredPlayerList.find(p => p.ID === playerId);
      if (removedPlayer) {
        const updatedFilteredList = dynamicFilteredPlayerList.filter(p => p.ID !== playerId);
        const updatedNonSeasonList = [...dynamicNonSeasonPlayerList, removedPlayer];

        setDynamicFilteredPlayerList(updatedFilteredList);
        setDynamicNonSeasonPlayerList(updatedNonSeasonList);
      }
    } catch (err) {
      console.error(err);
    }
  };

  // 4) Save changes for players
  const savePlayerChanges = async () => {
    try {
      const res = await axios.post("/wp-json/bbj/v1/update-season-players", playerChanges);
      console.log(res);
      setCheckChanged(false);
    } catch (err) {
      console.error(err);
    }
  };

  // 5) Handle input changes
  //    This is the same logic as before, but placed in context.
  //    You can expose it so the UI can call it.
  const handlePlayerInputChange = (seasonId, player_id, name, value) => {
    // If the user checks "evicted", we add them to the evicted list, etc.
    if (name === "current_evicted") {
      if (value === 1) {
        setEvictedPlayers(prev => [...prev, player_id]);
      } else {
        setEvictedPlayers(prev => prev.filter(id => id !== player_id));
      }
    }

    // Update dynamicFilteredPlayerList
    setDynamicFilteredPlayerList(prevList =>
      prevList.map(player => {
        if (player.ID === player_id) {
          return {
            ...player,
            [name]: value
          };
        }
        return player;
      })
    );

    // Prepare the changes object
    setPlayerChanges(prevChanges => {
      const currentChanges = prevChanges[player_id] || {};
      return {
        ...prevChanges,
        [player_id]: {
          ...currentChanges,
          playerID: player_id,
          seasonID: seasonId,
          [name]: value
        }
      };
    });

    setCheckChanged(true);
  };

  // -------------
  // PROVIDER
  // -------------
  return (
    <AdminContext.Provider
      value={{
        seasonList,
        playerList,
        loading,

        // ADD THE NEW STATES & FUNCTIONS BELOW
        dynamicFilteredPlayerList,
        setDynamicFilteredPlayerList,
        dynamicNonSeasonPlayerList,
        setDynamicNonSeasonPlayerList,
        fetchDataFlag,
        setFetchDataFlag,
        evictedPlayers,
        setEvictedPlayers,
        voteToWinList,
        setVoteToWinList,

        playerChanges,
        setPlayerChanges,
        checkChanged,
        setCheckChanged,

        fetchSeasonPlayers,
        addPlayerToSeason,
        removePlayerFromSeason,
        savePlayerChanges,
        handlePlayerInputChange
      }}
    >
      {children}
    </AdminContext.Provider>
  );
};
