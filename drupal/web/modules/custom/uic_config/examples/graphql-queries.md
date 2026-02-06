# Exemples de requetes GraphQL pour UIC Configuration

Ce fichier contient des exemples de requetes GraphQL pour utiliser les champs personnalises ajoutes par le module UIC Configuration dans le cadre de la migration SPIP vers Drupal 10.

## Types de contenu disponibles

Le module expose trois types de contenu via GraphQL Compose :

| Type Drupal | Query GraphQL | Type GraphQL | Origine SPIP |
|-------------|---------------|-------------|--------------|
| Article | `nodeArticles` | `NodeArticle` | Articles SPIP |
| Activity Page | `nodeActivityPages` | `NodeActivityPage` | Rubriques SPIP |
| Project Page | `nodeProjectPages` | `NodeProjectPage` | Projets SPIP |

Chaque type dispose de champs `fieldSpipId` et `fieldSpipUrl` pour la tracabilite de la migration.

---

## Requetes pour Article

### Recuperer tous les articles avec pagination

```graphql
query GetArticles($first: Int = 10, $after: Cursor) {
  nodeArticles(first: $first, after: $after) {
    nodes {
      id
      title
      path
      created {
        timestamp
      }
      fieldSubtitle
      fieldFeaturedImage {
        ... on MediaImage {
          id
          name
          mediaImage {
            url
            alt
            width
            height
          }
        }
      }
      body {
        processed
        summary
      }
      fieldHeader {
        processed
      }
      fieldFooter {
        processed
      }
      fieldGallery {
        ... on MediaImage {
          id
          name
          mediaImage {
            url
            alt
            width
            height
          }
        }
      }
      fieldAttachments {
        ... on MediaDocument {
          id
          name
          mediaDocument {
            url
          }
        }
      }
      fieldTags {
        id
        name
      }
      fieldSpipId
      fieldSpipUrl
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
```

### Recuperer un article par son ID

```graphql
query GetArticleById($id: ID!) {
  node(id: $id) {
    ... on NodeArticle {
      id
      title
      path
      created {
        timestamp
      }
      changed {
        timestamp
      }
      fieldSubtitle
      fieldFeaturedImage {
        ... on MediaImage {
          mediaImage {
            url
            alt
          }
        }
      }
      body {
        processed
      }
      fieldHeader {
        processed
      }
      fieldFooter {
        processed
      }
      fieldGallery {
        ... on MediaImage {
          mediaImage {
            url
            alt
          }
        }
      }
      fieldAttachments {
        ... on MediaDocument {
          name
          mediaDocument {
            url
          }
        }
      }
      fieldTags {
        name
      }
      fieldSpipId
      fieldSpipUrl
    }
  }
}
```

### Recuperer un article par son chemin

```graphql
query GetArticleByPath($path: String!) {
  route(path: $path) {
    ... on RouteInternal {
      entity {
        ... on NodeArticle {
          id
          title
          fieldSubtitle
          fieldFeaturedImage {
            ... on MediaImage {
              mediaImage {
                url
                alt
              }
            }
          }
          body {
            processed
          }
          fieldHeader {
            processed
          }
          fieldFooter {
            processed
          }
        }
      }
    }
  }
}
```

---

## Requetes pour Activity Page

### Lister les pages d'activite

```graphql
query GetActivityPages($first: Int = 10) {
  nodeActivityPages(first: $first) {
    nodes {
      id
      title
      path
      created {
        timestamp
      }
      fieldSubtitle
      fieldFeaturedImage {
        ... on MediaImage {
          mediaImage {
            url
            alt
          }
        }
      }
      body {
        processed
        summary
      }
      fieldHeader {
        processed
      }
      fieldFooter {
        processed
      }
      fieldGallery {
        ... on MediaImage {
          mediaImage {
            url
            alt
          }
        }
      }
      fieldAttachments {
        ... on MediaDocument {
          name
          mediaDocument {
            url
          }
        }
      }
      fieldSpipId
      fieldSpipUrl
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
```

### Recuperer une page d'activite par ID

```graphql
query GetActivityPageById($id: ID!) {
  node(id: $id) {
    ... on NodeActivityPage {
      id
      title
      path
      fieldSubtitle
      fieldFeaturedImage {
        ... on MediaImage {
          mediaImage {
            url
            alt
            width
            height
          }
        }
      }
      body {
        processed
      }
      fieldHeader {
        processed
      }
      fieldFooter {
        processed
      }
      fieldGallery {
        ... on MediaImage {
          id
          name
          mediaImage {
            url
            alt
          }
        }
      }
      fieldAttachments {
        ... on MediaDocument {
          id
          name
          mediaDocument {
            url
          }
        }
      }
      fieldSpipId
      fieldSpipUrl
    }
  }
}
```

