import React, { useState } from 'react';
import { FaReply, FaFlag, FaEllipsisV, FaEdit, FaTrash, FaLink, FaThumbtack } from 'react-icons/fa';
import { useBbjUser } from '../hooks/useBbjUser.js';
import * as commentApi from '../lib/api.js';
import VoteButtons from './VoteButtons.jsx';
import RankBadge from './RankBadge.jsx';
import ReactionButtons from './ReactionButtons.jsx';
import OnlineIndicator from './OnlineIndicator.jsx';
import AuthorModal from './AuthorModal.jsx';
import ReportModal from './ReportModal.jsx';
import StaffPickBadge from './StaffPickBadge.jsx';

// CommentForm lives in the composer chunk — lazy load on first reply intent.
const CommentForm = React.lazy(() =>
  import(/* webpackChunkName: "composer" */ '../composer.js').then((m) => ({ default: m.CommentForm }))
);

export default function CommentCard({
  comment,
  postId,
  depth = 0,
  onCommentAdded,
  onCommentDeleted,
  onLoginRequired,
  isHighlighted = false,
}) {
  const { user, isAuthenticated } = useBbjUser();
  const [showReplyForm, setShowReplyForm] = useState(false);
  const [showReportModal, setShowReportModal] = useState(false);
  const [showAuthorModal, setShowAuthorModal] = useState(false);
  const [showDropdown, setShowDropdown] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const [editContent, setEditContent] = useState(comment.content);
  const [currentContent, setCurrentContent] = useState(comment.content);
  const [loading, setLoading] = useState(false);
  const [replies, setReplies] = useState(comment.replies || []);
  const [isPinned, setIsPinned] = useState(comment.is_pinned || false);
  const [errorMsg, setErrorMsg] = useState(null);

  const showError = (msg) => {
    setErrorMsg(msg);
    setTimeout(() => setErrorMsg(null), 4000);
  };

  const canReply = depth < 3;
  const isAuthor = isAuthenticated && (user?.id === comment.author.id || user?.user_id === comment.author.id);
  const canModerate = comment.can_edit || comment.can_delete;

  const handleSharePermalink = async () => {
    const url = `${window.location.origin}${window.location.pathname}?comment=${comment.id}#comment-${comment.id}`;
    try {
      await navigator.clipboard.writeText(url);
    } catch (err) {
      const textarea = document.createElement('textarea');
      textarea.value = url;
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
    }
  };

  const handleReplySubmit = (newComment) => {
    setReplies([...replies, newComment]);
    setShowReplyForm(false);
    onCommentAdded?.(newComment);
  };

  const handleEdit = async () => {
    if (!editContent.trim()) return;
    setLoading(true);
    try {
      await commentApi.update(comment.id, { content: editContent });
      setCurrentContent(editContent);
      setIsEditing(false);
    } catch (error) {
      showError(error.message || 'Edit failed');
      console.error('Edit failed:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async () => {
    if (!window.confirm('Are you sure you want to delete this comment?')) return;
    setLoading(true);
    try {
      await commentApi.remove(comment.id);
      onCommentDeleted?.(comment.id);
    } catch (error) {
      showError(error.message || 'Delete failed');
      console.error('Delete failed:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleTogglePin = async () => {
    setLoading(true);
    try {
      if (isPinned) {
        await commentApi.unpin(comment.id);
        setIsPinned(false);
      } else {
        await commentApi.pin(comment.id);
        setIsPinned(true);
      }
    } catch (error) {
      showError(error.message || 'Pin toggle failed');
      console.error('Pin toggle failed:', error);
    } finally {
      setLoading(false);
    }
  };

  const formatContent = (text) => {
    const urlRegex = /(https?:\/\/[^\s]+)/g;
    const mentionRegex = /@([a-zA-Z0-9_-]+)/g;
    const parts = text.split(urlRegex);
    const result = [];

    parts.forEach((part, partIndex) => {
      if (part.match(urlRegex)) {
        result.push(
          <a
            key={`url-${partIndex}`}
            href={part}
            target="_blank"
            rel="noopener noreferrer"
            className="text-primary-500 hover:underline break-all"
          >
            {part}
          </a>
        );
      } else {
        const mentionParts = part.split(mentionRegex);
        mentionParts.forEach((mentionPart, mentionIndex) => {
          if (mentionIndex % 2 === 1) {
            result.push(
              <span
                key={`mention-${partIndex}-${mentionIndex}`}
                className="text-primary-500 font-medium cursor-pointer hover:underline"
              >
                @{mentionPart}
              </span>
            );
          } else if (mentionPart) {
            result.push(mentionPart);
          }
        });
      }
    });

    return result;
  };

  return (
    <div
      id={`comment-${comment.id}`}
      className={`${depth > 0 ? 'ml-6 md:ml-10 pl-4 border-l-2 border-slate-200 dark:border-slate-600' : ''} ${isHighlighted ? 'highlight-comment' : ''}`}
    >
      <div className="py-4">
        <div className="flex gap-3">
          <div className="flex-shrink-0 relative w-10 h-10">
            <div className="w-10 h-10 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700">
              {comment.author.avatar ? (
                <img
                  src={comment.author.avatar}
                  alt={comment.author.name}
                  width={40}
                  height={40}
                  className="w-full h-full object-cover"
                  loading="lazy"
                />
              ) : (
                <div className="w-full h-full flex items-center justify-center text-slate-500 font-bold">
                  {comment.author.name?.charAt(0) || '?'}
                </div>
              )}
            </div>
            {comment.author.is_online && (
              <OnlineIndicator
                isOnline={true}
                size="sm"
                className="absolute bottom-0 right-0"
              />
            )}
          </div>

          <div className="flex-1 min-w-0">
            <div className="flex flex-wrap items-center gap-2 mb-1">
              <button
                onClick={() => comment.author.id > 0 && setShowAuthorModal(true)}
                className={`font-semibold text-slate-800 dark:text-white ${
                  comment.author.id > 0 ? 'hover:text-primary-500 cursor-pointer' : ''
                }`}
                disabled={comment.author.id === 0}
              >
                {comment.author.name}
              </button>
              {comment.author.rank && <RankBadge rank={comment.author.rank} size="xs" />}
              {isPinned && <StaffPickBadge />}
              <span className="text-xs text-slate-500" title={comment.date}>
                {comment.time_ago}
              </span>
            </div>

            {isEditing ? (
              <div className="mb-2">
                <textarea
                  value={editContent}
                  onChange={(e) => setEditContent(e.target.value)}
                  rows={3}
                  className="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                />
                <div className="flex gap-2 mt-2">
                  <button
                    onClick={() => {
                      setIsEditing(false);
                      setEditContent(currentContent);
                    }}
                    className="px-3 py-1 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200"
                  >
                    Cancel
                  </button>
                  <button
                    onClick={handleEdit}
                    disabled={loading}
                    className="px-3 py-1 bg-primary-500 hover:bg-primary-600 text-white rounded text-sm font-medium disabled:opacity-50"
                  >
                    {loading ? 'Saving...' : 'Save'}
                  </button>
                </div>
              </div>
            ) : (
              <div className="text-slate-700 dark:text-slate-300 text-sm whitespace-pre-wrap break-words">
                {formatContent(currentContent)}
              </div>
            )}

            {comment.media && (
              <div className="mt-2">
                <div className="inline-block max-w-sm rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700">
                  <img
                    src={comment.media.url}
                    alt="Attached"
                    className="max-w-full max-h-64 object-contain"
                    loading="lazy"
                    width={comment.media.width || undefined}
                    height={comment.media.height || undefined}
                  />
                </div>
              </div>
            )}

            {errorMsg && (
              <div className="text-xs text-red-500 dark:text-red-400 mt-1 bg-red-50 dark:bg-red-900/20 px-2 py-1 rounded">
                {errorMsg}
              </div>
            )}

            {!isEditing && (
              <div className="flex items-center gap-4 mt-2">
                <VoteButtons
                  commentId={comment.id}
                  votes={comment.votes}
                  userVote={comment.user_vote}
                  onLoginRequired={onLoginRequired}
                />

                <ReactionButtons
                  commentId={comment.id}
                  reactions={comment.reactions}
                  reactionTotal={comment.reaction_total}
                  userReaction={comment.user_reaction}
                  onLoginRequired={onLoginRequired}
                />

                {canReply && (
                  <button
                    onClick={() => {
                      if (!isAuthenticated) {
                        onLoginRequired?.();
                        return;
                      }
                      setShowReplyForm(!showReplyForm);
                    }}
                    className="flex items-center gap-1 text-xs text-slate-500 hover:text-primary-500 transition-colors"
                  >
                    <FaReply className="w-3 h-3" />
                    <span>Reply</span>
                  </button>
                )}

                {isAuthenticated && !isAuthor && (
                  <button
                    onClick={() => setShowReportModal(true)}
                    className="flex items-center gap-1 text-xs text-slate-500 hover:text-red-500 transition-colors"
                  >
                    <FaFlag className="w-3 h-3" />
                    <span>Report</span>
                  </button>
                )}

                <button
                  onClick={handleSharePermalink}
                  className="flex items-center gap-1 text-xs text-slate-500 hover:text-primary-500 transition-colors"
                  title="Copy link to comment"
                >
                  <FaLink className="w-3 h-3" />
                  <span className="hidden sm:inline">Share</span>
                </button>

                {canModerate && (
                  <div className="relative">
                    <button
                      onClick={() => setShowDropdown(!showDropdown)}
                      className="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 rounded"
                    >
                      <FaEllipsisV className="w-3 h-3" />
                    </button>

                    {showDropdown && (
                      <>
                        <div
                          className="fixed inset-0 z-10"
                          onClick={() => setShowDropdown(false)}
                        />
                        <div className="absolute right-0 mt-1 w-36 bg-white dark:bg-slate-700 rounded-lg shadow-lg border border-slate-200 dark:border-slate-600 z-20 py-1">
                          {comment.can_edit && (
                            <button
                              onClick={() => {
                                setIsEditing(true);
                                setShowDropdown(false);
                              }}
                              className="w-full flex items-center gap-2 px-3 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600"
                            >
                              <FaEdit className="w-3 h-3" />
                              Edit
                            </button>
                          )}
                          {comment.can_pin && (
                            <button
                              onClick={() => {
                                handleTogglePin();
                                setShowDropdown(false);
                              }}
                              className="w-full flex items-center gap-2 px-3 py-2 text-sm text-amber-600 dark:text-amber-400 hover:bg-slate-100 dark:hover:bg-slate-600"
                            >
                              <FaThumbtack className="w-3 h-3" />
                              {isPinned ? 'Unpin' : 'Pin'}
                            </button>
                          )}
                          {comment.can_delete && (
                            <button
                              onClick={() => {
                                handleDelete();
                                setShowDropdown(false);
                              }}
                              className="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-slate-100 dark:hover:bg-slate-600"
                            >
                              <FaTrash className="w-3 h-3" />
                              Delete
                            </button>
                          )}
                        </div>
                      </>
                    )}
                  </div>
                )}
              </div>
            )}
          </div>
        </div>

        {showReplyForm && (
          <div className="mt-4 ml-13">
            <React.Suspense fallback={<div className="text-xs text-gray-400">Loading editor…</div>}>
              <CommentForm
                postId={postId}
                parentId={comment.id}
                onSubmit={handleReplySubmit}
                onCancel={() => setShowReplyForm(false)}
                placeholder={`Reply to ${comment.author.name}...`}
                buttonText="Reply"
                compact
              />
            </React.Suspense>
          </div>
        )}
      </div>

      {replies.length > 0 && (
        <div>
          {replies.map((reply) => (
            <CommentCard
              key={reply.id}
              comment={reply}
              postId={postId}
              depth={depth + 1}
              onCommentAdded={onCommentAdded}
              onCommentDeleted={(id) => {
                setReplies(replies.filter((r) => r.id !== id));
                onCommentDeleted?.(id);
              }}
              onLoginRequired={onLoginRequired}
              isHighlighted={false}
            />
          ))}
        </div>
      )}

      <ReportModal
        isOpen={showReportModal}
        onClose={() => setShowReportModal(false)}
        commentId={comment.id}
        commentAuthor={comment.author.name}
      />

      <AuthorModal
        userId={comment.author.id}
        isOpen={showAuthorModal}
        onClose={() => setShowAuthorModal(false)}
      />
    </div>
  );
}
