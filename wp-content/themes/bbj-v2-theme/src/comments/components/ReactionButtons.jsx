import React, { useState, useRef, useEffect } from 'react';
import { useBbjUser } from '../hooks/useBbjUser.js';
import * as commentApi from '../lib/api.js';
import { useToast } from '../hooks/useToast.js';

export const REACTION_TYPES = {
  like:  '👍',
  love:  '❤️',
  haha:  '😂',
  wow:   '😮',
  sad:   '😢',
  angry: '😡',
};

export default function ReactionButtons({
  commentId,
  reactions = {},
  reactionTotal = 0,
  userReaction = null,
  onLoginRequired,
}) {
  const { isAuthenticated } = useBbjUser();
  const toast = useToast();
  const [currentReactions, setCurrentReactions] = useState(reactions);
  const [currentTotal, setCurrentTotal] = useState(reactionTotal);
  const [currentUserReaction, setCurrentUserReaction] = useState(userReaction);
  const [showPicker, setShowPicker] = useState(false);
  const [loading, setLoading] = useState(false);
  const pickerRef = useRef(null);

  useEffect(() => {
    const handleClickOutside = (e) => {
      if (pickerRef.current && !pickerRef.current.contains(e.target)) {
        setShowPicker(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleReactionClick = async (reactionType) => {
    if (!isAuthenticated) {
      onLoginRequired?.();
      return;
    }

    setLoading(true);
    setShowPicker(false);

    try {
      let result;
      if (currentUserReaction === reactionType) {
        result = await commentApi.reactRemove(commentId, reactionType);
        if (result && (result.success || result.reactions)) {
          setCurrentReactions(result.reactions || {});
          setCurrentTotal(result.total || 0);
          setCurrentUserReaction(null);
        }
      } else {
        result = await commentApi.reactAdd(commentId, reactionType);
        if (result && (result.success || result.reactions)) {
          setCurrentReactions(result.reactions || {});
          setCurrentTotal(result.total || 0);
          setCurrentUserReaction(result.user_reaction || reactionType);
        }
      }
    } catch (error) {
      if (error.status === 401) {
        window.dispatchEvent(new CustomEvent('bbj:auth:open', { detail: { reason: 'react' } }));
      } else {
        toast.push(error.message || 'Reaction failed', { kind: 'error' });
      }
      console.error('Reaction error:', error);
    } finally {
      setLoading(false);
    }
  };

  const topReactions = Object.entries(currentReactions)
    .sort(([, a], [, b]) => b - a)
    .slice(0, 3)
    .map(([type]) => type);

  return (
    <div className="relative flex items-center" ref={pickerRef}>
      <button
        onClick={() => {
          if (!isAuthenticated) {
            onLoginRequired?.();
            return;
          }
          setShowPicker(!showPicker);
        }}
        disabled={loading}
        className={`flex items-center gap-1 text-xs transition-colors ${
          currentUserReaction
            ? 'text-primary-500'
            : 'text-slate-500 hover:text-primary-500'
        } disabled:opacity-50`}
      >
        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span className="hidden sm:inline">React</span>
      </button>

      {currentTotal > 0 && (
        <div className="flex items-center ml-1.5">
          <div className="flex -space-x-1">
            {topReactions.map((type) => (
              <span
                key={type}
                className="text-xs bg-slate-100 dark:bg-slate-700 rounded-full w-5 h-5 flex items-center justify-center"
                title={`${currentReactions[type]} ${type}`}
              >
                {REACTION_TYPES[type]}
              </span>
            ))}
          </div>
          <span className="text-xs text-slate-500 ml-1">{currentTotal}</span>
        </div>
      )}

      {showPicker && (
        <div className="absolute bottom-full left-0 mb-2 z-20">
          <div className="bg-white dark:bg-slate-800 rounded-full shadow-lg border border-slate-200 dark:border-slate-700 px-2 py-1.5 flex gap-1">
            {Object.entries(REACTION_TYPES).map(([type, emoji]) => (
              <button
                key={type}
                onClick={() => handleReactionClick(type)}
                className={`w-8 h-8 rounded-full flex items-center justify-center text-lg transition-transform hover:scale-125 ${
                  currentUserReaction === type ? 'bg-primary-100 dark:bg-primary-900/30' : 'hover:bg-slate-100 dark:hover:bg-slate-700'
                }`}
                title={type.charAt(0).toUpperCase() + type.slice(1)}
              >
                {emoji}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
