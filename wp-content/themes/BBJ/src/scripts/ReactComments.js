import React, { useState, useEffect, Fragment } from "react";
import axios from "axios";
import SingleComment from "./ReactComponents/SingleComment";

const CommentSystem = () => {
  const [comments, setComments] = useState([]);
  const [commentsPerLoad] = useState(10);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [loading, setLoading] = useState(true);
  const [postId, setPostId] = useState(null);
  const rootUrl = playerData.root_url;

  useEffect(() => {
    const pageIdDiv = document.getElementById("page-id");
    if (pageIdDiv) {
      setPostId(pageIdDiv.getAttribute("data-id"));
    }
  }, []);

  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      const res = await axios.get(`${rootUrl}/wp-json/wp/v2/comments?post=${postId}&per_page=${commentsPerLoad}&page=${page}`);
      setTotalPages(res.headers["x-wp-totalpages"]);
      setComments(res.data);
      setLoading(false);
    };
    if (postId) {
      fetchData();
    }
  }, [commentsPerLoad, page, postId]);

  const loadMoreComments = async () => {
    if (page < totalPages) {
      setLoading(true);
      const res = await axios.get(`${rootUrl}/wp-json/wp/v2/comments?post=${postId}&per_page=${commentsPerLoad}&page=${page + 1}`);
      setPage(page + 1);
      setComments([...comments, ...res.data]);
      setLoading(false);
    }
  };

  console.log("page");
  console.log(page);
  console.log(window.location.pathname.split("/").pop());
  console.log(comments);
  return (
    <Fragment>
      {comments
        .filter(comment => comment.status === "approved")
        .map(comment => (
          <SingleComment key={comment.id} comment={comment} />
        ))}

      {loading && <div>Loading...</div>}
      {page < totalPages && <button onClick={loadMoreComments}>Load More</button>}
    </Fragment>
  );
};

export default CommentSystem;
