const { fetchCategories, listPosts, getPostBySlug } = require('./blog-storage');

function sendJson(res, status, payload) {
  res.status(status);
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.setHeader('Cache-Control', 'public, max-age=120, stale-while-revalidate=600');
  res.send(JSON.stringify(payload));
}

module.exports = async function handler(req, res) {
  if (req.method !== 'GET') {
    res.setHeader('Allow', 'GET');
    return sendJson(res, 405, { ok: false, error: 'METHOD_NOT_ALLOWED' });
  }

  try {
    const categories = await fetchCategories();
    const categoryId = req.query?.category ? String(req.query.category) : null;
    const slug = req.query?.slug ? String(req.query.slug) : null;

    if (slug) {
      const post = await getPostBySlug(slug, { status: 'published' });
      if (!post) {
        return sendJson(res, 404, { ok: false, error: 'POST_NOT_FOUND' });
      }

      const publishedPosts = await listPosts({ status: 'published' });
      const relatedPosts = publishedPosts
        .filter((item) => item.slug !== post.slug)
        .filter((item) => post.relatedSlugs.includes(item.slug) || item.category?.id === post.category?.id)
        .slice(0, 3)
        .map((item) => ({
          title: item.title,
          slug: item.slug,
          excerpt: item.excerpt,
          image: item.image,
          publishedAt: item.publishedAt,
          category: item.category,
        }));

      return sendJson(res, 200, {
        ok: true,
        categories,
        post,
        relatedPosts,
      });
    }

    const posts = await listPosts({ status: 'published', categoryId });

    return sendJson(res, 200, {
      ok: true,
      categories,
      posts: posts.map((item) => ({
        title: item.title,
        slug: item.slug,
        excerpt: item.excerpt,
        publishedAt: item.publishedAt,
        category: item.category,
        image: item.image,
        readingTimeMinutes: item.readingTimeMinutes,
        author: item.author,
      })),
    });
  } catch (error) {
    return sendJson(res, 500, {
      ok: false,
      error: 'BLOG_STORAGE_UNAVAILABLE',
      detail: error.message,
    });
  }
};
