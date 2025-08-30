import { graphql } from "@/lib/gql";

export const FRAGMENT_NODE_UNION = graphql(`
  fragment FragmentNodeUnion on NodeInterface {
    __typename
    id
    title
    status
    path
    langcode {
      id
    }
    created {
      timestamp
    }
    changed {
      timestamp
    }
    metatag {
      ...FragmentMetaTag
    }
    ...FragmentNodeActivityPage
    ...FragmentNodeArticle
    ...FragmentNodeFrontpage
    ...FragmentNodePage
  }
`);

export const FRAGMENT_NODE_ARTICLE = graphql(`
  fragment FragmentNodeArticle on NodeArticle {
    excerpt
    sticky
    header {
      ...FragmentText
    }
    body {
      ...FragmentTextSummary
    }
    footer {
      ...FragmentText
    }
    gallery {
      ...FragmentMediaImage
    }
    attachments {
      ...FragmentMediaDocument
    }
    featuredImage {
      ...FragmentMediaImage
    }
    tags {
      id
      name
      path
    }
    author {
      __typename
      ... on User {
        ...FragmentUser
      }
    }
    translations {
      ...FragmentNodeTranslation
    }
  }
`);

export const FRAGMENT_NODE_FRONTPAGE = graphql(`
  fragment FragmentNodeFrontpage on NodeFrontpage {
    contentElements {
      ... on ParagraphInterface {
        __typename
        id
        # Here we include only the paragraph types that can actually be used in the field
        # contentElements for this node type. Using the generated union type, we can be sure
        # that all fragments we use here can actually be used.
        ... on NodeFrontpageContentElementsUnion {
          ...FragmentParagraphFormattedText
          ...FragmentParagraphLink
          ...FragmentParagraphImage
          ...FragmentParagraphVideo
          ...FragmentParagraphFileAttachments
          ...FragmentParagraphHero
          ...FragmentParagraphAccordion
          ...FragmentParagraphListingArticle
          ...FragmentParagraphLiftupArticle
        }
      }
    }
    translations {
      ...FragmentNodeTranslation
    }
  }
`);

export const FRAGMENT_NODE_PAGE = graphql(`
  fragment FragmentNodePage on NodePage {
    contentElements {
      ... on ParagraphInterface {
        __typename
        id
        # Here we include only the paragraph types that can actually be used in the field
        # contentElements for this node type. Using the generated union type, we can be sure
        # that all fragments we refer to here can actually be used.
        ... on NodePageContentElementsUnion {
          ...FragmentParagraphFormattedText
          ...FragmentParagraphLink
          ...FragmentParagraphImage
          ...FragmentParagraphVideo
          ...FragmentParagraphFileAttachments
          ...FragmentParagraphHero
          ...FragmentParagraphAccordion
          ...FragmentParagraphListingArticle
          ...FragmentParagraphAccordion
          ...FragmentParagraphLiftupArticle
        }
      }
    }
    translations {
      ...FragmentNodeTranslation
    }
  }
`);

export const FRAGMENT_ARTICLE_TEASER = graphql(`
  fragment FragmentArticleTeaser on NodeArticle {
    __typename
    id
    featuredImage {
      ...FragmentMediaImage
    }
    path
    title
    sticky
    excerpt
    created {
      timestamp
    }
    tags {
      id
      name
      path
    }
    author {
      __typename
      ... on User {
        ...FragmentUser
      }
    }
  }
`);

export const FRAGMENT_NODE_ACTIVITY_PAGE = graphql(`
  fragment FragmentNodeActivityPage on NodeActivityPage {
    sticky
    header {
      ...FragmentText
    }
    body {
      ...FragmentTextSummary
    }
    footer {
      ...FragmentText
    }
    featuredImage {
      ...FragmentMediaImage
    }
    gallery {
      ... on MediaImage {
        ...FragmentMediaImage
      }
    }
    attachments {
      ... on MediaDocument {
        ...FragmentMediaDocument
      }
    }
    author {
      __typename
      ... on User {
        ...FragmentUser
      }
    }
    subtitle
    spipId
    spipUrl
  }
`);

export const FRAGMENT_ACTIVITY_TEASER = graphql(`
  fragment FragmentActivityTeaser on NodeActivityPage {
    __typename
    id
    path
    title
    sticky
    created {
      timestamp
    }
    header {
      ...FragmentText
    }
    featuredImage {
      ...FragmentMediaImage
    }
    gallery {
      ... on MediaImage {
        ...FragmentMediaImage
      }
    }
    author {
      __typename
      ... on User {
        ...FragmentUser
      }
    }
  }
`);
