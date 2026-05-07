import React, { useState, useEffect, useRef } from 'react';
import { useBbjUser } from '../hooks/useBbjUser.js';
import * as commentApi from '../lib/api.js';
import RankBadge from './RankBadge.jsx';
import OnlineIndicator from './OnlineIndicator.jsx';

export default function AuthorModal({ userId, isOpen, onClose }) {
  const { isAuthenticated } = useBbjUser();
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [followLoading, setFollowLoading] = useState(false);
  const [followError, setFollowError] = useState(null);
  const [error, setError] = useState(null);
  const modalRef = useRef(null);

  useEffect(() => {
    if (isOpen && userId) {
      fetchProfile();
    }
  }, [isOpen, userId]);

  useEffect(() => {
    const handleEscape = (e) => {
      if (e.key === 'Escape') {
        onClose?.();
      }
    };
    if (isOpen) {
      document.addEventListener('keydown', handleEscape);
      return () => document.removeEventListener('keydown', handleEscape);
    }
  }, [isOpen, onClose]);

  useEffect(() => {
    const handleClickOutside = (e) => {
      if (modalRef.current && !modalRef.current.contains(e.target)) {
        onClose?.();
      }
    };
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [isOpen, onClose]);

  const fetchProfile = async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await commentApi.userProfile(userId);
      if (result && (result.success || result.profile)) {
        setProfile(result.profile || result);
      } else {
        setError('Failed to load profile');
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const handleFollowToggle = async () => {
    if (!isAuthenticated || !profile) return;

    setFollowLoading(true);
    try {
      if (profile.is_following) {
        const result = await commentApi.unfollowUser(userId);
        if (result && result.success) {
          setProfile((prev) => ({
            ...prev,
            is_following: false,
            stats: { ...prev.stats, followers: result.follower_count },
          }));
        }
      } else {
        const result = await commentApi.followUser(userId);
        if (result && result.success) {
          setProfile((prev) => ({
            ...prev,
            is_following: true,
            stats: { ...prev.stats, followers: result.follower_count },
          }));
        }
      }
    } catch (err) {
      setFollowError(err.message || 'Follow action failed');
      setTimeout(() => setFollowError(null), 4000);
      console.error('Follow error:', err);
    } finally {
      setFollowLoading(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div
        ref={modalRef}
        className="bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-md mx-4 max-h-[90vh] overflow-hidden"
      >
        <button
          onClick={onClose}
          className="absolute top-4 right-4 p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 z-10"
          aria-label="Close"
        >
          <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        {loading ? (
          <div className="flex items-center justify-center py-16">
            <svg className="animate-spin h-8 w-8 text-primary-500" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
          </div>
        ) : error ? (
          <div className="p-6 text-center">
            <p className="text-red-500 dark:text-red-400">{error}</p>
            <button
              onClick={fetchProfile}
              className="mt-4 px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white rounded-lg text-sm"
            >
              Retry
            </button>
          </div>
        ) : profile ? (
          <div className="overflow-y-auto max-h-[90vh]">
            <div className="relative bg-gradient-to-br from-primary-500 to-primary-600 p-6 pb-16">
              <div className="relative mx-auto w-20 h-20 mb-4">
                <div className="w-20 h-20 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700 ring-4 ring-white dark:ring-slate-800">
                  <img
                    src={profile.avatar}
                    alt={profile.name}
                    width={80}
                    height={80}
                    className="w-full h-full object-cover"
                  />
                </div>
                {profile.is_online && (
                  <OnlineIndicator
                    isOnline={true}
                    size="md"
                    className="absolute bottom-1 right-1"
                  />
                )}
              </div>

              <h2 className="text-xl font-bold text-white text-center">{profile.name}</h2>

              <p className="text-sm text-white/70 text-center mt-1">
                {profile.is_online ? (
                  <span className="text-green-300">Online now</span>
                ) : profile.last_active ? (
                  <>Last active: {profile.last_active}</>
                ) : null}
              </p>

              {profile.rank && (
                <div className="flex justify-center mt-2">
                  <RankBadge rank={profile.rank} size="sm" />
                </div>
              )}
            </div>

            <div className="grid grid-cols-4 gap-1 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 -mt-8 mx-4 rounded-lg shadow-sm">
              <div className="text-center">
                <div className="text-lg font-bold text-slate-800 dark:text-white">{profile.stats?.comments ?? 0}</div>
                <div className="text-xs text-slate-500">Comments</div>
              </div>
              <div className="text-center">
                <div className="text-lg font-bold text-green-600">{profile.stats?.upvotes_received ?? 0}</div>
                <div className="text-xs text-slate-500">Upvotes</div>
              </div>
              <div className="text-center">
                <div className="text-lg font-bold text-slate-800 dark:text-white">{profile.stats?.followers ?? 0}</div>
                <div className="text-xs text-slate-500">Followers</div>
              </div>
              <div className="text-center">
                <div className="text-lg font-bold text-slate-800 dark:text-white">{profile.stats?.following ?? 0}</div>
                <div className="text-xs text-slate-500">Following</div>
              </div>
            </div>

            <div className="p-4">
              {profile.bio && (
                <p className="text-sm text-slate-600 dark:text-slate-400 mb-4">{profile.bio}</p>
              )}

              {profile.member_since_formatted && (
                <p className="text-xs text-slate-500 dark:text-slate-500 mb-4">
                  Member since {profile.member_since_formatted}
                </p>
              )}

              {isAuthenticated && !profile.is_self && (
                <button
                  onClick={handleFollowToggle}
                  disabled={followLoading}
                  className={`w-full py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50 ${
                    profile.is_following
                      ? 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600'
                      : 'bg-primary-500 hover:bg-primary-600 text-white'
                  }`}
                >
                  {followLoading ? '...' : profile.is_following ? 'Following' : 'Follow'}
                </button>
              )}

              {followError && (
                <p className="text-xs text-red-500 dark:text-red-400 mt-1 text-center">{followError}</p>
              )}

              {profile.recent_comments && profile.recent_comments.length > 0 && (
                <div className="mt-6">
                  <h3 className="text-sm font-semibold text-slate-800 dark:text-white mb-3">Recent Comments</h3>
                  <div className="space-y-3">
                    {profile.recent_comments.map((comment) => (
                      <div
                        key={comment.id}
                        className="text-sm p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg"
                      >
                        <p className="text-slate-600 dark:text-slate-400 line-clamp-2">{comment.content}</p>
                        <p className="text-xs text-slate-400 mt-1">
                          on {comment.post_title} · {comment.time_ago}
                        </p>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}
