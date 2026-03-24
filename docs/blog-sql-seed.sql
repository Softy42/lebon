INSERT INTO blog_categories (id, name, description, sort_order)
VALUES
  ('colocation-senior', 'Colocation senior', 'Comprendre le modèle de maison partagée et ses bénéfices.', 1),
  ('bien-vieillir', 'Bien vieillir', 'Préserver l\'autonomie, la santé sociale et l\'équilibre au quotidien.', 2),
  ('conseils-aux-familles', 'Conseils aux familles', 'Repères concrets pour accompagner un proche avec sérénité.', 3),
  ('vie-en-maison-partagee', 'Vie en maison partagée', 'La vie quotidienne dans les maisons Maison Mélina.', 4),
  ('actualites-maison-melina', 'Actualités Maison Mélina', 'Ouvertures, événements, temps forts et nouveautés du réseau.', 5)
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), sort_order=VALUES(sort_order);
