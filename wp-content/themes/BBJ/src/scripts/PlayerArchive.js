import axios from "axios";
import React, { Component } from "react";
import Players from "./ReactComponents/Players";

export class PlayerArchive extends Component {
  state = {
    players: [],
    isLoaded: false
  };

  componentDidMount() {
    axios
      .get("/wp-json/bbj/v1/player_info/")
      .then(res =>
        this.setState({
          players: res.data,
          isLoaded: true
        })
      )
      .catch(err => console.log(err));
  }

  render() {
    const { players, isLoaded } = this.state;
    const { seasons } = this.state.players;

    console.log(players);

    // console.log(this.state);

    const playerList = players.map(player => player.first_name);

    //console.log(playerList);

    if (isLoaded) {
      return (
        <React.Fragment>
          <table>
            <tr>
              <th>First Name</th>
              <th>Last Name</th>
              <th>Seasons</th>
            </tr>
            {players.map(player => (
              <Players key={player.id} player={player} />
            ))}
          </table>
        </React.Fragment>
      );
    }

    return <h3>Loading...</h3>;
  }
}

export default PlayerArchive;
