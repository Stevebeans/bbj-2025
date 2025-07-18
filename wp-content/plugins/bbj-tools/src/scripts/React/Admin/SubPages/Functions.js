// Functions.js
import axios from "axios";

export const addSeason = ({ fullName, startDate, endDate, abbrv, seasonNum, featuredImage, setStatusMessage, setDisplayImage }) => {
  // This sets an initial status so the user knows something is happening
  setStatusMessage("Adding Season...");

  const formData = new FormData();
  formData.append("full_name", fullName);
  formData.append("start_date", startDate);
  formData.append("end_date", endDate);
  formData.append("abbrv", abbrv);
  formData.append("season_num", seasonNum);

  if (featuredImage) {
    formData.append("featured_image", featuredImage);
  }

  axios
    .post("/wp-json/bbj/v1/create-season", formData)
    .then(res => {
      setStatusMessage(res.data.message);
      if (res.data.featured_image) {
        setDisplayImage(res.data.featured_image);
      }
    })
    .catch(err => {
      setStatusMessage(`Error Saving - ${err.message}`);
    });
};
