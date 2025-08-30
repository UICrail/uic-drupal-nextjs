import { drupalClientViewer } from "@/lib/drupal/drupal-client";
import type { FragmentArticleTeaserFragment } from "@/lib/gql/graphql";
import { LISTING_ACTIVITIES_CONNECTION, LISTING_ARTICLES } from "@/lib/graphql/queries";
import type { RequestDocument } from "graphql-request";

import { routing } from "@/i18n/routing";

type GetArticlesArgs = {
  limit?: number;
  offset?: number;
  locale?: string;
};

export const getArticles = async ({
  limit = 5,
  offset = 0,
  locale = routing.defaultLocale,
}: GetArticlesArgs): Promise<{
  totalPages: number;
  nodes: FragmentArticleTeaserFragment[];
}> => {
  let nodes: FragmentArticleTeaserFragment[] = [];
  let totalPages = 1;
  try {
    const articlesViewResult = await drupalClientViewer.doGraphQlRequest(
      LISTING_ARTICLES,
      {
        langcode: locale,
        page: 0,
        pageSize: limit,
        offset: offset,
      },
    );

    if (articlesViewResult.articlesView?.results) {
      nodes = articlesViewResult.articlesView
        .results as FragmentArticleTeaserFragment[];
      // To get to the total number of pages, we need to add the offset
      // to the "total" property, that is to be considered as the total "remaining"
      // articles to be displayed.
      totalPages = Math.ceil(
        (articlesViewResult.articlesView.pageInfo.total + offset) / limit,
      );
    }
  } catch (error) {
    console.error(error);
  }

  return {
    totalPages,
    nodes,
  };
};

export const getLatestArticlesItems = async (
  args: GetArticlesArgs,
): Promise<{
  totalPages: number;
  articles: FragmentArticleTeaserFragment[];
}> => {
  const { totalPages, nodes } = await getArticles(args);

  return {
    totalPages,
    articles: nodes,
  };
};

type GetActivitiesArgs = {
  first?: number;
  after?: string | null;
  last?: number;
  before?: string | null;
  locale?: string;
};

type ActivityTeaser = {
  id: string;
  path?: string | null;
  title: string;
  created?: { timestamp: number };
  header?: { value?: string | null; processed?: string | null };
  gallery?: { mediaImage?: { url: string; alt?: string | null } }[];
  author?: { __typename?: string; name?: string | null } | null;
};

export const getActivities = async ({
  first = 20,
  after = null,
  last,
  before,
  locale = routing.defaultLocale,
}: GetActivitiesArgs): Promise<{
  nodes: ActivityTeaser[];
  pageInfo: {
    hasNextPage: boolean;
    hasPreviousPage: boolean;
    startCursor: string | null;
    endCursor: string | null;
  };
}> => {
  let nodes: ActivityTeaser[] = [];
  let pageInfo = {
    hasNextPage: false,
    hasPreviousPage: false,
    startCursor: null as string | null,
    endCursor: null as string | null,
  };
  try {
    const result = (await drupalClientViewer.doGraphQlRequest(
      (LISTING_ACTIVITIES_CONNECTION as unknown) as RequestDocument,
      {
        langcode: locale,
        first,
        after,
        last,
        before,
      },
    )) as unknown as {
      nodeActivityPages?: {
        nodes?: ActivityTeaser[];
        pageInfo?: typeof pageInfo;
      };
    };

    if (result?.nodeActivityPages) {
      nodes = (result.nodeActivityPages.nodes || []) as ActivityTeaser[];
      pageInfo = {
        hasNextPage: !!result.nodeActivityPages.pageInfo?.hasNextPage,
        hasPreviousPage: !!result.nodeActivityPages.pageInfo?.hasPreviousPage,
        startCursor: result.nodeActivityPages.pageInfo?.startCursor ?? null,
        endCursor: result.nodeActivityPages.pageInfo?.endCursor ?? null,
      };
    }
  } catch (error) {
    console.error(error);
  }

  return {
    nodes,
    pageInfo,
  };
};

export const getLatestActivitiesItems = async (
  args: GetActivitiesArgs,
): Promise<{
  activities: ActivityTeaser[];
  pageInfo: {
    hasNextPage: boolean;
    hasPreviousPage: boolean;
    startCursor: string | null;
    endCursor: string | null;
  };
}> => {
  const { nodes, pageInfo } = await getActivities(args);
  return {
    activities: nodes,
    pageInfo,
  };
};