---

## Requetes pour Project Page

### Lister les pages de projet

```graphql
query GetProjectPages($first: Int = 10) {
  nodeProjectPages(first: $first) {
    nodes {
      id
      title
      path
      created {
        timestamp
      }
      fieldSubtitle
      fieldFeaturedImage {
        ... on MediaImage {
          mediaImage {
            url
            alt
          }
        }
      }
      body {
        processed
      }
      fieldHeader {
        processed
      }
      fieldFooter {
        processed
      }
      fieldImage {
        url
        alt
        width
        height
      }
      fieldStartEnd {
        value
        end {
          value
        }
      }
      fieldTags {
        id
        name
      }
      fieldSpipId
      fieldSpipUrl
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
```

### Recuperer une page de projet par ID

```graphql
query GetProjectPageById($id: ID!) {
  node(id: $id) {
    ... on NodeProjectPage {
      id
      title
      path
      fieldSubtitle
      fieldFeaturedImage {
        ... on MediaImage {
          mediaImage {
            url
            alt
          }
        }
      }
      body {
        processed
      }
      fieldHeader {
        processed
      }
      fieldFooter {
        processed
      }
      fieldImage {
        url
        alt
      }
      fieldStartEnd {
        value
        end {
          value
        }
      }
      fieldTags {
        name
      }
      fieldSpipId
      fieldSpipUrl
    }
  }
}
```

---

## Requetes combinees

### Recuperer tous les types de contenu

```graphql
query GetAllContent($articlesFirst: Int = 5, $activitiesFirst: Int = 5, $projectsFirst: Int = 5) {
  articles: nodeArticles(first: $articlesFirst) {
    nodes {
      id
      title
      fieldSubtitle
      fieldFeaturedImage {
        ... on MediaImage {
          mediaImage {
            url
            alt
          }
        }
      }
      created {
        timestamp
      }
    }
  }
  
  activities: nodeActivityPages(first: $activitiesFirst) {
    nodes {
      id
      title
      fieldSubtitle
      fieldFeaturedImage {
        ... on MediaImage {
          mediaImage {
            url
            alt
          }
        }
      }
      created {
        timestamp
      }
    }
  }
  
  projects: nodeProjectPages(first: $projectsFirst) {
    nodes {
      id
      title
      fieldSubtitle
      fieldFeaturedImage {
        ... on MediaImage {
          mediaImage {
            url
            alt
          }
        }
      }
      fieldStartEnd {
        value
      }
    }
  }
}
```

---

## Fragments reutilisables

### Fragment pour les champs communs

```graphql
fragment CommonFields on NodeInterface {
  id
  title
  path
  created {
    timestamp
  }
  changed {
    timestamp
  }
}

fragment MediaImageFields on MediaImage {
  id
  name
  mediaImage {
    url
    alt
    width
    height
  }
}

fragment SpipFields on NodeInterface {
  ... on NodeArticle {
    fieldSpipId
    fieldSpipUrl
  }
  ... on NodeActivityPage {
    fieldSpipId
    fieldSpipUrl
  }
  ... on NodeProjectPage {
    fieldSpipId
    fieldSpipUrl
  }
}
```

### Utilisation des fragments

```graphql
query GetArticlesWithFragments($first: Int = 10) {
  nodeArticles(first: $first) {
    nodes {
      ...CommonFields
      fieldSubtitle
      fieldFeaturedImage {
        ...MediaImageFields
      }
      body {
        processed
      }
    }
  }
}

fragment CommonFields on NodeInterface {
  id
  title
  path
  created {
    timestamp
  }
}

fragment MediaImageFields on MediaImage {
  id
  name
  mediaImage {
    url
    alt
    width
    height
  }
}
```

---

## Variables d'exemple

### Pour GetArticles

```json
{
  "first": 10,
  "after": null
}
```

### Pour GetArticleById

```json
{
  "id": "node:1"
}
```

### Pour GetArticleByPath

```json
{
  "path": "/articles/mon-article"
}
```

### Pour GetAllContent

```json
{
  "articlesFirst": 5,
  "activitiesFirst": 5,
  "projectsFirst": 5
}
```

