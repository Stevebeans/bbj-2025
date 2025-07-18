import axios from "axios";

class ReplyBox {
  constructor() {
    this.initialize();
  }

  initialize() {
    // Wait for DOM to load before attaching event handlers
    document.addEventListener("DOMContentLoaded", () => {
      this.attachEventHandlers();
      this.attachSubmitHandlers();
    });
  }

  attachEventHandlers() {
    // Now selecting the div containing the "fa-reply" icon.
    const replyButtons = document.querySelectorAll(".reply-button");

    replyButtons.forEach(replyButton => {
      replyButton.addEventListener("click", event => {
        this.toggleReplyBox(event);
      });
    });
  }

  attachSubmitHandlers() {
    // For submit buttons
    const submitButtons = document.querySelectorAll(".submit-comment");

    submitButtons.forEach(button => {
      button.addEventListener("click", event => {
        const postId = event.target.getAttribute("data-post-id");
        const textarea = document.getElementById(`comment-text-${postId}`);
        const commentText = textarea.value;
        const nonce = event.target.getAttribute("data-nonce");

        this.submitComment(postId, commentText, nonce);
      });
    });
  }

  submitComment(postId, commentText, nonce) {
    const data = {
      post_id: postId,
      comment_text: commentText
    };

    const responseTextDiv = document.querySelector(`#reply-box-inner-${postId} .response-text`);

    responseTextDiv.innerHTML = "";

    // submit to bbj/v1/add_comment via axios

    axios
      .post("/wp-json/bbj/v1/add_comment", data, {
        headers: {
          "X-WP-Nonce": nonce,
          "Content-Type": "application/json"
        }
      })
      .then(response => {
        console.log(response);
        // refresh page
        location.reload();
      })
      .catch(error => {
        console.log(error);
        let errorMessage = "An error occurred while submitting the comment.";
        if (error.response && error.response.data && error.response.data.message) {
          errorMessage = error.response.data.message;
        }
        responseTextDiv.innerHTML = `<span class="text-red-500">${errorMessage}</span>`;
      });
  }

  toggleReplyBox(event) {
    const parentContainer = event.target.closest(".border.relative");
    const postID = parentContainer.dataset.replyBox;

    let replyContainer = document.querySelector(`#reply-box-inner-${postID}`);
    let replyText = parentContainer.querySelector(".reply-text");
    let replyIcon = replyText.querySelector("i");
    let replyTextinner = replyText.querySelector("span");

    if (replyContainer.style.display === "none" || replyContainer.style.display === "") {
      replyContainer.style.display = "block";

      replyContainer.classList.add("slide-down");
      replyIcon.classList.remove("fa-reply");
      replyIcon.classList.add("fa-xmark");
      replyTextinner.textContent = " Cancel";
    } else {
      replyContainer.classList.remove("slide-down");
      replyIcon.classList.remove("fa-xmark");
      replyIcon.classList.add("fa-reply");
      replyTextinner.textContent = " Reply";
      replyContainer.style.display = "none";
    }

    console.log(parentContainer);
    console.log(postID);
    console.log(replyContainer);
  }
}

export default ReplyBox;
