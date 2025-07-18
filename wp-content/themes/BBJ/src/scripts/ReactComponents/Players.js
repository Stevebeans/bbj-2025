import React, { Component } from "react";
import Seasons from "./Seasons";
import PropTypes from "prop-types";
import axios from "axios";

export class Players extends Component {
  componentDidMount() {}

  render() {
    const { player } = this.props;

    return (
      <React.Fragment>
        <tr>
          <td>{player.first_name}</td>
          <td>{player.last_name}</td>
          <td>
            <Seasons season={player.seasons} />
          </td>
        </tr>
      </React.Fragment>
    );
  }
}

export default Players;
