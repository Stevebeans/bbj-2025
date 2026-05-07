// Mirror of bbj-app's RankBadge static config. Update both if ranks change.
import { FaCrown, FaMedal, FaGem, FaShieldAlt, FaStar, FaTrophy } from 'react-icons/fa';

export const iconMap = {
  crown:  FaCrown,
  medal:  FaMedal,
  gem:    FaGem,
  shield: FaShieldAlt,
  star:   FaStar,
  trophy: FaTrophy,
};

// Tailwind class lookup keyed by the rank's color token.
export const colorClasses = {
  // Text colors
  'gray-500':   'text-gray-500',
  'orange-600': 'text-orange-600',
  'cyan-600':   'text-cyan-600',
  'yellow-600': 'text-yellow-600',
  'purple-600': 'text-purple-600',
  'teal-600':   'text-teal-600',
  'red-600':    'text-red-600',
  'blue-600':   'text-blue-600',
  'pink-600':   'text-pink-600',
  'amber-500':  'text-amber-500',
  // Background colors
  'gray-100':   'bg-gray-100 dark:bg-gray-800',
  'orange-100': 'bg-orange-100 dark:bg-orange-900/30',
  'cyan-100':   'bg-cyan-100 dark:bg-cyan-900/30',
  'yellow-100': 'bg-yellow-100 dark:bg-yellow-900/30',
  'purple-100': 'bg-purple-100 dark:bg-purple-900/30',
  'teal-100':   'bg-teal-100 dark:bg-teal-900/30',
  'red-100':    'bg-red-100 dark:bg-red-900/30',
  'blue-100':   'bg-blue-100 dark:bg-blue-900/30',
  'amber-100':  'bg-amber-100 dark:bg-amber-900/30',
  'amber-200':  'bg-amber-200 dark:bg-amber-800/40',
  'pink-100':   'bg-pink-100 dark:bg-pink-900/30',
};

export const ringClasses = {
  'orange-400': 'ring-2 ring-orange-400 ring-offset-1',
  'amber-400':  'ring-2 ring-amber-400 ring-offset-1',
};

export const sizeClasses = {
  xs: 'text-xs px-1.5 py-0.5',
  sm: 'text-xs px-2 py-1',
  md: 'text-sm px-3 py-1.5',
};

export const iconSizes = {
  xs: 'w-2.5 h-2.5',
  sm: 'w-3 h-3',
  md: 'w-4 h-4',
};
