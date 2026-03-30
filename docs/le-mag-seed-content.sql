-- Seed initial content for Le Mag Maison Mélina
-- Compatible with schema in docs/le-mag-schema.sql

START TRANSACTION;

-- 1) Testimonials (published + consent)
INSERT INTO blog_testimonials (
  quote_text,
  person_name,
  person_role,
  area_label,
  status,
  consent_publication,
  published_at,
  created_at,
  updated_at
)
VALUES
(
  'Nous avons trouvé une solution humaine et rassurante pour notre maman. L''ambiance de la maison est chaleureuse et elle se sent entourée.',
  'Claire',
  'Fille d''une résidente',
  'Loire',
  'published',
  1,
  NOW(),
  NOW(),
  NOW()
),
(
  'Avant, mon père était souvent seul. Aujourd''hui, il a retrouvé un rythme de vie et du lien social au quotidien.',
  'Marc',
  'Proche aidant',
  'Haute-Loire',
  'published',
  1,
  NOW(),
  NOW(),
  NOW()
),
(
  'Nous avons pu visiter, poser nos questions et avancer sereinement. L''équipe est disponible et bienveillante.',
  'Anonyme',
  'Famille',
  'Loire',
  'published',
  1,
  NOW(),
  NOW(),
  NOW()
);

-- 2) Articles
INSERT INTO blog_posts (
  title,
  slug,
  excerpt,
  content_html,
  category_id,
  status,
  published_at,
  author_name,
  seo_title,
  seo_description,
  cta_variant,
  created_at,
  updated_at
)
VALUES
(
  'Qu''est-ce qu''une colocation senior ?',
  'quest-ce-quune-colocation-senior',
  'Une colocation senior est une maison à taille humaine où des personnes âgées vivent ensemble, dans un cadre chaleureux, sécurisé et non institutionnel.',
  '<p>Quand un proche vieillit, il est parfois difficile de savoir quelle solution choisir. La colocation senior est une alternative de plus en plus recherchée, car elle combine sécurité, lien social et qualité de vie.</p>\n<h2>Une maison partagée à taille humaine</h2>\n<p>Une colocation senior accueille plusieurs résidents dans une maison adaptée. Chaque personne dispose d''un espace privé, tout en partageant des lieux de vie communs comme la cuisine, le salon ou le jardin.</p>\n<h2>Pour qui est-ce adapté ?</h2>\n<p>La colocation senior s''adresse aux personnes âgées autonomes ou semi-autonomes qui souhaitent rester entourées, tout en conservant leur liberté au quotidien.</p>\n<h2>Les avantages concrets</h2>\n<ul><li>Rompre l''isolement</li><li>Vivre dans un cadre convivial</li><li>Conserver ses habitudes de vie</li><li>Être rassuré grâce à un accompagnement adapté</li></ul>\n<h2>Une alternative à l''EHPAD dans certains cas</h2>\n<p>La colocation senior n''est pas un établissement médicalisé. C''est une solution intermédiaire rassurante, qui convient à de nombreuses familles avant une perte d''autonomie plus importante.</p>\n<p>Vous vous posez des questions pour un proche ? Notre équipe peut vous aider à y voir clair, simplement et sans engagement.</p>',
  'colocation-senior',
  'published',
  NOW(),
  'Thierry',
  'Qu''est-ce qu''une colocation senior ? Définition simple et concrète',
  'Comprenez simplement le fonctionnement d''une colocation senior, ses avantages et les profils concernés pour mieux accompagner un proche.',
  'contact',
  NOW(),
  NOW()
),
(
  'Colocation senior ou EHPAD : quelles différences ?',
  'colocation-senior-ou-ehpad-quelles-differences',
  'Entre colocation senior et EHPAD, les besoins ne sont pas les mêmes. Voici un comparatif clair pour choisir la solution la plus adaptée à votre proche.',
  '<p>Quand la question de l''hébergement se pose, de nombreuses familles hésitent entre colocation senior et EHPAD. Ces deux solutions répondent à des besoins différents.</p>\n<h2>Le niveau d''accompagnement</h2>\n<p>L''EHPAD est une structure médicalisée pour des personnes ayant besoin d''un suivi médical important. La colocation senior, elle, convient davantage aux personnes autonomes ou semi-autonomes.</p>\n<h2>Le cadre de vie</h2>\n<p>En colocation senior, la vie se déroule dans une maison conviviale, avec une ambiance plus familiale. En EHPAD, l''organisation est plus institutionnelle.</p>\n<h2>L''autonomie au quotidien</h2>\n<p>La colocation senior favorise l''autonomie et les habitudes de vie : rythme personnel, liberté de mouvement, moments de partage choisis.</p>\n<h2>Le lien social</h2>\n<p>Dans les deux cas, le lien social est important. La différence est souvent dans le format : plus intime et chaleureux en maison partagée.</p>\n<h2>Comment choisir ?</h2>\n<p>Le bon choix dépend avant tout de l''état de santé, du niveau d''autonomie et du projet de vie de votre proche.</p>\n<p>Le plus simple est de visiter, poser vos questions et comparer concrètement les solutions.</p>',
  'conseils-aux-familles',
  'published',
  NOW(),
  'Christine',
  'Colocation senior ou EHPAD : le comparatif clair pour les familles',
  'Découvrez les différences entre colocation senior et EHPAD : cadre de vie, accompagnement, autonomie et critères de choix pour votre proche.',
  'visit',
  NOW(),
  NOW()
),
(
  'Comment lutter contre l''isolement des personnes âgées ?',
  'comment-lutter-contre-lisolement-des-personnes-agees',
  'L''isolement des personnes âgées peut s''installer progressivement. Voici des actions simples et concrètes pour préserver le lien social et le bien-vieillir.',
  '<p>L''isolement touche de nombreux seniors, souvent de manière progressive. Il peut avoir un impact sur le moral, la santé et la motivation au quotidien.</p>\n<h2>Repérer les premiers signes</h2>\n<ul><li>Moins de sorties</li><li>Repli sur soi</li><li>Baisse d''envie de participer aux activités</li><li>Discours négatif ou perte d''élan</li></ul>\n<h2>Maintenir des routines sociales</h2>\n<p>Des gestes simples peuvent faire la différence : repas partagés, appels réguliers, visites planifiées, participation à des activités de quartier.</p>\n<h2>Créer un environnement relationnel stable</h2>\n<p>Le cadre de vie joue un rôle clé. Vivre dans un lieu où les échanges sont naturels et quotidiens aide à préserver l''équilibre émotionnel.</p>\n<h2>Ne pas attendre l''urgence</h2>\n<p>Plus on agit tôt, plus il est facile de mettre en place une solution adaptée et sereine pour la personne concernée et sa famille.</p>\n<p>Vous souhaitez échanger sur la situation d''un proche ? Nous sommes à votre écoute pour vous orienter avec bienveillance.</p>',
  'bien-vieillir',
  'published',
  NOW(),
  'Thierry',
  'Comment lutter contre l''isolement des personnes âgées ? Conseils concrets',
  'Isolement senior : repérez les signaux et découvrez des solutions concrètes pour préserver le lien social et la qualité de vie au quotidien.',
  'contact',
  NOW(),
  NOW()
)
ON DUPLICATE KEY UPDATE
  title = VALUES(title),
  excerpt = VALUES(excerpt),
  content_html = VALUES(content_html),
  category_id = VALUES(category_id),
  status = VALUES(status),
  author_name = VALUES(author_name),
  seo_title = VALUES(seo_title),
  seo_description = VALUES(seo_description),
  cta_variant = VALUES(cta_variant),
  updated_at = NOW();

