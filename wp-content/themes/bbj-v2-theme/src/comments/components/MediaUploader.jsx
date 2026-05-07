import React, { useState, useRef } from 'react';
import * as commentApi from '../lib/api.js';

const MAX_FILE_SIZE = 2 * 1024 * 1024;
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif'];

export default function MediaUploader({ onMediaChange, disabled = false }) {
  const [media, setMedia] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState(null);
  const [dragOver, setDragOver] = useState(false);
  const fileInputRef = useRef(null);

  const validateFile = (file) => {
    if (!ALLOWED_TYPES.includes(file.type)) {
      return 'Only JPG, PNG and GIF files are allowed';
    }
    if (file.size > MAX_FILE_SIZE) {
      return 'File size must be under 2MB';
    }
    return null;
  };

  const handleFileSelect = async (file) => {
    if (!file) return;
    const validationError = validateFile(file);
    if (validationError) {
      setError(validationError);
      return;
    }

    setError(null);
    setUploading(true);

    try {
      const result = await commentApi.uploadMedia(file);
      if (result && (result.success || result.media)) {
        const mediaData = result.media || result;
        setMedia(mediaData);
        onMediaChange?.(mediaData);
      } else {
        throw new Error('Upload failed');
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setUploading(false);
    }
  };

  const handleInputChange = (e) => {
    const file = e.target.files?.[0];
    if (file) handleFileSelect(file);
    e.target.value = '';
  };

  const handleDrop = (e) => {
    e.preventDefault();
    setDragOver(false);
    const file = e.dataTransfer.files?.[0];
    if (file) handleFileSelect(file);
  };

  const handleDragOver = (e) => { e.preventDefault(); setDragOver(true); };
  const handleDragLeave = (e) => { e.preventDefault(); setDragOver(false); };

  const handleRemove = async () => {
    if (!media) return;
    try {
      if (media.id) await commentApi.deleteMedia(media.id);
    } catch (err) {
      console.error('Failed to delete media:', err);
    }
    setMedia(null);
    onMediaChange?.(null);
    setError(null);
  };

  const triggerFileSelect = () => fileInputRef.current?.click();

  if (media) {
    return (
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
          onClick={handleRemove}
          className="absolute -top-2 -right-2 w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-md transition-colors"
          aria-label="Remove media"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    );
  }

  return (
    <div>
      <input
        ref={fileInputRef}
        type="file"
        accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif"
        onChange={handleInputChange}
        className="hidden"
        disabled={disabled || uploading}
      />

      {error && (
        <div className="mb-2 text-sm text-red-500 dark:text-red-400">
          {error}
        </div>
      )}

      {uploading ? (
        <div className="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
          <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
          Uploading...
        </div>
      ) : (
        <div
          onDrop={handleDrop}
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          className={`border-2 border-dashed rounded-lg p-3 text-center cursor-pointer transition-colors ${
            dragOver
              ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
              : 'border-slate-300 dark:border-slate-600 hover:border-primary-400 dark:hover:border-primary-500'
          }`}
          onClick={triggerFileSelect}
        >
          <div className="flex items-center justify-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span>Add image (JPG/PNG/GIF, max 2MB)</span>
          </div>
        </div>
      )}
    </div>
  );
}
