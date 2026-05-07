import { useEffect, useState, useCallback } from 'react';

const AUTH_EVENT = 'bbj:auth:changed';
const ME_PATH = '/auth/me';

export function useBbjUser() {
  const initial = (typeof window !== 'undefined' && window.bbjComments && window.bbjComments.user) || null;
  const [user, setUser] = useState(initial);

  const refresh = useCallback(async () => {
    if (!window.bbjComments) return;
    try {
      const res = await fetch(window.bbjComments.endpoints.base + ME_PATH, {
        credentials: 'include',
        headers: { 'X-BBJ-Nonce': window.bbjComments.nonce },
      });
      if (res.ok) {
        const data = await res.json();
        setUser(data.user || null);
        window.bbjComments.user = data.user || null;
      } else if (res.status === 401) {
        setUser(null);
        window.bbjComments.user = null;
      }
    } catch (err) {
      console.error('[bbj-comments] auth refresh failed', err);
    }
  }, []);

  useEffect(() => {
    const handler = () => refresh();
    window.addEventListener(AUTH_EVENT, handler);
    return () => window.removeEventListener(AUTH_EVENT, handler);
  }, [refresh]);

  return { user, isAuthenticated: !!user, refresh };
}
