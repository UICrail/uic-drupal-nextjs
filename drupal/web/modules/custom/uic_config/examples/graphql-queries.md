# Exemples de requêtes GraphQL pour UIC Configuration

Ce fichier contient des exemples de requêtes GraphQL pour utiliser les champs personnalisés ajoutés par le module UIC Configuration.

## Requêtes pour Article

### Récupérer un article avec tous les champs personnalisés

```graphql
query GetArticle($id: ID!) {
  node(id: $id) {
    ... on Article {
      id
      title
      body {
        processed
      }
      fieldSubtitle
      fieldHeader {
        processed
      }
      fieldFooter {
        processed
      }
      fieldGallery {
        entities {
          ... on MediaImage {
            id
            name
            fieldMediaImage {
              url
              alt
              width
              height
            }
          }
        }
      }
      fieldAttachments {
        entities {
          ... on MediaDocument {
            id
            name
            fieldMediaDocument {
              url
              filename
              filesize
            }
          }
        }
      }
      fieldSpipId
      fieldSpipUrl
      created {
        timestamp
      }
      changed {
        timestamp
      }
    }
  }
}
```

### Lister les articles avec champs personnalisés

```graphql
query ListArticles($limit: Int = 10, $offset: Int = 0) {
  nodeQuery(limit: $limit, offset: $offset, filter: { conditions: [{ field: "type", value: "article" }] }) {
    entities {
      ... on Article {
        id
        title
        fieldSubtitle
        fieldSpipId
        fieldSpipUrl
        fieldImage {
          url
          alt
        }
        created {
          timestamp
        }
      }
    }
    count
  }
}
```

### Rechercher des articles par ID SPIP

```graphql
query SearchArticlesBySpipId($spipId: String!) {
  nodeQuery(
    filter: { 
      conditions: [
        { field: "type", value: "article" },
        { field: "field_spip_id", value: $spipId }
      ] 
    }
  ) {
    entities {
      ... on Article {
        id
        title
        fieldSubtitle
        fieldSpipId
        fieldSpipUrl
      }
    }
  }
}
```

## Requêtes pour Project Page

### Récupérer une page de projet complète

```graphql
query GetProjectPage($id: ID!) {
  node(id: $id) {
    ... on ProjectPage {
      id
      title
      body {
        processed
      }
      fieldSubtitle
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
        endValue
      }
      fieldSpipId
      fieldSpipUrl
      fieldTags {
        entities {
          ... on TaxonomyTermTags {
            id
            name
          }
        }
      }
      created {
        timestamp
      }
      changed {
        timestamp
      }
    }
  }
}
```

### Lister les pages de projet

```graphql
query ListProjectPages($limit: Int = 10, $offset: Int = 0) {
  nodeQuery(limit: $limit, offset: $offset, filter: { conditions: [{ field: "type", value: "project_page" }] }) {
    entities {
      ... on ProjectPage {
        id
        title
        fieldSubtitle
        fieldImage {
          url
          alt
        }
        fieldStartEnd {
          value
          endValue
        }
        fieldTags {
          entities {
            ... on TaxonomyTermTags {
              name
            }
          }
        }
        fieldSpipId
        fieldSpipUrl
      }
    }
    count
  }
}
```

### Filtrer les projets par tags

```graphql
query FilterProjectsByTags($tagIds: [ID!]!) {
  nodeQuery(
    filter: { 
      conditions: [
        { field: "type", value: "project_page" },
        { field: "field_tags", value: $tagIds, operator: IN }
      ] 
    }
  ) {
    entities {
      ... on ProjectPage {
        id
        title
        fieldSubtitle
        fieldImage {
          url
          alt
        }
        fieldTags {
          entities {
            ... on TaxonomyTermTags {
              id
              name
            }
          }
        }
      }
    }
  }
}
```

## Requêtes combinées

### Récupérer articles et projets avec pagination

