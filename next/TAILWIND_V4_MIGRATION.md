# Migration Tailwind CSS v3.4.17 → v4.0.0

## Changements effectués

### 1. ✅ Mise à jour des dépendances (`package.json`)

- `tailwindcss`: `^3.4.17` → `^4.0.0`
- `@tailwindcss/postcss`: ajout de `^4.1.14` (nouveau package requis)
- `@tailwindcss/typography`: reste en `^0.5.15` (pas encore de version v4, chargé via tailwind.config.ts)

### 2. ✅ Migration de la configuration vers CSS (`globals.css`)

- Remplacement de `@tailwind base/components/utilities` par `@import "tailwindcss"`
- Ajout du bloc `@theme` avec :
  - Définition des font families (inter, overpass)
  - Définition des border radius customisés
  - Déclaration des couleurs personnalisées
  - Migration des animations accordion (keyframes + variables)

### 3. ✅ Adaptation PostCSS (`postcss.config.js`)

- Suppression de `"tailwindcss/nesting"` (géré nativement en v4)
- Remplacement de `tailwindcss` par `@tailwindcss/postcss` (nouveau package en v4)
- Suppression de `autoprefixer` (géré automatiquement par Tailwind v4)

### 4. ✅ Migration de `tailwind.config.js` vers `tailwind.config.ts`

- Suppression de l'ancien `tailwind.config.js`
- Création d'un nouveau `tailwind.config.ts` minimal pour charger les plugins (notamment `@tailwindcss/typography` qui n'a pas encore de version v4)
- La configuration du thème reste dans `globals.css` via `@theme`

### 5. ✅ Remplacement des classes shadow

Tailwind v4 a renommé les classes shadow :

| Ancien (v3) | Nouveau (v4) | Fichiers mis à jour                                                                                      |
| ----------- | ------------ | -------------------------------------------------------------------------------------------------------- |
| `shadow-sm` | `shadow-xs`  | activity-teaser, articles-list-item, card                                                                |
| `shadow-md` | `shadow-sm`  | gallery-image-dialog, paragraph--file-attachments, contact-form, article-teaser, dropdown-menu (Content) |
| `shadow-lg` | `shadow-md`  | sonner, dialog, dropdown-menu (SubContent)                                                               |

**Fichiers modifiés :**

- `src/components/ui/sonner.tsx`
- `src/components/ui/dialog.tsx`
- `src/components/ui/dropdown-menu.tsx` (2 occurrences)
- `src/components/ui/card.tsx`
- `src/components/activity/activity-teaser.tsx`
- `src/components/media/gallery-image-dialog.tsx`
- `src/components/paragraph/paragraph--file-attachments.tsx`
- `src/components/forms/contact-form.tsx`
- `src/components/article/article-teaser.tsx`
- `src/app/[locale]/(static)/all-articles/_components/articles-list-item.tsx`

## Prochaines étapes

### 📦 Installation des dépendances

```bash
cd next
ddev exec -s node npm install
```

### 🔨 Test du build

```bash
cd next
ddev exec -s node npm run build
```

### 🧪 Test en développement

```bash
cd next
ddev exec -s node npm run dev
```

## Points d'attention

### ⚠️ Vérifications nécessaires

1. **Storybook** : Vérifier que Storybook fonctionne correctement avec Tailwind v4

   ```bash
   npm run storybook
   ```

2. **Plugin tailwindcss-animate** : Vérifier la compatibilité
   - Les animations accordion ont été migrées manuellement dans `@theme`
   - Le plugin pourrait nécessiter une mise à jour

3. **Tests Cypress** : S'assurer que les tests passent toujours

   ```bash
   npm run cypress:run
   ```

4. **Styles personnalisés** : Vérifier visuellement que :
   - Les couleurs (theme tokens HSL) sont correctes
   - Les polices (inter, overpass) sont appliquées
   - Les animations accordion fonctionnent
   - Les ombres (shadow-xs, shadow-sm, shadow-md) sont visuelles

### 🎨 Nouvelles fonctionnalités Tailwind v4

- **10x plus rapide** grâce au moteur Oxide (Rust)
- **Nesting CSS natif** : Déjà utilisé dans `.hyperlink` (globals.css)
- **Lightning CSS** : Remplacement de PostCSS
- **Configuration CSS** : Plus besoin de JavaScript pour la config

### 🔄 Rollback si nécessaire

Si la migration pose problème :

1. Restaurer `package.json` (v3 dependencies)
2. Restaurer `tailwind.config.js` depuis le commit précédent
3. Restaurer `globals.css` (directives @tailwind)
4. Restaurer `postcss.config.js` (avec nesting)
5. Exécuter `npm install`

## Références

- [Tailwind CSS v4 Upgrade Guide](https://tailwindcss.com/docs/upgrade-guide)
- [Tailwind CSS v4 Changelog](https://tailwindcss.com/blog/tailwindcss-v4)
- [Lightning CSS Documentation](https://lightningcss.dev/)
