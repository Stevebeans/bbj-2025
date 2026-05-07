import React from 'react';
import { FaStar } from 'react-icons/fa';

export default function StaffPickBadge({ className = '' }) {
  return (
    <span
      className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300 ${className}`}
    >
      <FaStar className="w-2.5 h-2.5" />
      Staff Pick
    </span>
  );
}
