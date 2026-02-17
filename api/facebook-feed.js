const GRAPH_BASE_URL = 'https://graph.facebook.com';
const DEFAULT_LIMIT = 4;
const MAX_LIMIT = 6;
const MIN_LIMIT = 1;
const CACHE_TTL_MS = 30 * 60 * 1000;
const STALE_MAX_MS = 24 * 60 * 60 * 1000;
const UPSTREAM_TIMEOUT_MS = 3000;

if (!globalThis.__facebookFeedCache) {
  globalThis.__facebookFeedCache = {
    items: null,
    fetchedAt: 0,
  };
}

function clampLimit(rawLimit) {
  const parsed = Number.parseInt(rawLimit, 10);
  if (Number.isNaN(parsed)) {
    return DEFAULT_LIMIT;
  }
  return Math.max(MIN_LIMIT, Math.min(MAX_LIMIT, parsed));
}

function truncateMessage(message) {
  if (typeof message !== 'string') {
    return '';
  }
  const trimmed = message.trim();
  if (trimmed.length <= 500) {
    return trimmed;
  }
  return `${trimmed.slice(0, 497)}...`;
}

function normalizeItem(item) {
  if (!item || typeof item !== 'object') {
    return null;
  }
  if (!item.permalink_url) {
    return null;
  }

  return {
    id: String(item.id || ''),
    message: truncateMessage(item.message),
    publishedAt: item.created_time || null,
    permalink: item.permalink_url,
    image: item.full_picture || null,
  };
}

function createJsonResponse(res, statusCode, payload) {
  res.status(statusCode).setHeader('Content-Type', 'application/json; charset=utf-8');
  res.setHeader('Cache-Control', 'public, max-age=300, stale-while-revalidate=1800');
  res.send(JSON.stringify(payload));
}

async function fetchFromFacebook(limit) {
  const pageId = process.env.FB_PAGE_ID;
  const accessToken = process.env.FB_PAGE_ACCESS_TOKEN;
  const graphVersion = process.env.FB_GRAPH_VERSION || 'v22.0';

  if (!pageId || !accessToken) {
    throw new Error('MISSING_FACEBOOK_ENV');
  }

  const url = new URL(`${GRAPH_BASE_URL}/${graphVersion}/${encodeURIComponent(pageId)}/posts`);
  url.searchParams.set('fields', 'id,message,created_time,permalink_url,full_picture');
  url.searchParams.set('limit', String(limit));
  url.searchParams.set('access_token', accessToken);

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), UPSTREAM_TIMEOUT_MS);

  let response;
  try {
    response = await fetch(url, {
      headers: { Accept: 'application/json' },
      signal: controller.signal,
    });
  } finally {
    clearTimeout(timeout);
  }

  if (!response.ok) {
    const error = new Error('UPSTREAM_UNAVAILABLE');
    error.status = response.status;
    throw error;
  }

  const payload = await response.json();
  const rawItems = Array.isArray(payload?.data) ? payload.data : [];
  return rawItems.map(normalizeItem).filter(Boolean);
}

module.exports = async function handler(req, res) {
  if (req.method !== 'GET') {
    res.setHeader('Allow', 'GET');
    return createJsonResponse(res, 405, {
      ok: false,
      error: {
        code: 'METHOD_NOT_ALLOWED',
        message: 'Méthode non autorisée.',
        retryable: false,
      },
    });
  }

  const requestId = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
  const limit = clampLimit(req.query?.limit);
  const cache = globalThis.__facebookFeedCache;
  const now = Date.now();

  const cacheAge = cache.fetchedAt ? now - cache.fetchedAt : Number.POSITIVE_INFINITY;
  if (Array.isArray(cache.items) && cacheAge <= CACHE_TTL_MS) {
    return createJsonResponse(res, 200, {
      ok: true,
      generatedAt: new Date().toISOString(),
      requestId,
      cache: {
        hit: true,
        ageSeconds: Math.floor(cacheAge / 1000),
        ttlSeconds: Math.floor(CACHE_TTL_MS / 1000),
        stale: false,
      },
      items: cache.items.slice(0, limit),
    });
  }

  try {
    const items = await fetchFromFacebook(limit);

    cache.items = items;
    cache.fetchedAt = now;

    return createJsonResponse(res, 200, {
      ok: true,
      generatedAt: new Date().toISOString(),
      requestId,
      cache: {
        hit: false,
        ageSeconds: 0,
        ttlSeconds: Math.floor(CACHE_TTL_MS / 1000),
        stale: false,
      },
      items,
    });
  } catch (error) {
    const staleAge = cache.fetchedAt ? now - cache.fetchedAt : Number.POSITIVE_INFINITY;
    if (Array.isArray(cache.items) && staleAge <= STALE_MAX_MS) {
      return createJsonResponse(res, 200, {
        ok: true,
        degraded: true,
        reason: error.name === 'AbortError' ? 'UPSTREAM_TIMEOUT' : 'UPSTREAM_UNAVAILABLE',
        generatedAt: new Date().toISOString(),
        requestId,
        cache: {
          hit: true,
          ageSeconds: Math.floor(staleAge / 1000),
          ttlSeconds: Math.floor(CACHE_TTL_MS / 1000),
          stale: true,
        },
        items: cache.items.slice(0, limit),
      });
    }

    const code = error.message === 'MISSING_FACEBOOK_ENV'
      ? 'CONFIGURATION_ERROR'
      : error.name === 'AbortError'
        ? 'UPSTREAM_TIMEOUT'
        : 'UPSTREAM_UNAVAILABLE';

    return createJsonResponse(res, 503, {
      ok: false,
      error: {
        code,
        message: 'Le flux Facebook est temporairement indisponible.',
        retryable: true,
      },
      requestId,
    });
  }
};
