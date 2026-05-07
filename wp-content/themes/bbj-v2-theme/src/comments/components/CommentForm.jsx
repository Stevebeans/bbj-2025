import React, { useState, useRef, useCallback } from 'react';
import { useBbjUser } from '../hooks/useBbjUser.js';
import * as commentApi from '../lib/api.js';
import MediaUploader from './MediaUploader.jsx';
import GiphyPicker from './GiphyPicker.jsx';
import EmojiPicker from './EmojiPicker.jsx';
import MentionAutocomplete from './MentionAutocomplete.jsx';

export default function CommentForm({
  postId,
  parentId = 0,
  onSubmit,
  onCancel,
  placeholder = 'Write a comment...',
  buttonText = 'Post Comment',
  compact = false,
}) {
  const { user, isAuthenticated } = useBbjUser();
  const [content, setContent] = useState('');
  const [media, setMedia] = useState(null);
  const [showMediaUploader, setShowMediaUploader] = useState(false);
  const [showGiphyPicker, setShowGiphyPicker] = useState(false);
  const [showEmojiPicker, setShowEmojiPicker] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const [mentionQuery, setMentionQuery] = useState('');
  const [mentionStartIndex, setMentionStartIndex] = useState(null);
  const [showMentionAutocomplete, setShowMentionAutocomplete] = useState(false);
  const textareaRef = useRef(null);

  const handleMediaChange = (newMedia) => {
    setMedia(newMedia);
    if (!newMedia) setShowMediaUploader(false);
  };

  const handleGiphySelect = (giphyMedia) => {
    setMedia(giphyMedia);
    setShowGiphyPicker(false);
    setShowMediaUploader(false);
  };

  const handleRemoveMedia = () => {
    setMedia(null);
    setShowMediaUploader(false);
    setShowGiphyPicker(false);
  };

  const handleEmojiSelect = (emoji) => {
    setContent((prev) => prev + emoji);
  };

  const handleContentChange = useCallback((e) => {
    const value = e.target.value;
    const cursorPos = e.target.selectionStart;
    setContent(value);

    const textBeforeCursor = value.substring(0, cursorPos);
    const lastAtIndex = textBeforeCursor.lastIndexOf('@');

    if (lastAtIndex !== -1) {
      const textAfterAt = textBeforeCursor.substring(lastAtIndex + 1);
      if (!/\s/.test(textAfterAt)) {
        setMentionQuery(textAfterAt);
        setMentionStartIndex(lastAtIndex);
        setShowMentionAutocomplete(true);
        return;
      }
    }
    setMentionQuery('');
    setMentionStartIndex(null);
    setShowMentionAutocomplete(false);
  }, []);

  const handleMentionSelect = useCallback((u) => {
    if (mentionStartIndex !== null) {
      const beforeMention = content.substring(0, mentionStartIndex);
      const afterMention = content.substring(mentionStartIndex + mentionQuery.length + 1);
      const newContent = `${beforeMention}@${u.display_name} ${afterMention}`;
      setContent(newContent);
      setMentionQuery('');
      setMentionStartIndex(null);
      setShowMentionAutocomplete(false);
      if (textareaRef.current) {
        const newCursorPos = beforeMention.length + u.display_name.length + 2;
        setTimeout(() => {
          textareaRef.current.focus();
          textareaRef.current.setSelectionRange(newCursorPos, newCursorPos);
        }, 0);
      }
    }
  }, [content, mentionStartIndex, mentionQuery]);

  const handleMentionClose = useCallback(() => {
    setMentionQuery('');
    setMentionStartIndex(null);
    setShowMentionAutocomplete(false);
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!content.trim()) {
      setError('Please enter a comment');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const result = await commentApi.create({
        postId,
        content,
        parentId,
        mediaId: media?.id || null,
      });
      setContent('');
      setMedia(null);
      setShowMediaUploader(false);
      setShowGiphyPicker(false);
      setShowEmojiPicker(false);
      onSubmit?.(result.comment || result);
    } catch (err) {
      if (err.status === 401) {
        window.dispatchEvent(new CustomEvent('bbj:auth:open', { detail: { reason: 'comment' } }));
      }
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  if (!isAuthenticated) {
    return (
      <div className={`bg-slate-50 dark:bg-slate-800 rounded-lg p-4 text-center ${compact ? '' : 'mb-6'}`}>
        <p className="text-slate-600 dark:text-slate-400 mb-3">
          Please log in to join the discussion
        </p>
        <button
          onClick={() => window.dispatchEvent(new CustomEvent('bbj:auth:open', { detail: { reason: 'login_required' } }))}
          className="px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white rounded-lg font-medium transition-colors"
        >
          Log In
        </button>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className={compact ? '' : 'mb-6'}>
      {error && (
        <div className="mb-3 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-600 dark:text-red-400 text-sm">
          {error}
        </div>
      )}

      <div className="flex gap-3">
        {!compact && (
          <div className="flex-shrink-0">
            <div className="w-10 h-10 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700">
              {user?.avatar_url || user?.avatar ? (
                <img
                  src={user.avatar_url || user.avatar}
                  alt={user?.display_name || 'User'}
                  width={40}
                  height={40}
                  className="w-full h-full object-cover"
                  loading="lazy"
                />
              ) : (
                <div className="w-full h-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                  <span className="text-primary-600 dark:text-primary-400 font-bold">
                    {user?.display_name?.charAt(0) || '?'}
                  </span>
                </div>
              )}
            </div>
          </div>
        )}

        <div className="flex-1 relative overflow-visible">
          <textarea
            ref={textareaRef}
            value={content}
            onChange={handleContentChange}
            placeholder={placeholder}
            rows={compact ? 2 : 3}
            className="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
          />

          {showMentionAutocomplete && (
            <MentionAutocomplete
              query={mentionQuery}
              onSelect={handleMentionSelect}
              onClose={handleMentionClose}
            />
          )}

          {media && !content.trim() && (
            <p className="text-xs text-amber-600 dark:text-amber-400 mt-1">Replies cannot be empty — add some text to post</p>
          )}

          {media && (
            <div className="mt-2">
              <div className="relative inline-block max-w-xs">
                <div className="relative rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700">
                  <img
                    src={media.url}
                    alt="Attached media"
                    className="max-w-full max-h-48 object-contain"
                    width={media.width || undefined}
                    height={media.height || undefined}
                  />
                </div>
                <button
                  type="button"
                  onClick={handleRemoveMedia}
                  className="absolute -top-2 -right-2 w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-md transition-colors"
                  aria-label="Remove media"
                >
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>
          )}

          {showMediaUploader && !media && (
            <div className="mt-2">
              <MediaUploader onMediaChange={handleMediaChange} disabled={loading} />
            </div>
          )}

          {showGiphyPicker && !media && (
            <div className="mt-2 relative">
              <GiphyPicker onSelect={handleGiphySelect} onClose={() => setShowGiphyPicker(false)} />
            </div>
          )}

          {showEmojiPicker && (
            <div className="mt-2 relative">
              <EmojiPicker onSelect={handleEmojiSelect} onClose={() => setShowEmojiPicker(false)} />
            </div>
          )}

          <div className="flex items-center justify-between mt-2">
            <div className="flex items-center gap-1">
              {!media && (
                <>
                  <button
                    type="button"
                    onClick={() => {
                      setShowMediaUploader(!showMediaUploader);
                      setShowGiphyPicker(false);
                      setShowEmojiPicker(false);
                    }}
                    className={`p-1.5 rounded transition-colors ${
                      showMediaUploader
                        ? 'text-primary-500 bg-primary-50 dark:bg-primary-900/30'
                        : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'
                    }`}
                    title="Add image"
                  >
                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                  </button>

                  <button
                    type="button"
                    onClick={() => {
                      setShowGiphyPicker(!showGiphyPicker);
                      setShowMediaUploader(false);
                      setShowEmojiPicker(false);
                    }}
                    className={`p-1.5 rounded transition-colors ${
                      showGiphyPicker
                        ? 'text-primary-500 bg-primary-50 dark:bg-primary-900/30'
                        : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'
                    }`}
                    title="Add GIF"
                  >
                    <span className="text-xs font-bold">GIF</span>
                  </button>
                </>
              )}

              <button
                type="button"
                onClick={() => {
                  setShowEmojiPicker(!showEmojiPicker);
                  setShowMediaUploader(false);
                  setShowGiphyPicker(false);
                }}
                className={`p-1.5 rounded transition-colors ${
                  showEmojiPicker
                    ? 'text-primary-500 bg-primary-50 dark:bg-primary-900/30'
                    : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'
                }`}
                title="Add emoji"
              >
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </button>
            </div>

            <div className="flex items-center gap-2">
              {onCancel && (
                <button
                  type="button"
                  onClick={onCancel}
                  className="px-3 py-1.5 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition-colors"
                >
                  Cancel
                </button>
              )}
              <button
                type="submit"
                disabled={loading || !content.trim()}
                className="px-4 py-1.5 bg-primary-500 hover:bg-primary-600 text-white rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                {loading ? 'Posting...' : buttonText}
              </button>
            </div>
          </div>
        </div>
      </div>
    </form>
  );
}
