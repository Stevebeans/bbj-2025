import React from 'react';
import { createRoot } from 'react-dom/client';
import CommentSection from './components/CommentSection.jsx';
import { ToastHost } from './hooks/useToast.js';

export default function mount(root, data) {
  const postId = parseInt(root.dataset.postId, 10) || (data && data.postId) || 0;

  // Wipe the entire SSR placeholder (heading + skeleton + noscript fallback)
  // so the React heading is the single source of truth for the count.
  root.innerHTML = '';

  const target = document.createElement('div');
  root.appendChild(target);
  createRoot(target).render(
    React.createElement(React.Fragment, null,
      React.createElement(CommentSection, { postId, config: (data && data.config) || {} }),
      React.createElement(ToastHost)
    )
  );
}
