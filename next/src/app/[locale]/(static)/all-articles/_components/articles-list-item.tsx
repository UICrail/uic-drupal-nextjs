"use client";

import Image from "next/image";
import Link from "next/link";
import { useLocale, useTranslations } from "next-intl";

import type { FragmentArticleTeaserFragment } from "@/lib/gql/graphql";
import { cn, formatDateTimestamp } from "@/lib/utils";

interface ArticleListItemProps {
  article: FragmentArticleTeaserFragment;
}

export function ArticleListItem({ article }: ArticleListItemProps) {
  const t = useTranslations();
  const locale = useLocale();
  const author = article.author?.name;
  const date = formatDateTimestamp(article.created.timestamp, locale);

  return (
    <Link
      href={article.path}
      className={cn(
        "group relative flex h-full flex-col overflow-hidden rounded-lg border bg-white shadow-sm transition-all duration-300 hover:shadow-lg dark:bg-gray-900",
        "border-primary-200 bg-primary-50 dark:bg-primary-900/20",
      )}
    >
      {(article.featuredImage?.mediaImage || article.image) && (
        <div className="relative aspect-video overflow-hidden">
          <Image
            src={article.featuredImage?.mediaImage?.url || article.image?.url}
            width={500}
            height={300}
            className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
            alt={
              article.featuredImage?.mediaImage?.alt ||
              article.image?.alt ||
              article.title
            }
          />
          {article.sticky && (
            <div className="absolute right-2 top-2 h-2 w-2 rounded-full bg-cyan-500"></div>
          )}
        </div>
      )}
      <div className="flex flex-1 flex-col p-4">
        <h2 className="group-hover:text-primary-600 dark:group-hover:text-primary-400 mb-2 line-clamp-2 text-lg font-semibold leading-tight text-gray-900 transition-colors dark:text-white">
          {article.title}
        </h2>
        <div className="mb-3 text-sm text-gray-600 dark:text-gray-400">
          {author && (
            <span className="font-medium">{t("posted-by", { author })}</span>
          )}
          {author && date && <span className="mx-1">•</span>}
          <span>{date}</span>
        </div>
        <p className="line-clamp-3 flex-1 text-sm text-gray-700 dark:text-gray-300">
          {article.excerpt}
        </p>

        {/* Tags */}
        {Array.isArray(article.tags) && article.tags.length > 0 && (
          <div className="mt-3 flex flex-wrap gap-1">
            {article.tags.slice(0, 3).map((tag) => (
              <span
                key={tag.id}
                className="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300"
              >
                {tag.name}
              </span>
            ))}
            {article.tags.length > 3 && (
              <span className="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                +{article.tags.length - 3}
              </span>
            )}
          </div>
        )}
      </div>
    </Link>
  );
}
