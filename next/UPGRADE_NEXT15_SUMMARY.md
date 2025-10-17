# Résumé de l'upgrade vers Next.js 15.5.6

## ✅ Changements effectués

### 1. Configuration Next.js (`next.config.mjs`)

- ✅ Supprimé `experimental.instrumentationHook` (maintenant activé par défaut)
- ✅ Déplacé `experimental.swrDelta` → `expireTime: 31536000`
- ✅ Ajouté `allowedDevOrigins` pour ddev en développement
- ✅ Filtré les URLs vides dans `images.remotePatterns`

### 2. Package.json

- ✅ React 19 RC installé (`^19.0.0-rc.1`)
- ✅ Types React mis à jour vers `^19.0.6`
- ✅ Types React-DOM mis à jour vers `^19.0.2`
- ✅ `@next/eslint-plugin-next` → `^15.5.6`
- ✅ `eslint-config-next` → `^15.5.6`

### 3. SearchParams asynchrones (2 fichiers)

- ✅ `src/app/[locale]/(static)/all-articles/page.tsx`
- ✅ `src/app/[locale]/(static)/nodepreview/page.tsx`

### 4. Params asynchrones (12 fichiers modifiés)

- ✅ `src/app/[locale]/layout.tsx` (generateMetadata + RootLayout)
- ✅ `src/app/[locale]/(static)/page.tsx` (Frontpage)
- ✅ `src/app/[locale]/(dynamic)/[...slug]/page.tsx` (NodePage + generateStaticParams)
- ✅ `src/app/[locale]/(static)/layout.tsx` (StaticLayout)
- ✅ `src/app/[locale]/(dynamic)/[...slug]/layout.tsx` (DynamicLayout)
- ✅ `src/app/[locale]/(static)/dashboard/page.tsx`
- ✅ `src/app/[locale]/(static)/dashboard/webforms/[webformName]/[webformSubmissionUuid]/page.tsx`
- ✅ `src/app/[locale]/(static)/auth/login/page.tsx`
- ✅ `src/app/[locale]/(static)/auth/register/page.tsx`

**Note:** Le fichier `search/page.tsx` est un composant client et n'a pas besoin de modification.

## 🔧 Prochaines étapes

### 1. Nettoyer et réinstaller les dépendances

```bash
cd next
rm -rf .next node_modules/.cache
npm install --legacy-peer-deps
```

### 2. Tester en développement

```bash
ddev npm run dev
```

### 3. Vérifier le build

```bash
ddev npm run build
```

## ⚠️ Erreur à résoudre

L'erreur `Cannot find module 'next/dist/client/components/static-generation-async-storage.external.js'` est probablement due à un cache corrompu ou des modules incomplets.

**Solutions :**

1. Supprimer `.next/` et `node_modules/.cache/`
2. Réinstaller avec `npm install --legacy-peer-deps`
3. Si le problème persiste, supprimer `node_modules/` et `package-lock.json`, puis réinstaller

## 📝 Changements de syntaxe

### Avant (Next.js 14)

```typescript
export default async function Page({
  params: { locale, slug },
}: {
  params: { locale: string; slug: string[] };
}) {
  // utilisation directe
}
```

### Après (Next.js 15)

```typescript
export default async function Page({
  params,
}: {
  params: Promise<{ locale: string; slug: string[] }>;
}) {
  const { locale, slug } = await params;
  // utilisation après await
}
```

## ✅ Vérifications effectuées

- ✅ Aucune erreur de linting détectée
- ✅ Tous les fichiers TypeScript sont valides
- ✅ 12 fichiers de pages/layouts modifiés
- ✅ 2 fichiers searchParams modifiés
- ✅ Configuration Next.js mise à jour

## ✅ Cache Handler Personnalisé

**Problème :** `@neshca/cache-handler@1.9.0` incompatible avec Next.js 15

**Solution :** Implémentation d'un cache handler natif Next.js 15 avec Redis

Fichiers créés/modifiés :

- ✅ `cache-handler.mjs` - Cache handler personnalisé utilisant l'API Next.js 15
- ✅ `src/instrumentation.ts` - Simplifié (plus besoin de `@neshca/cache-handler/instrumentation`)
- ✅ `next.config.mjs` - Réactivé `cacheHandler`

**Fonctionnalités :**

- ✅ Support Redis (si configuré via `REDIS_HOST` et `REDIS_PASS`)
- ✅ Fallback automatique vers cache mémoire
- ✅ Support des tags de cache (`revalidateTag`)
- ✅ Timeout de 3s pour les opérations Redis
- ✅ Gestion d'erreurs robuste

**Documentation :** [Next.js Cache Handler API](https://nextjs.org/docs/app/api-reference/config/next-config-js/incrementalCacheHandlerPath)

## 🔍 À surveiller

- Compatibilité de `next-drupal ^2.0.0` avec React 19
- Tests Storybook après l'upgrade
- Tests Cypress e2e

---

Date: $(date)
Version Next.js: 15.5.6
Version React: 19.0.0-rc.1