```graphql
query GetContentWithPagination($limit: Int = 10, $offset: Int = 0) {
  articles: nodeQuery(
    limit: $limit, 
    offset: $offset, 
    filter: { conditions: [{ field: "type", value: "article" }] }
  ) {
    entities {
      ... on Article {
        id
        title
        fieldSubtitle
        fieldSpipId
        created {
          timestamp
        }
      }
    }
    count
  }
  
  projects: nodeQuery(
    limit: $limit, 
    offset: $offset, 
    filter: { conditions: [{ field: "type", value: "project_page" }] }
  ) {
    entities {
      ... on ProjectPage {
        id
        title
        fieldSubtitle
        fieldSpipId
        fieldStartEnd {
          value
        }
      }
    }
    count
  }
}
```

## Variables d'exemple

### Pour GetArticle
```json
{
  "id": "1"
}
```

### Pour ListArticles
```json
{
  "limit": 5,
  "offset": 0
}
```

### Pour SearchArticlesBySpipId
```json
{
  "spipId": "SPIP_12345"
}
```

### Pour FilterProjectsByTags
```json
{
  "tagIds": ["1", "2", "3"]
}
```

## Utilisation dans Next.js

### Hook personnalisé pour les articles

```typescript
// hooks/useArticles.ts
import { useQuery } from '@apollo/client';
import { gql } from '@apollo/client';

const GET_ARTICLES = gql`
  query ListArticles($limit: Int = 10, $offset: Int = 0) {
    nodeQuery(limit: $limit, offset: $offset, filter: { conditions: [{ field: "type", value: "article" }] }) {
      entities {
        ... on Article {
          id
          title
          fieldSubtitle
          fieldSpipId
          fieldSpipUrl
          fieldImage {
            url
            alt
          }
        }
      }
      count
    }
  }
`;

export function useArticles(limit = 10, offset = 0) {
  return useQuery(GET_ARTICLES, {
    variables: { limit, offset },
  });
}
```

### Composant Article avec champs personnalisés

```typescript
// components/Article.tsx
import { gql, useQuery } from '@apollo/client';

const GET_ARTICLE = gql`
  query GetArticle($id: ID!) {
    node(id: $id) {
      ... on Article {
        id
        title
        fieldSubtitle
        fieldHeader {
          processed
        }
        fieldFooter {
          processed
        }
        fieldGallery {
          entities {
            ... on MediaImage {
              fieldMediaImage {
                url
                alt
              }
            }
          }
        }
        fieldSpipId
        fieldSpipUrl
      }
    }
  }
`;

export function Article({ id }: { id: string }) {
  const { loading, error, data } = useQuery(GET_ARTICLE, {
    variables: { id },
  });

  if (loading) return <div>Chargement...</div>;
  if (error) return <div>Erreur: {error.message}</div>;

  const article = data.node;

  return (
    <article>
      <h1>{article.title}</h1>
      {article.fieldSubtitle && (
        <h2>{article.fieldSubtitle}</h2>
      )}
      
      {article.fieldHeader?.processed && (
        <div dangerouslySetInnerHTML={{ __html: article.fieldHeader.processed }} />
      )}
      
      {article.fieldGallery?.entities?.length > 0 && (
        <div className="gallery">
          {article.fieldGallery.entities.map((image, index) => (
            <img 
              key={index}
              src={image.fieldMediaImage.url} 
              alt={image.fieldMediaImage.alt}
            />
          ))}
        </div>
      )}
      
      {article.fieldFooter?.processed && (
        <footer dangerouslySetInnerHTML={{ __html: article.fieldFooter.processed }} />
      )}
      
      {article.fieldSpipId && (
        <div>SPIP ID: {article.fieldSpipId}</div>
      )}
    </article>
  );
}
```

## Notes importantes

1. **Noms des champs** : Les champs sont exposés en camelCase (ex: `fieldSubtitle` au lieu de `field_subtitle`)
2. **Relations** : Les champs média retournent des entités avec leurs propres champs
3. **Champs de texte formaté** : Utilisez `.processed` pour le HTML rendu
4. **Pagination** : Utilisez `limit` et `offset` pour la pagination
5. **Filtres** : Vous pouvez combiner plusieurs conditions dans les filtres