---

## Utilisation dans Next.js

### Configuration du client GraphQL

```typescript
// lib/graphql-client.ts
import { GraphQLClient } from 'graphql-request';

const endpoint = process.env.NEXT_PUBLIC_DRUPAL_GRAPHQL_URL || 'http://localhost/graphql';

export const graphqlClient = new GraphQLClient(endpoint, {
  headers: {
    // Ajouter des headers si necessaire
  },
});
```

### Hook personnalise pour les articles

```typescript
// hooks/useArticles.ts
import useSWR from 'swr';
import { graphqlClient } from '@/lib/graphql-client';
import { gql } from 'graphql-request';

const GET_ARTICLES = gql`
  query GetArticles($first: Int = 10) {
    nodeArticles(first: $first) {
      nodes {
        id
        title
        fieldSubtitle
        fieldFeaturedImage {
          ... on MediaImage {
            mediaImage {
              url
              alt
            }
          }
        }
        body {
          summary
        }
        path
        created {
          timestamp
        }
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
`;

export function useArticles(first = 10) {
  return useSWR(['articles', first], () =>
    graphqlClient.request(GET_ARTICLES, { first })
  );
}
```

### Composant Article

```typescript
// components/ArticleCard.tsx
import Image from 'next/image';
import Link from 'next/link';

interface ArticleCardProps {
  article: {
    id: string;
    title: string;
    fieldSubtitle?: string;
    fieldFeaturedImage?: {
      mediaImage: {
        url: string;
        alt: string;
      };
    };
    body?: {
      summary?: string;
    };
    path: string;
  };
}

export function ArticleCard({ article }: ArticleCardProps) {
  return (
    <article className="border rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow">
      {article.fieldFeaturedImage && (
        <div className="relative h-48">
          <Image
            src={article.fieldFeaturedImage.mediaImage.url}
            alt={article.fieldFeaturedImage.mediaImage.alt || article.title}
            fill
            className="object-cover"
          />
        </div>
      )}
      <div className="p-4">
        <h2 className="text-xl font-semibold mb-2">
          <Link href={article.path} className="hover:text-blue-600">
            {article.title}
          </Link>
        </h2>
        {article.fieldSubtitle && (
          <p className="text-gray-600 mb-2">{article.fieldSubtitle}</p>
        )}
        {article.body?.summary && (
          <p className="text-gray-700 line-clamp-3">{article.body.summary}</p>
        )}
      </div>
    </article>
  );
}
```

---

## Requetes utiles pour la migration SPIP

### Lister tous les contenus migres avec leurs IDs SPIP

```graphql
query GetAllMigratedContent {
  articles: nodeArticles(first: 100) {
    nodes {
      id
      title
      path
      fieldSpipId
      fieldSpipUrl
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }

  activities: nodeActivityPages(first: 100) {
    nodes {
      id
      title
      path
      fieldSpipId
      fieldSpipUrl
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }

  projects: nodeProjectPages(first: 100) {
    nodes {
      id
      title
      path
      fieldSpipId
      fieldSpipUrl
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
```

### Fragment pour les champs de tracabilite SPIP

```graphql
fragment SpipTraceability on NodeInterface {
  ... on NodeArticle {
    fieldSpipId
    fieldSpipUrl
  }
  ... on NodeActivityPage {
    fieldSpipId
    fieldSpipUrl
  }
  ... on NodeProjectPage {
    fieldSpipId
    fieldSpipUrl
  }
}
```

Ce fragment est utile pour ajouter rapidement la tracabilite SPIP a n'importe quelle requete.

---

## Notes importantes

1. **Noms des champs** : Les champs sont exposes en camelCase (ex: `fieldSubtitle` au lieu de `field_subtitle`)

2. **Types GraphQL** :
   - `NodeArticle` pour Article
   - `NodeActivityPage` pour Activity Page
   - `NodeProjectPage` pour Project Page

3. **Relations Media** : Les champs media retournent des unions, utilisez des fragments inline (`... on MediaImage`)

4. **Champs de texte formate** : Utilisez `.processed` pour le HTML rendu, `.value` pour le texte brut

5. **Pagination** : GraphQL Compose utilise la pagination Relay (`first`, `after`, `pageInfo`)

6. **IDs** : Les IDs GraphQL Compose sont au format `node:123` ou juste le numero selon la configuration

7. **Dates** : Les champs date retournent un objet avec `timestamp` (Unix timestamp)
