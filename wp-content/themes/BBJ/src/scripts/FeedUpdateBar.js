const g_update_body = document.querySelector(".update-box-body");
const g_update_toggle = document.querySelector("#toggle_feed_update");
const g_update_box = document.querySelector("#update-box");
let g_update_status = false;
let g_storage_status = localStorage.getItem("feed_update_bar");

export function feed_update_slider() {
  console.log("FEED UPDATE BAR");
  console.log(g_storage_status);
  console.log("FEED UPDATE BAR ABOIVE");

  // if (g_storage_status === true) {
  //   console.log("should be open");
  //   g_update_status = true;
  //   g_update_box.classList.add("update-toggle");
  // }
  if (g_storage_status == "true") {
    console.log("should be open");
    g_update_status = true;
    g_update_body.classList.add("update-toggle");
  }

  g_update_toggle.addEventListener("click", e => {
    console.log("click");
    toggle_feed_window();
  });
}

function toggle_feed_window() {
  if (g_update_status) {
    g_update_body.classList.remove("update-toggle");
    g_update_status = false;
    localStorage.setItem("feed_update_bar", false);
    console.log("close");
    console.log(g_storage_status);
  } else {
    g_update_body.classList.add("update-toggle");
    localStorage.setItem("feed_update_bar", true);
    g_update_status = true;
    console.log("open");
    console.log(g_storage_status);
  }
  console.log("update");
}
