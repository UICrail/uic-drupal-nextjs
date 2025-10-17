import { unstable_cache } from "next/cache";
import { AbortError } from "p-retry";

import { REVALIDATE_LONG } from "@/lib/constants";
import {
  drupalClientPreviewer,
  drupalClientViewer,
} from "@/lib/drupal/drupal-client";
import { GET_ENTITY_AT_DRUPAL_PATH } from "@/lib/graphql/queries";

import { env } from "@/env";

/**
 * Function to directly fetch a node from Drupal by its path and locale.
 *
 * @param path The path of the node.
 * @param locale The locale of the node.
 * @param revision The revision of the node.
 * @param isDraftMode If true, fetches the draft version of the node.
 * @returns The fetched node data or null if not found.
 */
export async function fetchNodeByPathQuery(
  path: string,
  locale: string,
  isDraftMode: boolean,
  revision: string = null,
) {
  const drupalClient = isDraftMode ? drupalClientPreviewer : drupalClientViewer;
  return await drupalClient.doGraphQlRequest(GET_ENTITY_AT_DRUPAL_PATH, {
    path,
    langcode: locale,
    revision,
  });
}

/**
 * Function to retrieve a node by its Drupal path.
 *
 * @param path The Drupal path of the node.
 * @param locale The language code for the locale of the node.
 * @param isDraftMode Optional. Defaults to false. If true, fetches the draft version of the node.
 * @param revision Optional. The revision of the node.
 * @returns An object containing the node data or an error message.
 *
 * @example
 * const { data, error } = await getNodeByPathQuery('/about-us', 'en');
 *
 * // With draft mode enabled:
 * const { data, error } = await getNodeByPathQuery('/about-us', 'en', true);
 *
 * // With draft mode enabled and a specific revision:
 * const { data, error } = await getNodeByPathQuery('/about-us', 'en', true, 'working_copy');
 */
export async function getNodeByPathQuery(
  path: string,
  locale: string,
  isDraftMode: boolean = false,
  revision: string = null,
) {
  try {
    // Use Next.js 15 native unstable_cache with tags and revalidation
    // Don't cache in draft mode to always get fresh data
    if (isDraftMode) {
      return await fetchNodeByPathQuery(path, locale, isDraftMode, revision);
    }

    const cachedFetchNode = unstable_cache(
      async (
        nodePath: string,
        nodeLocale: string,
        draft: boolean,
        rev: string | null,
      ) => {
        return await fetchNodeByPathQuery(nodePath, nodeLocale, draft, rev);
      },
      [`node-${locale}-${path}-${revision || "latest"}`],
      {
        tags: [`/${locale}${path}`],
        revalidate: REVALIDATE_LONG,
      },
    );

    return await cachedFetchNode(path, locale, isDraftMode, revision);
  } catch (error) {
    const type =
      error instanceof AbortError
        ? "GraphQL"
        : error instanceof TypeError
          ? "Network"
          : "Unknown";

    const moreInfo =
      type === "GraphQL"
        ? `Check graphql_compose logs: ${env.NEXT_PUBLIC_DRUPAL_BASE_URL}/admin/reports`
        : "";

    const errorMessage = `${type} Error during GetNodeByPath query with $path: "${path}" and $langcode: "${locale}". ${moreInfo}`;
    console.log(JSON.stringify(errorMessage, null, 2));
    throw new Error(errorMessage);
  }
}
