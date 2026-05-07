import React, { useState, useEffect, useCallback, useRef } from 'react';
import * as commentApi from '../lib/api.js';

export default function GiphyPicker({ onSelect, onClose }) {
  const [query, setQuery] = useState('');
  const [gifs, setGifs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [selecting, setSelecting] = useState(null);
  const searchTimeout = useRef(null);
  const containerRef = useRef(null);

  useEffect(() => { loadTrending(); }, []);

  useEffect(() => {
    const handleClickOutside = (e) => {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        onClose?.();
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [onClose]);

  const loadTrending = async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await commentApi.giphyTrending(20);
      if (result && (result.success || result.gifs)) {
        setGifs(result.gifs || []);
      }
    } catch (err) {
      setError(err.message || 'Failed to load GIFs');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = useCallback(async (searchQuery) => {
    if (!searchQuery.trim()) {
      loadTrending();
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const result = await commentApi.giphySearch(searchQuery, 20, 0);
      if (result && (result.success || result.gifs)) {
        setGifs(result.gifs || []);
      }
    } catch (err) {
      setError(err.message || 'GIF search failed');
      console.error(err);
    } finally {
      setLoading(false);
    }
  }, []);

  const handleQueryChange = (e) => {
    const value = e.target.value;
    setQuery(value);
    if (searchTimeout.current) clearTimeout(searchTimeout.current);
    searchTimeout.current = setTimeout(() => handleSearch(value), 400);
  };

  const handleSelectGif = async (gif) => {
    setSelecting(gif.id);
    setError(null);
    try {
      const result = await commentApi.giphyStore(gif.id, gif.url, gif.width, gif.height);
      if (result && (result.success || result.media)) {
        onSelect?.(result.media || result);
      } else {
        throw new Error('Failed to save GIF');
      }
    } catch (err) {
      setError(err.message || 'Failed to select GIF');
      console.error(err);
    } finally {
      setSelecting(null);
    }
  };

  return (
    <div
      ref={containerRef}
      className="bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 overflow-hidden w-80"
    >
      <div className="p-3 border-b border-slate-200 dark:border-slate-700">
        <div className="flex items-center gap-2">
          <div className="flex-1 relative">
            <input
              type="text"
              value={query}
              onChange={handleQueryChange}
              placeholder="Search GIFs..."
              className="w-full pl-8 pr-3 py-1.5 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-800 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            />
            <svg className="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 rounded"
            aria-label="Close"
          >
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <div className="p-2 max-h-64 overflow-y-auto">
        {error && (
          <div className="text-center text-red-500 dark:text-red-400 text-sm py-4">
            {error}
          </div>
        )}

        {loading && !gifs.length ? (
          <div className="flex items-center justify-center py-8">
            <svg className="animate-spin h-6 w-6 text-primary-500" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
          </div>
        ) : gifs.length === 0 ? (
          <div className="text-center text-slate-500 dark:text-slate-400 text-sm py-8">
            No GIFs found
          </div>
        ) : (
          <div className="grid grid-cols-2 gap-2">
            {gifs.map((gif) => (
              <button
                key={gif.id}
                onClick={() => handleSelectGif(gif)}
                disabled={selecting === gif.id}
                className="relative aspect-video rounded overflow-hidden hover:ring-2 hover:ring-primary-500 transition-all focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-50"
              >
                <img
                  src={gif.preview_url}
                  alt={gif.title || 'GIF'}
                  className="w-full h-full object-cover"
                  loading="lazy"
                />
                {selecting === gif.id && (
                  <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
                    <svg className="animate-spin h-5 w-5 text-white" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                  </div>
                )}
              </button>
            ))}
          </div>
        )}
      </div>

      <div className="px-3 py-2 bg-slate-50 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-700 text-center">
        <span className="text-xs text-slate-500 dark:text-slate-400">Powered by GIPHY</span>
      </div>
    </div>
  );
}
