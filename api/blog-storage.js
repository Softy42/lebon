const crypto = require('node:crypto');

const mysql = require('mysql2/promise');

if (!globalThis.__blogDbPool) {
  const port = Number(process.env.BLOG_DB_PORT || 3306);
  globalThis.__blogDbPool = mysql.createPool({
    host: process.env.BLOG_DB_HOST,
    port,
    user: process.env.BLOG_DB_USER,
    password: process.env.BLOG_DB_PASSWORD,
    database: process.env.BLOG_DB_NAME,
    waitForConnections: true,
    connectionLimit: Number(process.env.BLOG_DB_POOL_SIZE || 10),
    queueLimit: 0,
    charset: 'utf8mb4',
  });
}

const pool = globalThis.__blogDbPool;
const DEFAULT_CATEGORY_ID = 'actualites-maison-melina';

function slugify(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-');
}

function nowIso() {
  return new Date().toISOString();
}

function excerptFromBody(body) {
  const text = String(body || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
  return text.length <= 180 ? text : `${text.slice(0, 177)}...`;
}

function estimateReadTime(content) {
  const words = String(content || '').trim().split(/\s+/).filter(Boolean).length;
  return Math.max(1, Math.ceil(words / 200));
}

function normalizeCategory(input, index) {
  const name = String(input?.name || '').trim();
  if (!name) {
    throw new Error(`INVALID_CATEGORY_${index}`);
  }
  const id = slugify(input?.id || name);
  return {
    id,
    name,
    description: String(input?.description || '').trim(),
    order: Number.isFinite(Number(input?.order)) ? Number(input.order) : index,
  };
}

function normalizePost(input, categoriesById) {
  const title = String(input?.title || '').trim();
  if (!title) {
    throw new Error('TITLE_REQUIRED');
  }

  const slug = slugify(input?.slug || title);
  if (!slug) {
    throw new Error('SLUG_REQUIRED');
  }

  const content = String(input?.content || '').trim();
  if (!content) {
    throw new Error('CONTENT_REQUIRED');
  }

  const categoryId = String(input?.categoryId || DEFAULT_CATEGORY_ID);
  if (!categoriesById.has(categoryId)) {
    throw new Error('INVALID_CATEGORY');
  }

  const status = input?.status === 'draft' ? 'draft' : 'published';
  const publishedAt = status === 'published' ? (input?.publishedAt || nowIso()) : null;

  const image = input?.image && typeof input.image === 'object'
    ? {
        url: String(input.image.url || '').trim(),
        alt: String(input.image.alt || title).trim(),
      }
    : null;

  const excerpt = String(input?.excerpt || '').trim() || excerptFromBody(content);
  const seoTitle = String(input?.seo?.title || title).trim();
  const seoDescription = String(input?.seo?.description || excerpt).trim();

  return {
    id: String(input?.id || crypto.randomUUID()),
    title,
    slug,
    excerpt,
    content,
    categoryId,
    status,
    publishedAt,
    updatedAt: nowIso(),
    createdAt: input?.createdAt || nowIso(),
    author: String(input?.author || 'Équipe Maison Mélina').trim(),
    image,
    cta: {
      label: String(input?.cta?.label || 'Demander une visite').trim(),
      url: String(input?.cta?.url || '/contact').trim(),
    },
    seo: {
      title: seoTitle,
      description: seoDescription,
    },
    readingTimeMinutes: estimateReadTime(content),
    relatedSlugs: Array.isArray(input?.relatedSlugs)
      ? input.relatedSlugs.map((item) => slugify(item)).filter(Boolean)
      : [],
  };
}

function mapPostRow(row) {
  let relatedSlugs = [];
  try {
    relatedSlugs = row.related_slugs ? JSON.parse(row.related_slugs) : [];
  } catch {
    relatedSlugs = [];
  }

  return {
    id: row.id,
    title: row.title,
    slug: row.slug,
    excerpt: row.excerpt,
    content: row.content,
    status: row.status,
    publishedAt: row.published_at,
    updatedAt: row.updated_at,
    createdAt: row.created_at,
    author: row.author,
    image: row.image_url ? { url: row.image_url, alt: row.image_alt || row.title } : null,
    seo: { title: row.seo_title || row.title, description: row.seo_description || row.excerpt },
    cta: { label: row.cta_label || 'Demander une visite', url: row.cta_url || '/contact' },
    readingTimeMinutes: row.reading_time_minutes || 1,
    relatedSlugs,
    category: row.category_id ? { id: row.category_id, name: row.category_name } : null,
    categoryId: row.category_id,
  };
}

async function query(sql, params = []) {
  const [rows] = await pool.execute(sql, params);
  return rows;
}

async function fetchCategories() {
  return query(
    'SELECT id, name, description, sort_order AS `order` FROM blog_categories ORDER BY sort_order ASC, name ASC',
  );
}

async function fetchCategoriesMap() {
  const rows = await fetchCategories();
  return new Map(rows.map((row) => [row.id, row]));
}

async function listPosts({ status = 'published', categoryId = null } = {}) {
  const params = [];
  const where = [];

  if (status !== 'all') {
    where.push('p.status = ?');
    params.push(status);
  }
  if (categoryId) {
    where.push('p.category_id = ?');
    params.push(categoryId);
  }

  const sql = `
    SELECT
      p.id,
      p.title,
      p.slug,
      p.excerpt,
      p.content,
      p.status,
      p.published_at,
      p.updated_at,
      p.created_at,
      p.author,
      p.image_url,
      p.image_alt,
      p.seo_title,
      p.seo_description,
      p.cta_label,
      p.cta_url,
      p.reading_time_minutes,
      p.related_slugs,
      c.id AS category_id,
      c.name AS category_name
    FROM blog_posts p
    LEFT JOIN blog_categories c ON c.id = p.category_id
    ${where.length ? `WHERE ${where.join(' AND ')}` : ''}
    ORDER BY COALESCE(p.published_at, p.updated_at) DESC
  `;

  const rows = await query(sql, params);
  return rows.map(mapPostRow);
}

async function getPostBySlug(slug, { status = 'published' } = {}) {
  const where = ['p.slug = ?'];
  const params = [slug];
  if (status !== 'all') {
    where.push('p.status = ?');
    params.push(status);
  }

  const rows = await query(
    `
    SELECT
      p.id,
      p.title,
      p.slug,
      p.excerpt,
      p.content,
      p.status,
      p.published_at,
      p.updated_at,
      p.created_at,
      p.author,
      p.image_url,
      p.image_alt,
      p.seo_title,
      p.seo_description,
      p.cta_label,
      p.cta_url,
      p.reading_time_minutes,
      p.related_slugs,
      c.id AS category_id,
      c.name AS category_name
    FROM blog_posts p
    LEFT JOIN blog_categories c ON c.id = p.category_id
    WHERE ${where.join(' AND ')}
    LIMIT 1
    `,
    params,
  );

  return rows[0] ? mapPostRow(rows[0]) : null;
}

async function insertPost(post) {
  await query(
    `INSERT INTO blog_posts (
      id, title, slug, excerpt, content, category_id, status,
      published_at, updated_at, created_at, author,
      image_url, image_alt, seo_title, seo_description,
      cta_label, cta_url, reading_time_minutes, related_slugs
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      post.id,
      post.title,
      post.slug,
      post.excerpt,
      post.content,
      post.categoryId,
      post.status,
      post.publishedAt,
      post.updatedAt,
      post.createdAt,
      post.author,
      post.image?.url || null,
      post.image?.alt || null,
      post.seo.title,
      post.seo.description,
      post.cta.label,
      post.cta.url,
      post.readingTimeMinutes,
      JSON.stringify(post.relatedSlugs || []),
    ],
  );
}

async function updatePost(id, post) {
  await query(
    `UPDATE blog_posts SET
      title = ?,
      slug = ?,
      excerpt = ?,
      content = ?,
      category_id = ?,
      status = ?,
      published_at = ?,
      updated_at = ?,
      author = ?,
      image_url = ?,
      image_alt = ?,
      seo_title = ?,
      seo_description = ?,
      cta_label = ?,
      cta_url = ?,
      reading_time_minutes = ?,
      related_slugs = ?
    WHERE id = ?`,
    [
      post.title,
      post.slug,
      post.excerpt,
      post.content,
      post.categoryId,
      post.status,
      post.publishedAt,
      post.updatedAt,
      post.author,
      post.image?.url || null,
      post.image?.alt || null,
      post.seo.title,
      post.seo.description,
      post.cta.label,
      post.cta.url,
      post.readingTimeMinutes,
      JSON.stringify(post.relatedSlugs || []),
      id,
    ],
  );
}

async function deletePost(id) {
  const result = await query('DELETE FROM blog_posts WHERE id = ?', [id]);
  return result.affectedRows > 0;
}

async function slugExists(slug, exceptId = null) {
  const rows = exceptId
    ? await query('SELECT id FROM blog_posts WHERE slug = ? AND id <> ? LIMIT 1', [slug, exceptId])
    : await query('SELECT id FROM blog_posts WHERE slug = ? LIMIT 1', [slug]);
  return rows.length > 0;
}

async function createCategory(category) {
  await query(
    'INSERT INTO blog_categories (id, name, description, sort_order) VALUES (?, ?, ?, ?)',
    [category.id, category.name, category.description, category.order],
  );
}

async function updateCategory(id, category) {
  await query(
    'UPDATE blog_categories SET name = ?, description = ?, sort_order = ? WHERE id = ?',
    [category.name, category.description, category.order, id],
  );
}

async function deleteCategory(id) {
  const result = await query('DELETE FROM blog_categories WHERE id = ?', [id]);
  return result.affectedRows > 0;
}

async function categoryInUse(id) {
  const rows = await query('SELECT id FROM blog_posts WHERE category_id = ? LIMIT 1', [id]);
  return rows.length > 0;
}

async function categoryExists(id) {
  const rows = await query('SELECT id FROM blog_categories WHERE id = ? LIMIT 1', [id]);
  return rows.length > 0;
}

function verifyAdmin(req) {
  const auth = String(req.headers.authorization || '');
  const token = auth.startsWith('Bearer ') ? auth.slice(7) : '';
  const expected = process.env.BLOG_ADMIN_TOKEN;
  return Boolean(expected) && token === expected;
}

module.exports = {
  DEFAULT_CATEGORY_ID,
  normalizeCategory,
  normalizePost,
  slugify,
  verifyAdmin,
  fetchCategories,
  fetchCategoriesMap,
  listPosts,
  getPostBySlug,
  insertPost,
  updatePost,
  deletePost,
  slugExists,
  createCategory,
  updateCategory,
  deleteCategory,
  categoryInUse,
  categoryExists,
};
