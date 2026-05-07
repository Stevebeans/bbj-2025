import React, { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

let pushExternal = null;

export function useToast() {
  return {
    push: (msg, opts = {}) => pushExternal && pushExternal(msg, opts),
  };
}

export function ToastHost() {
  const [items, setItems] = useState([]);
  const [host] = useState(() => {
    if (typeof document === 'undefined') return null;
    let el = document.getElementById('bbj-comments-toasts');
    if (!el) {
      el = document.createElement('div');
      el.id = 'bbj-comments-toasts';
      el.className = 'fixed bottom-4 right-4 z-50 space-y-2 pointer-events-none';
      document.body.appendChild(el);
    }
    return el;
  });

  const push = useCallback((msg, { kind = 'info', ttl = 4000 } = {}) => {
    const id = Math.random().toString(36).slice(2);
    setItems((prev) => [...prev, { id, msg, kind }]);
    setTimeout(() => setItems((prev) => prev.filter((t) => t.id !== id)), ttl);
  }, []);

  useEffect(() => { pushExternal = push; return () => { pushExternal = null; }; }, [push]);

  if (!host) return null;
  return createPortal(
    items.map((t) => React.createElement('div', {
      key: t.id,
      className: 'pointer-events-auto rounded-md px-3 py-2 shadow-md text-sm ' + (
        t.kind === 'error' ? 'bg-red-600 text-white' :
        t.kind === 'success' ? 'bg-emerald-600 text-white' :
        'bg-gray-900 text-white'
      ),
    }, t.msg)),
    host
  );
}
