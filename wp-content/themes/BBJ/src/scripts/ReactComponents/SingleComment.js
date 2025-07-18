import React, { useEffect, Fragment } from "react";

function SingleComment({ comment }) {
  return (
    <Fragment>
      <div className="m-2 border border-primary500">
        <p>{comment.author_name}</p>
        <p>{comment.content.rendered}</p>
        <p>{comment.date}</p>
      </div>
    </Fragment>
  );
}

export default SingleComment;
