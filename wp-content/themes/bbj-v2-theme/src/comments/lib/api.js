import { bbjAuthFetch } from './bbjAuthFetch.js';

export const list = (postId, { page = 1, perPage = 20, sort = 'newest' } = {}) =>
  bbjAuthFetch(`/comments/${postId}?page=${page}&per_page=${perPage}&sort=${sort}`);

export const create = ({ postId, content, parentId = 0, mediaId = null }) =>
  bbjAuthFetch('/comments', {
    method: 'POST',
    body: { post_id: postId, content, parent_id: parentId, media_id: mediaId },
  });

export const update = (commentId, { content }) =>
  bbjAuthFetch(`/comments/${commentId}`, { method: 'PUT', body: { content } });

export const remove = (commentId) =>
  bbjAuthFetch(`/comments/${commentId}`, { method: 'DELETE' });

export const vote = (commentId, voteType) =>
  bbjAuthFetch(`/comments/${commentId}/vote`, { method: 'POST', body: { vote_type: voteType } });

export const myVote = (commentId) =>
  bbjAuthFetch(`/comments/${commentId}/my-vote`);

export const report = (commentId, { reason, details = '' }) =>
  bbjAuthFetch(`/comments/${commentId}/report`, { method: 'POST', body: { reason, details } });

export const reactAdd = (commentId, reactionType) =>
  bbjAuthFetch(`/comments/${commentId}/reactions`, { method: 'POST', body: { reaction_type: reactionType } });

export const reactRemove = (commentId, reactionType) =>
  bbjAuthFetch(`/comments/${commentId}/reactions`, { method: 'DELETE', body: { reaction_type: reactionType } });

export const pin = (commentId) =>
  bbjAuthFetch(`/comments/${commentId}/pin`, { method: 'POST' });

export const unpin = (commentId) =>
  bbjAuthFetch(`/comments/${commentId}/pin`, { method: 'DELETE' });

export const uploadMedia = (file) => {
  const fd = new FormData();
  fd.append('file', file);
  return bbjAuthFetch('/comments/media', { method: 'POST', body: fd });
};

export const giphySearch = (q, limit = 20, offset = 0) =>
  bbjAuthFetch(`/comments/media/giphy/search?q=${encodeURIComponent(q)}&limit=${limit}&offset=${offset}`);

export const userSearch = (q, limit = 10) =>
  bbjAuthFetch(`/users/search?q=${encodeURIComponent(q)}&limit=${limit}`);

export const userRank = (userId) =>
  bbjAuthFetch(`/users/${userId}/rank`);

export const userProfile = (userId) =>
  bbjAuthFetch(`/users/${userId}/profile`);