-- 3) Link testimonials to articles (replace existing links for these slugs)
DELETE bpt
FROM blog_post_testimonials bpt
JOIN blog_posts bp ON bp.id = bpt.post_id
WHERE bp.slug IN (
  'quest-ce-quune-colocation-senior',
  'colocation-senior-ou-ehpad-quelles-differences',
  'comment-lutter-contre-lisolement-des-personnes-agees'
);

INSERT INTO blog_post_testimonials (post_id, testimonial_id, position)
SELECT bp.id, bt.id, 1
FROM blog_posts bp
JOIN blog_testimonials bt ON bt.person_name = 'Claire'
WHERE bp.slug = 'quest-ce-quune-colocation-senior'
LIMIT 1;

INSERT INTO blog_post_testimonials (post_id, testimonial_id, position)
SELECT bp.id, bt.id, 1
FROM blog_posts bp
JOIN blog_testimonials bt ON bt.person_name = 'Marc'
WHERE bp.slug = 'colocation-senior-ou-ehpad-quelles-differences'
LIMIT 1;

INSERT INTO blog_post_testimonials (post_id, testimonial_id, position)
SELECT bp.id, bt.id, 1
FROM blog_posts bp
JOIN blog_testimonials bt ON bt.person_name = 'Anonyme'
WHERE bp.slug = 'comment-lutter-contre-lisolement-des-personnes-agees'
LIMIT 1;

COMMIT;
