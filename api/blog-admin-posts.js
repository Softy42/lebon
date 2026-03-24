const {
  verifyAdmin,
  fetchCategoriesMap,
  normalizePost,
  listPosts,
  insertPost,
  updatePost,
  deletePost,
  slugExists
} = require('./blog-storage');

function sendJson(res, status, payload) {
  res.status(status);
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.send(JSON.stringify(payload));
}

module.exports = async function handler(req, res) {
  if (!verifyAdmin(req)) {
    return sendJson(res, 401, { ok: false, error: 'UNAUTHORIZED' });
  }

  try {
    if (req.method === 'GET') {
      const posts = await listPosts({ status: 'all' });
      return sendJson(res, 200, { ok: true, posts });
    }

    const categoriesById = await fetchCategoriesMap();

    if (req.method === 'POST') {
      const post = normalizePost(req.body, categoriesById);
      if (await slugExists(post.slug)) {
        return sendJson(res, 409, { ok: false, error: 'SLUG_ALREADY_EXISTS' });
      }
      await insertPost(post);
      return sendJson(res, 201, { ok: true, post });
    }

    if (req.method === 'PUT') {
      const id = String(req.query?.id || '');
      const existing = (await listPosts({ status: 'all' })).find((item) => item.id === id);
      if (!existing) {
        return sendJson(res, 404, { ok: false, error: 'POST_NOT_FOUND' });
      }

      const post = normalizePost(
        {
          ...existing,
          ...req.body,
          id,
          createdAt: existing.createdAt,
          categoryId: req.body?.categoryId || existing.categoryId,
          image: req.body?.image || existing.image,
          seo: req.body?.seo || existing.seo,
          cta: req.body?.cta || existing.cta,
        },
        categoriesById,
      );

      if (await slugExists(post.slug, id)) {
        return sendJson(res, 409, { ok: false, error: 'SLUG_ALREADY_EXISTS' });
      }

      await updatePost(id, post);
      return sendJson(res, 200, { ok: true, post });
    }

    if (req.method === 'DELETE') {
      const id = String(req.query?.id || '');
      const removed = await deletePost(id);
      if (!removed) {
        return sendJson(res, 404, { ok: false, error: 'POST_NOT_FOUND' });
      }
      return sendJson(res, 200, { ok: true });
    }

    res.setHeader('Allow', 'GET, POST, PUT, DELETE');
    return sendJson(res, 405, { ok: false, error: 'METHOD_NOT_ALLOWED' });
  } catch (error) {
    return sendJson(res, 400, { ok: false, error: error.message });
  }
};
