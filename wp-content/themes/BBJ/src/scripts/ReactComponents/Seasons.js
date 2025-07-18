import React, { Component } from "react";

export class Seasons extends Component {
  render() {
    let { season } = this.props;

    let arrayCheck = Array.isArray(season);

    //console.log(season);

    if (season) {
      console.log(season);
    }

    //console.log(season);

    return <React.Fragment>{season}</React.Fragment>;
  }
}

export default Seasons;
