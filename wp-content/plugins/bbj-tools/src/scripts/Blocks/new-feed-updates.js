// Import dependencies
import { registerBlockType } from "@wordpress/blocks";
import { useState, useEffect } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";

// Register the block
registerBlockType("my-plugin/new-feed-updates", {
  title: "New Feed Updates",
  icon: "video-alt",
  category: "bbj-blocks",
  keywords: ["feed", "updates", "custom"],
  edit: function () {
    const [posts, setPosts] = useState([]);

    useEffect(() => {
      apiFetch({ path: "/wp/v2/live-feed-updates?per_page=10" })
        .then(data => {
          setPosts(data);
        })
        .catch(() => {
          setPosts([]);
        });
    }, []);

    return (
      <div>
        {posts.length === 0 ? (
          <p>No posts</p>
        ) : (
          <ul>
            {posts.map(post => (
              <li className="p-2" key={post.id}>
                <a href={post.link} target="_blank" rel="noreferrer">
                  {post.title.rendered}
                </a>
              </li>
            ))}
          </ul>
        )}
      </div>
    );
  },
  save: function () {
    // This is a dynamic block, so we return null here and define a server-side render callback instead.
    return null;
  }
});
