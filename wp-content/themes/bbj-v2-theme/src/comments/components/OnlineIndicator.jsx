import React from 'react';

/**
 * OnlineIndicator - Green dot to show user is online.
 * Sizes: xs (6px), sm (8px), md (10px), lg (12px)
 */
export default function OnlineIndicator({ isOnline, size = 'sm', className = '' }) {
  if (!isOnline) return null;

  const sizeClasses = {
    xs: 'w-1.5 h-1.5',
    sm: 'w-2 h-2',
    md: 'w-2.5 h-2.5',
    lg: 'w-3 h-3',
  };

  return (
    <span
      className={`inline-block rounded-full bg-green-500 ring-2 ring-white dark:ring-slate-800 ${sizeClasses[size] || sizeClasses.sm} ${className}`}
      title="Online"
      aria-label="Online"
    />
  );
}
