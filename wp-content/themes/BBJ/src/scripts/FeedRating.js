import axios from "axios";

class FeedRating {
  constructor() {
    this.initDiv = document.querySelector("#new-feed-updates");
    this.feedRatingContainer = document.querySelectorAll(".feed-update-ratings");
    this.loginModal = document.querySelector("#loginModal");
    this.closeLoginModalButton = document.querySelector("#closeLoginModal");

    this.isUserLoggedIn = !!window.userLoggedIn;

    console.log("is user logged in", this.isUserLoggedIn);

    if (this.initDiv) {
      this.initialize();
    }
  }

  initialize() {
    console.log("initializing feed rating");
    console.log(document);
    document.addEventListener("DOMContentLoaded", () => {
      this.attachEventHandlers();
      this.closeLoginModalButton.addEventListener("click", () => {
        this.loginModal.classList.add("hidden");
      });
    });
  }
  showLoginModal() {
    this.loginModal.classList.remove("hidden");
  }

  attachEventHandlers() {
    this.feedRatingContainer.forEach(feedRating => {
      const feedRatingId = feedRating.getAttribute("data-feed-rating");
      const rateUp = feedRating.querySelector(".feed-update-id-up");
      const rateDown = feedRating.querySelector(".feed-update-id-down");

      rateUp.addEventListener("click", event => {
        this.submitRating(feedRatingId, "up");
      });

      rateDown.addEventListener("click", event => {
        this.submitRating(feedRatingId, "down");
      });
    });
  }

  submitRating(feedRatingId, rating) {
    if (!this.isUserLoggedIn) {
      this.showLoginModal();
      return;
    }

    const data = {
      feed_update_id: feedRatingId,
      rating: rating
    };

    axios
      .post("/wp-json/bbj/v1/add_feed_update_rating", data, {
        headers: {
          "X-WP-Nonce": playerData.nonce,
          "Content-Type": "application/json"
        }
      })
      .then(response => {
        console.log(response);
        const newRating = response.data.total_rating;

        const ratingElement = document.querySelector(`[data-count-for="${feedRatingId}"]`);

        // Update the rating displayed
        if (ratingElement) {
          ratingElement.innerText = newRating;

          // Update the rating class
          if (newRating > 0) {
            ratingElement.classList.add("positive");
            ratingElement.classList.remove("negative");
          } else if (newRating < 0) {
            ratingElement.classList.add("negative");
            ratingElement.classList.remove("positive");
          } else {
            ratingElement.classList.remove("positive");
            ratingElement.classList.remove("negative");
          }
        }
      })
      .catch(error => {
        console.log(error);
      });
  }
}

export default FeedRating;
