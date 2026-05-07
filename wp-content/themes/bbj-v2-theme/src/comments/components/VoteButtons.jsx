import React, { useState } from 'react';
import { FaChevronUp, FaChevronDown } from 'react-icons/fa';
import { useBbjUser } from '../hooks/useBbjUser.js';
import * as commentApi from '../lib/api.js';
import { useToast } from '../hooks/useToast.js';

export default function VoteButtons({ commentId, votes, userVote, onVoteChange, onLoginRequired }) {
  const { isAuthenticated } = useBbjUser();
  const toast = useToast();
  const [loading, setLoading] = useState(false);
  const [currentVotes, setCurrentVotes] = useState(votes || { up: 0, down: 0, score: 0 });
  const [currentUserVote, setCurrentUserVote] = useState(userVote);

  const handleVote = async (voteType) => {
    if (!isAuthenticated) {
      onLoginRequired?.();
      return;
    }
    if (loading) return;

    const newVoteType = currentUserVote === (voteType === 'up' ? 1 : -1) ? 'remove' : voteType;

    setLoading(true);
    const previousVotes = { ...currentVotes };
    const previousUserVote = currentUserVote;

    let newVotes = { ...currentVotes };
    if (newVoteType === 'remove') {
      if (previousUserVote === 1) { newVotes.up--; newVotes.score--; }
      else if (previousUserVote === -1) { newVotes.down--; newVotes.score++; }
      setCurrentUserVote(null);
    } else {
      if (previousUserVote === 1) { newVotes.up--; newVotes.score--; }
      else if (previousUserVote === -1) { newVotes.down--; newVotes.score++; }
      if (newVoteType === 'up') {
        newVotes.up++; newVotes.score++; setCurrentUserVote(1);
      } else {
        newVotes.down++; newVotes.score--; setCurrentUserVote(-1);
      }
    }
    setCurrentVotes(newVotes);

    try {
      const result = await commentApi.vote(commentId, newVoteType);
      if (result && result.votes) {
        setCurrentVotes(result.votes);
        setCurrentUserVote(result.user_vote);
        onVoteChange?.(result.votes, result.user_vote);
      }
    } catch (error) {
      setCurrentVotes(previousVotes);
      setCurrentUserVote(previousUserVote);
      if (error.status === 401) {
        window.dispatchEvent(new CustomEvent('bbj:auth:open', { detail: { reason: 'vote' } }));
      } else {
        toast.push(error.message || 'Vote failed', { kind: 'error' });
      }
      console.error('Vote failed:', error);
    } finally {
      setLoading(false);
    }
  };

  const scoreColor =
    currentVotes.score > 0
      ? 'text-green-600 dark:text-green-400'
      : currentVotes.score < 0
      ? 'text-red-600 dark:text-red-400'
      : 'text-slate-500 dark:text-slate-400';

  return (
    <div className="flex items-center gap-1">
      <button
        onClick={() => handleVote('up')}
        disabled={loading}
        className={`p-1 rounded transition-colors ${
          currentUserVote === 1
            ? 'text-primary-500 bg-primary-50 dark:bg-primary-900/30'
            : 'text-slate-400 hover:text-primary-500 hover:bg-slate-100 dark:hover:bg-slate-700'
        } disabled:opacity-50`}
        aria-label="Upvote"
      >
        <FaChevronUp className="w-4 h-4" />
      </button>

      <span className={`min-w-[2rem] text-center font-bold text-sm ${scoreColor}`}>
        {currentVotes.score}
      </span>

      <button
        onClick={() => handleVote('down')}
        disabled={loading}
        className={`p-1 rounded transition-colors ${
          currentUserVote === -1
            ? 'text-red-500 bg-red-50 dark:bg-red-900/30'
            : 'text-slate-400 hover:text-red-500 hover:bg-slate-100 dark:hover:bg-slate-700'
        } disabled:opacity-50`}
        aria-label="Downvote"
      >
        <FaChevronDown className="w-4 h-4" />
      </button>
    </div>
  );
}
