class FeedEdit {
  constructor() {
    this.ident = document.querySelector("#index-feed-updater");

    if (this.ident) {
      this.bindEvents();
    }
  }

  bindEvents() {
    const titleLinks = document.querySelectorAll(".edit-title");
    titleLinks.forEach(link => {
      link.addEventListener("click", e => {
        e.preventDefault();
        this.editContent(e.target.dataset.id, "title");
      });
    });

    const contentLinks = document.querySelectorAll(".edit-content");
    console.log("Total contentLinks found:", contentLinks.length);
    contentLinks.forEach(link => {
      console.log("Binding event listener to:", link.outerHTML);

      link.addEventListener("click", e => {
        e.preventDefault();
        this.editContent(e.target.dataset.id, "content");
        console.log("stuff");
        console.log(e.target);
      });
    });

    document.addEventListener("click", e => {
      if (e.target.matches('[id^="save-changes-"]')) {
        const postId = e.target.getAttribute("id").replace("save-changes-", "");
        this.saveChanges(postId);
      }
    });
  }

  editContent(id, type) {
    console.log(`Triggered editContent with ID: ${id} and Type: ${type}`);

    const mainElement = document.getElementById(type + "-" + id);
    const inputElement = document.getElementById(type + "-input-" + id);
    const saveButton = document.getElementById("save-changes-" + id);

    console.log(`Main Element (${type + "-" + id}):`, mainElement);
    console.log(`Input Element (${type + "-input-" + id}):`, inputElement);
    console.log("Save Changes Button:", saveButton);

    mainElement.style.display = "none";
    inputElement.style.display = "block";
    saveButton.style.display = "block";

    console.log(`Styles after change - Main Element: ${mainElement.style.display}, Input Element: ${inputElement.style.display}`);
  }

  saveChanges(id) {
    const titleValue = document.getElementById("title-input-" + id).value;
    const contentValue = document.getElementById("content-input-" + id).value;

    console.log(`Saving changes for ID ${id}. Title: ${titleValue}, Content: ${contentValue}`);

    // Construct formData for the AJAX request
    const formData = new FormData();
    formData.append("title", titleValue);
    formData.append("content", contentValue);

    fetch(playerData.root_url + "/wp-json/wp/v2/live-feed-updates/" + id, {
      method: "POST",
      body: formData,
      headers: {
        "X-WP-Nonce": playerData.nonce
      }
    })
      .then(response => response.json())
      .then(data => {
        // Update the UI
        document.getElementById("title-" + id).innerText = data.title.rendered;
        document.getElementById("content-" + id).innerHTML = data.content.rendered;

        // Hide editable fields
        document.getElementById("title-input-" + id).style.display = "none";
        document.getElementById("content-input-" + id).style.display = "none";
        document.getElementById("save-changes-" + id).style.display = "none";
      })
      .catch(error => {
        console.error("There was an error updating the post:", error);
      });
  }
}

export default FeedEdit;
