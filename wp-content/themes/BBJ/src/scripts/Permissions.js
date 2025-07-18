export function permission_check() {
  const adBlocks = document.querySelectorAll(".bbjad");
  const roleCheck = document.querySelector("#user-role");
  const updater = document.querySelector("#update-box");

  if (roleCheck.getAttribute("data-role") === "premium") {
    console.log("premium user");
  } else {
    console.log("regular user");
  }

 
  console.log(roleCheck.getAttribute("data-role"));

  // adBlocks.forEach(el => {
  //   el.style.display = "none";
  // });
}
