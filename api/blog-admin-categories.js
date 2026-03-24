const {
  verifyAdmin,
  fetchCategories,
  normalizeCategory,
  createCategory,
  updateCategory,
  deleteCategory,
  categoryInUse,
  categoryExists,
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
      const categories = await fetchCategories();
      return sendJson(res, 200, { ok: true, categories });
    }

    if (req.method === 'POST') {
      const categories = await fetchCategories();
      const category = normalizeCategory(req.body, categories.length + 1);
      if (await categoryExists(category.id)) {
        return sendJson(res, 409, { ok: false, error: 'CATEGORY_EXISTS' });
      }
      await createCategory(category);
      return sendJson(res, 201, { ok: true, category });
    }

    if (req.method === 'PUT') {
      const id = String(req.query?.id || '');
      if (!(await categoryExists(id))) {
        return sendJson(res, 404, { ok: false, error: 'CATEGORY_NOT_FOUND' });
      }
      const category = normalizeCategory({ ...req.body, id }, 1);
      await updateCategory(id, category);
      return sendJson(res, 200, { ok: true, category });
    }

    if (req.method === 'DELETE') {
      const id = String(req.query?.id || '');
      if (!(await categoryExists(id))) {
        return sendJson(res, 404, { ok: false, error: 'CATEGORY_NOT_FOUND' });
      }
      if (await categoryInUse(id)) {
        return sendJson(res, 409, { ok: false, error: 'CATEGORY_IN_USE' });
      }
      await deleteCategory(id);
      return sendJson(res, 200, { ok: true });
    }

    res.setHeader('Allow', 'GET, POST, PUT, DELETE');
    return sendJson(res, 405, { ok: false, error: 'METHOD_NOT_ALLOWED' });
  } catch (error) {
    return sendJson(res, 400, { ok: false, error: error.message });
  }
};
