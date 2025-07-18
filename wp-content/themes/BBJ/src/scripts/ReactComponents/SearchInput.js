import React, { useState, useEffect } from "react";
import axios from "axios";

function BBJSearchBar() {
  const [inputValue, setInputValue] = useState("");
  const [searchResults, setSearchResults] = useState([]);
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    if (inputValue === "") {
      return;
    }

    setIsLoading(true);

    setTimeout(() => {
      axios.get(`/wp-json/bbj/v1/search?q=${inputValue}`).then(response => {
        setSearchResults(response.data);
        setIsLoading(false);
      });
    }, 300);
  }, [inputValue]);

  const handleInputChange = event => {
    setInputValue(event.target.value);
  };

  return (
    <div>
      <div className="relative">
        <input type="text" className="w-full rounded-3xl border border-primary500 bg-white py-1 px-2.5 focus:border-second500" id="bbj-search" placeholder="Search Here..." value={inputValue} onChange={handleInputChange} />
        {isLoading && <div className="spinner absolute top-0 right-0 mr-3 mt-1"></div>}
      </div>
      {Array.isArray(searchResults) && searchResults.map(result => <div key={result.id}>{result.title}</div>)}
    </div>
  );
}

export default BBJSearchBar;
