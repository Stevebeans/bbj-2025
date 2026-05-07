import React, { useState, useEffect, useCallback, Suspense } from 'react';
import * as commentApi from '../lib/api.js';
import CommentCard from './CommentCard.jsx';

// CommentForm is in the composer chunk — lazy load on first paint of the section
// (we want it visible at the top, but not blocking main chunk size).
const CommentForm = React.lazy(() =>
  import(/* webpackChunkName: "composer" */ '../composer.js').then((m) => ({ default: m.CommentForm }))
);

function CommentSectionInner({ postId, config = {} }) {
  const perPage = config.perPage || 20;
  const sortDefault = config.sortDefault || 'newest';

  const [comments, setComments] = useState([]);
  const [pagination, setPagination] = useState({ total: 0, current_page: 1, total_pages: 1 });
  const [sort, setSort] = useState(sortDefault);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [highlightedCommentId, setHighlightedCommentId] = useState(null);

  const fetchComments = useCallback(async (page = 1) => {
    setLoading(true);
    setError(null);
    try {
      const data = await commentApi.list(postId, { page, perPage, sort });
      setComments(data.comments || []);
      setPagination(data.pagination || { total: 0, current_page: 1, total_pages: 1 });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, [postId, sort, perPage]);

  useEffect(() => {
    fetchComments();
  }, [fetchComments]);

  // Permalink scroll handling
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const commentId = params.get('comment');
    if (commentId && !loading && comments.length > 0) {
      setHighlightedCommentId(parseInt(commentId, 10));
      const timer = setTimeout(() => {
        const element = document.getElementById(`comment-${commentId}`);
        if (element) {
          element.scrollIntoView({ behavior: 'smooth', block: 'center' });
          setTimeout(() => setHighlightedCommentId(null), 3000);
        }
      }, 100);
      return () => clearTimeout(timer);
    }
  }, [loading, comments.length]);

  const handleSortChange = (newSort) => setSort(newSort);

  const handleNewComment = (comment) => {
    if (!comment.parent_id || comment.parent_id === 0) {
      if (sort === 'newest') {
        setComments([comment, ...comments]);
      } else {
        setComments([...comments, comment]);
      }
    }
    setPagination({ ...pagination, total: pagination.total + 1 });
  };

  const handleCommentDeleted = (commentId) => {
    setComments(comments.filter((c) => c.id !== commentId));
    setPagination({ ...pagination, total: pagination.total - 1 });
  };

  const handleLoginRequired = () => {
    window.dispatchEvent(new CustomEvent('bbj:auth:open', { detail: { reason: 'login_required' } }));
  };

  const handlePageChange = (page) => {
    fetchComments(page);
    document.getElementById('comments')?.scrollIntoView({ behavior: 'smooth' });
  };

  return (
    <section id="comments" className="mt-8">
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-2xl font-osw font-bold text-slate-800 dark:text-white">
          {pagination.total} {pagination.total === 1 ? 'Comment' : 'Comments'}
        </h2>

        <div className="flex items-center gap-2">
          <label className="text-sm text-slate-500">Sort by:</label>
          <select
            value={sort}
            onChange={(e) => handleSortChange(e.target.value)}
            className="px-3 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-sm text-slate-800 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          >
            <option value="newest">Newest</option>
            <option value="oldest">Oldest</option>
            <option value="popular">Most Popular</option>
          </select>
        </div>
      </div>

      <Suspense fallback={<div className="text-sm text-slate-400 mb-6">Loading composer…</div>}>
        <CommentForm postId={postId} onSubmit={handleNewComment} />
      </Suspense>

      {error && (
        <div className="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
          <p className="text-red-600 dark:text-red-400">{error}</p>
          <button
            onClick={() => fetchComments()}
            className="mt-2 text-sm text-red-500 hover:text-red-600 underline"
          >
            Try again
          </button>
        </div>
      )}

      {loading && comments.length === 0 && (
        <div className="space-y-4">
          {[1, 2, 3].map((i) => (
            <div key={i} className="animate-pulse flex gap-3">
              <div className="w-10 h-10 bg-slate-200 dark:bg-slate-700 rounded-full" />
              <div className="flex-1 space-y-2">
                <div className="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/4" />
                <div className="h-3 bg-slate-200 dark:bg-slate-700 rounded w-3/4" />
                <div className="h-3 bg-slate-200 dark:bg-slate-700 rounded w-1/2" />
              </div>
            </div>
          ))}
        </div>
      )}

      {!loading && comments.length === 0 && !error && (
        <div className="text-center py-12 bg-slate-50 dark:bg-slate-800 rounded-lg">
          <svg
            className="w-16 h-16 mx-auto text-slate-300 dark:text-slate-600 mb-4"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
          </svg>
          <p className="text-slate-500 dark:text-slate-400">No comments yet</p>
          <p className="text-sm text-slate-400 dark:text-slate-500 mt-1">Be the first to share your thoughts!</p>
        </div>
      )}

      {!loading && comments.length > 0 && (
        <div className="divide-y divide-slate-200 dark:divide-slate-700">
          {comments.map((comment) => (
            <CommentCard
              key={comment.id}
              comment={comment}
              postId={postId}
              onCommentAdded={handleNewComment}
              onCommentDeleted={handleCommentDeleted}
              onLoginRequired={handleLoginRequired}
              isHighlighted={highlightedCommentId === comment.id}
            />
          ))}
        </div>
      )}

      {pagination.total_pages > 1 && (
        <div className="mt-8 flex items-center justify-center gap-2">
          <button
            onClick={() => handlePageChange(pagination.current_page - 1)}
            disabled={pagination.current_page === 1}
            className="px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50 dark:hover:bg-slate-700"
          >
            Previous
          </button>

          <div className="flex items-center gap-1">
            {Array.from({ length: pagination.total_pages }, (_, i) => i + 1)
              .filter((page) => (
                page === 1 ||
                page === pagination.total_pages ||
                Math.abs(page - pagination.current_page) <= 1
              ))
              .map((page, index, array) => {
                const showEllipsisBefore = index > 0 && page - array[index - 1] > 1;
                return (
                  <span key={page} className="flex items-center">
                    {showEllipsisBefore && <span className="px-2 text-slate-400">...</span>}
                    <button
                      onClick={() => handlePageChange(page)}
                      className={`w-10 h-10 rounded-lg text-sm font-medium transition-colors ${
                        page === pagination.current_page
                          ? 'bg-primary-500 text-white'
                          : 'hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300'
                      }`}
                    >
                      {page}
                    </button>
                  </span>
                );
              })}
          </div>

          <button
            onClick={() => handlePageChange(pagination.current_page + 1)}
            disabled={pagination.current_page === pagination.total_pages}
            className="px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50 dark:hover:bg-slate-700"
          >
            Next
          </button>
        </div>
      )}

      {loading && comments.length > 0 && (
        <div className="fixed inset-0 bg-black/10 flex items-center justify-center z-50">
          <div className="bg-white dark:bg-slate-800 rounded-lg p-4 shadow-lg">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500 mx-auto" />
          </div>
        </div>
      )}
    </section>
  );
}

function CommentSectionFallback() {
  return (
    <section id="comments" className="mt-8">
      <div className="flex items-center justify-between mb-6">
        <div className="h-8 bg-slate-200 dark:bg-slate-700 rounded w-32 animate-pulse" />
        <div className="h-8 bg-slate-200 dark:bg-slate-700 rounded w-24 animate-pulse" />
      </div>
      <div className="space-y-4">
        {[1, 2, 3].map((i) => (
          <div key={i} className="animate-pulse flex gap-3">
            <div className="w-10 h-10 bg-slate-200 dark:bg-slate-700 rounded-full" />
            <div className="flex-1 space-y-2">
              <div className="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/4" />
              <div className="h-3 bg-slate-200 dark:bg-slate-700 rounded w-3/4" />
              <div className="h-3 bg-slate-200 dark:bg-slate-700 rounded w-1/2" />
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}

export default function CommentSection(props) {
  return (
    <Suspense fallback={<CommentSectionFallback />}>
      <CommentSectionInner {...props} />
    </Suspense>
  );
}
