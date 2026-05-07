import React from 'react';
import { iconMap, colorClasses, ringClasses, sizeClasses, iconSizes } from '../lib/rankConfig.js';

export default function RankBadge({ rank, showLabel = true, size = 'sm' }) {
  if (!rank) return null;

  const Icon = rank.icon ? iconMap[rank.icon] : null;
  const textColor = colorClasses[rank.color] || 'text-gray-500';
  const bgColor = colorClasses[rank.bg_color] || 'bg-gray-100 dark:bg-gray-800';
  const ringClass = rank.ring ? ringClasses[rank.ring] || '' : '';

  return (
    <span
      className={`
        inline-flex items-center gap-1 rounded-full font-medium
        ${sizeClasses[size]}
        ${bgColor}
        ${textColor}
        ${ringClass}
      `}
    >
      {Icon && <Icon className={iconSizes[size]} />}
      {showLabel && <span>{rank.name}</span>}
    </span>
  );
}

export function RankBadgeInline({ rank }) {
  if (!rank) return null;

  const Icon = rank.icon ? iconMap[rank.icon] : null;
  const textColor = colorClasses[rank.color] || 'text-gray-500';

  return (
    <span className={`inline-flex items-center gap-1 ${textColor}`} title={rank.name}>
      {Icon && <Icon className="w-3 h-3" />}
      <span className="text-xs font-medium">{rank.name}</span>
    </span>
  );
}
