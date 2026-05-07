/**
 * Authenticated fetch wrapper for bbjd/v1.
 * - Always sends X-BBJ-Nonce.
 * - Optionally sends Authorization: Bearer if window.bbjComments.jwt is set.
 * - Auto-refreshes nonce once on 401 + rest_cookie_invalid_nonce, retries.
 * - Throws structured { code, message, status } on error.
 */

let nonceRefreshInflight = null;

async function refreshNonce() {
  if (nonceRefreshInflight) return nonceRefreshInflight;
  nonceRefreshInflight = (async () => {
    const res = await fetch(window.bbjComments.nonceRefreshUrl, {
      credentials: 'include',
    });
    if (!res.ok) throw new Error('nonce refresh failed: ' + res.status);
    const data = await res.json();
    window.bbjComments.nonce = data.nonce;
    return data.nonce;
  })()
    .finally(() => { nonceRefreshInflight = null; });
  return nonceRefreshInflight;
}

function authHeaders() {
  const headers = { 'X-BBJ-Nonce': window.bbjComments.nonce };
  if (window.bbjComments.jwt) headers['Authorization'] = 'Bearer ' + window.bbjComments.jwt;
  return headers;
}

export async function bbjAuthFetch(path, options = {}) {
  const url = window.bbjComments.endpoints.base + path;
  const init = {
    credentials: 'include',
    ...options,
    headers: { ...authHeaders(), ...(options.headers || {}) },
  };
  if (init.body && typeof init.body === 'object' && !(init.body instanceof FormData)) {
    init.headers['Content-Type'] = init.headers['Content-Type'] || 'application/json';
    init.body = JSON.stringify(init.body);
  }

  let res = await fetch(url, init);

  if (res.status === 401) {
    const cloned = res.clone();
    let body;
    try { body = await cloned.json(); } catch { body = null; }
    if (body && body.code === 'rest_cookie_invalid_nonce') {
      try {
        await refreshNonce();
        init.headers = { ...authHeaders(), ...(options.headers || {}) };
        res = await fetch(url, init);
      } catch (err) {
        const e = new Error('Authentication required'); e.status = 401; e.code = 'auth_required';
        window.dispatchEvent(new CustomEvent('bbj:auth:open', { detail: { reason: 'nonce_refresh_failed' } }));
        throw e;
      }
    }
  }

  if (!res.ok) {
    let body;
    try { body = await res.json(); } catch { body = null; }
    const err = new Error((body && body.message) || ('Request failed: ' + res.status));
    err.status = res.status;
    err.code = (body && body.code) || 'http_error';
    err.data = body && body.data;
    throw err;
  }

  if (res.status === 204) return null;
  return res.json();
}
