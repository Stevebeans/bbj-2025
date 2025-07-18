import React from "react";

const PostList = ({ posts, lastViewedPostID }) => {
  console.log(posts);
  return (
    <div>
      {posts.map((post, index) => (
        <div key={post.id}>
          <h2>
            {index + 1} {post.ID} - {post.post_title}
          </h2>
          <div dangerouslySetInnerHTML={{ __html: post.post_content }} />
          {lastViewedPostID === post.id && <div>You last viewed this post at {new Date().toLocaleString()}</div>}
        </div>
      ))}
    </div>
  );
};

export default PostList;
