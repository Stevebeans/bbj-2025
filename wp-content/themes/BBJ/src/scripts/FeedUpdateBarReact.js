import React, { useState, useEffect } from "react";
import axios from "axios";

function FeedUpdateBarReact() {
  const [isOpen, setIsOpen] = useState(false);
  const [title, setTitle] = useState("");
  const [content, setContent] = useState("");
  const [image, setImage] = useState("");
  const [updateMessage, setUpdateMessage] = useState("");

  useEffect(() => {
    const storedState = localStorage.getItem("feed_update_bar");

    console.log("stored stateddd");
    console.log(storedState);
    if (storedState === "true") {
      setIsOpen(true);
    }
  }, []);

  const toggleContent = () => {
    const newState = !isOpen;
    setIsOpen(newState);
    localStorage.setItem("feed_update_bar", newState);
  };

  const submitUpdate = () => {
    setUpdateMessage("Submitting...");

    const url = `${playerData.root_url}/wp-json/bbj/v1/feed-update`;

    // axios try catch

    const formData = new FormData();
    formData.append("title", title);
    formData.append("content", content);
    formData.append("image", image);

    axios
      .post(url, formData, {
        headers: {
          "Content-Type": "multipart/form-data",
          "X-WP-Nonce": playerData.nonce
        },
        withCredentials: true
      })
      .then(function (response) {
        console.log(response);
        setUpdateMessage("Success!");

        // clear form

        setTitle("");
        setContent("");
        setImage("");

        window.location.reload();
      })
      .catch(function (error) {
        console.log(error);
        setUpdateMessage(error);
      });
  };

  return (
    <div className="fixed bottom-0 z-50 flex w-full justify-center">
      <div className="mx-auto flex w-full max-w-7xl flex-col">
        <div className="flex w-full cursor-pointer justify-between rounded-t-md bg-primary500 px-2 py-1 font-bold text-white" onClick={toggleContent}>
          <div>Feed Update Bar</div>
          <div className="flex items-center hover:cursor-pointer">
            {isOpen ? "Close" : "Open"}
            <i className={`fa-solid fa-toggle-${isOpen ? "on" : "off"} ml-2`}></i>
          </div>
        </div>
        <div className={`transition-all duration-300 ease-in-out ${isOpen ? "h-fit" : "h-0"} overflow-hidden bg-white p-2`}>
          <div className="flex flex-col md:grid md:grid-cols-4 md:flex-row md:gap-2">
            <div className="md:col-span-4">
              Title
              <input type="text" className="w-full rounded-md" onChange={e => setTitle(e.target.value)} />
            </div>
            <div className="md:col-span-3">
              Content
              <textarea className="w-full rounded-md" onChange={e => setContent(e.target.value)} />
            </div>
            <div>
              Upload Image
              <input type="file" onChange={e => setImage(e.target.files[0])} />
            </div>
            <div className="text-center hover:cursor-pointer md:col-span-4">
              <button className="rounded-lg border border-primary500 bg-sky-50 px-2 py-1" onClick={submitUpdate}>
                Submit
              </button>
              {updateMessage && <span className="ml-2">{updateMessage}</span>}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export default FeedUpdateBarReact;
