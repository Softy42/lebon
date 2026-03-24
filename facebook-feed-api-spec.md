# Spécification backend — `/api/facebook-feed`

## 1) Objectif
Mettre en place une API backend stable pour alimenter la page `/actualites` sans exposer de secrets Facebook côté navigateur.

- **Frontend consommateur actuel** : `GET /api/facebook-feed?limit=4`
- **Contrainte** : garder un rendu robuste même si Facebook Graph API est indisponible.

---

## 2) Architecture recommandée

### Composants
1. **Site statique** (frontend) qui appelle l’API interne.
2. **Endpoint serverless** `/api/facebook-feed` (Vercel recommandé).
3. **Facebook Graph API** appelée **uniquement côté serveur**.
4. **Cache serveur** (mémoire/kv selon infra).

### Flux
`Browser -> /api/facebook-feed -> Graph API -> réponse normalisée`

---

## 3) Contrat HTTP

## Route
- **Méthode** : `GET`
- **Path** : `/api/facebook-feed`

## Query params
- `limit` (optionnel, entier)
  - défaut : `4`
  - minimum : `1`
  - maximum : `6`
  - comportement hors bornes : clamp entre 1 et 6.

## Headers réponse recommandés
- `Content-Type: application/json; charset=utf-8`
- `Cache-Control: public, max-age=300, stale-while-revalidate=1800`

---

## 4) Schéma de réponse JSON

## Succès nominal (200)
```json
{
  "ok": true,
  "generatedAt": "2026-02-17T10:30:00.000Z",
  "cache": {
    "hit": true,
    "ageSeconds": 120,
    "ttlSeconds": 1800,
    "stale": false
  },
  "items": [
    {
      "id": "123_456",
      "message": "Texte publication",
      "publishedAt": "2026-02-16T08:00:00.000Z",
      "permalink": "https://www.facebook.com/...",
      "image": "https://...jpg"
    }
  ]
}
```

## Succès dégradé (200) — Facebook indisponible mais fallback servi
```json
{
  "ok": true,
  "degraded": true,
  "reason": "UPSTREAM_TIMEOUT",
  "generatedAt": "2026-02-17T10:30:00.000Z",
  "cache": {
    "hit": true,
    "ageSeconds": 6500,
    "ttlSeconds": 1800,
    "stale": true
  },
  "items": [
    {
      "id": "123_456",
      "message": "Dernière publication en cache",
      "publishedAt": "2026-02-16T08:00:00.000Z",
      "permalink": "https://www.facebook.com/...",
      "image": null
    }
  ]
}
```

## Erreur technique (5xx)
```json
{
  "ok": false,
  "error": {
    "code": "UPSTREAM_UNAVAILABLE",
    "message": "Le flux Facebook est temporairement indisponible.",
    "retryable": true
  }
}
```

### Codes d’erreur internes recommandés
- `BAD_REQUEST`
- `UPSTREAM_TIMEOUT`
- `UPSTREAM_UNAVAILABLE`
- `TOKEN_INVALID`
- `NO_DATA`

---

## 5) Mapping Graph API -> Format frontend

## Champs Graph à demander
- `id`
- `message`
- `created_time`
- `permalink_url`
- `full_picture`

## Mapping normalisé
- `id` <- `id`
- `message` <- `message` (chaîne vide si absent)
- `publishedAt` <- `created_time`
- `permalink` <- `permalink_url`
- `image` <- `full_picture` (ou `null`)

## Règles de nettoyage
- Tronquer `message` côté backend à ~500 caractères max (sécurité/robustesse).
- Échapper/sanitiser avant rendu HTML côté front (défense en profondeur).
- Exclure les items sans `permalink`.

---

## 6) Cache et résilience

## Politique recommandée
- **TTL principal** : 30 minutes (`1800s`).
- **Stale max** : 24h (`86400s`) en cas de panne Facebook.
- **Timeout upstream** : 3 secondes.
- **Retry** : 1 tentative max sur erreur réseau transitoire.

## Stratégie
1. Lire le cache valide -> répondre immédiatement.
2. Si cache expiré -> tenter refresh Facebook.
3. Si refresh échoue -> servir cache stale (si age <= 24h) avec `degraded: true`.
4. Si aucun cache -> retourner erreur 503 contrôlée.

---

## 7) Sécurité

- Stocker les secrets uniquement en variables d’environnement.
- **Jamais** de token Facebook en frontend, logs, ou payload JSON.
- Vérifier et borner `limit` pour éviter les abus.
- Ajouter un rate limit basique (IP/User-Agent) sur l’endpoint public.
- Ne jamais retourner de stacktrace interne au client.

### Variables d’environnement minimales
- `FB_PAGE_ID`
- `FB_PAGE_ACCESS_TOKEN`
- `FB_GRAPH_VERSION` (ex: `v22.0`)

---

## 8) Observabilité (logs + métriques)

## Logs structurés (chaque requête)
- `requestId`
- `status`
- `durationMs`
- `cacheHit`
- `cacheStale`
- `itemsCount`
- `errorCode` (si erreur)

## Métriques à suivre
- Taux d’erreur `/api/facebook-feed`
- Latence p95
- Ratio cache hit
- Ratio réponses dégradées

## Seuils d’alerte
- Erreurs > 5% sur 15 min
- p95 > 2000ms sur 15 min

---

## 9) Plan de test avant production

1. **Nominal** : API renvoie 4 items valides.
2. **Limit clamp** : `limit=999` renvoie max 6 items.
3. **Payload incomplet** : item sans image -> `image: null`.
4. **Timeout Facebook** : réponse 200 dégradée avec stale si disponible.
5. **Token invalide** : réponse erreur contrôlée, sans fuite de secrets.
6. **Aucun cache + Facebook KO** : 503 contrôlée.

---

## 10) Définition de “Done”

- La page `/actualites` affiche des posts réels via l’endpoint backend.
- Le fallback UI actuel s’active proprement si backend en erreur.
- Cache 30 min + stale 24h opérationnels.
- Aucune fuite de token dans le navigateur.
- Logs exploitables pour diagnostiquer un incident.
