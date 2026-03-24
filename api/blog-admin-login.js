function sendJson(res, status, payload) {
  res.status(status);
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.send(JSON.stringify(payload));
}

module.exports = async function handler(req, res) {
  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    return sendJson(res, 405, { ok: false, error: 'METHOD_NOT_ALLOWED' });
  }

  const { username, password } = req.body || {};
  const expectedUsername = process.env.BLOG_ADMIN_USERNAME;
  const expectedPassword = process.env.BLOG_ADMIN_PASSWORD;
  const adminToken = process.env.BLOG_ADMIN_TOKEN;

  if (!expectedUsername || !expectedPassword || !adminToken) {
    return sendJson(res, 500, { ok: false, error: 'ADMIN_ENV_MISSING' });
  }

  if (username !== expectedUsername || password !== expectedPassword) {
    return sendJson(res, 401, { ok: false, error: 'INVALID_CREDENTIALS' });
  }

  return sendJson(res, 200, {
    ok: true,
    token: adminToken,
  });
};
