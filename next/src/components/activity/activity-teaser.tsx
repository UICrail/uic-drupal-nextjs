"use client";

import Image from "next/image";
import Link from "next/link";
import { useTranslations } from "next-intl";

import { cn, formatDateTimestamp } from "@/lib/utils";

export interface ActivityTeaserItem {
  id: string;
  path?: string | null;
  title: string;
  created?: { timestamp: number } | null;
  header?: { value?: string | null; processed?: string | null } | null;
  featuredImage?: {
    mediaImage?: { url: string; alt?: string | null } | null;
  } | null;
  gallery?:
    | { mediaImage?: { url: string; alt?: string | null } | null }[]
    | null;
}

interface ActivityTeaserProps {
  activity: ActivityTeaserItem;
}

export function ActivityTeaser({ activity }: ActivityTeaserProps) {
  const t = useTranslations();
  const date = activity?.created
    ? formatDateTimestamp(activity.created.timestamp, "en")
    : undefined;

  const image =
    activity.featuredImage?.mediaImage ||
    activity.gallery?.[0]?.mediaImage ||
    null;

  const CardInner = (
    <>
      {image && (
        <div className="relative aspect-video overflow-hidden">
          <Image
            src={image.url}
            width={500}
            height={300}
            className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
            alt={image.alt || activity.title}
          />
        </div>
      )}
      <div className="flex flex-1 flex-col p-4">
        <h3 className="group-hover:text-primary-600 dark:group-hover:text-primary-400 mb-2 line-clamp-2 text-lg font-semibold leading-tight text-gray-900 transition-colors dark:text-white">
          {activity.title}
        </h3>
        {date && (
          <div className="mb-3 text-sm text-gray-600 dark:text-gray-400">
            <span>{date}</span>
          </div>
        )}
        {activity.header?.value && (
          <p className="line-clamp-3 flex-1 text-sm text-gray-700 dark:text-gray-300">
            {activity.header.value}
          </p>
        )}
      </div>
    </>
  );

  const cardClass = cn(
    "group relative flex h-full flex-col overflow-hidden rounded-lg border bg-white shadow-sm transition-all duration-300 hover:shadow-lg dark:bg-gray-900",
    "border-primary-200 bg-primary-50 dark:bg-primary-900/20",
  );

  if (!activity.path) {
    return <div className={cardClass}>{CardInner}</div>;
  }

  return (
    <Link href={activity.path} className={cardClass}>
      {CardInner}
    </Link>
  );
}
