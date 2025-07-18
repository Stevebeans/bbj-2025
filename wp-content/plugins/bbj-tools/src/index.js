import "./scripts/Blocks/new-feed-updates.js";
import "./index.css";
import React from "react";
import ReactDOM from "react-dom";
import BBJAdmin from "./scripts/React/Admin/Admin.js";
import { AdminProvider } from "./scripts/React/Admin/AdminContext.js";

console.log("hello from the index.js");

const autoFill = document.getElementById("active-fill");
const bbjSettings = document.getElementById("bbj-settings");

import AutoFill from "./scripts/AutoFill";

if (autoFill) {
  new AutoFill();
}

bbjSettings &&
  ReactDOM.render(
    <AdminProvider>
      <BBJAdmin />
    </AdminProvider>,
    bbjSettings
  );
