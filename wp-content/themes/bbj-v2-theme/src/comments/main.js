import React from 'react';
import { createRoot } from 'react-dom/client';
import CommentSection from './components/CommentSection.jsx';
import { ToastHost } from './hooks/useToast.js';

export default function mount(root, data) {
  const postId = parseInt(root.dataset.postId, 10) || (data && data.postId) || 0;
  const skeleton = root.querySelector('.bbj-comments-skeleton');
  if (skeleton) skeleton.remove();

  const target = document.createElement('div');
  root.appendChild(target);
  createRoot(target).render(
    React.createElement(React.Fragment, null,
      React.createElement(CommentSection, { postId, config: (data && data.config) || {} }),
      React.createElement(ToastHost)
    )
  );
}
