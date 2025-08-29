import Image from "next/image";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { Calendar, Tag, User } from "lucide-react";

import { GalleryImageDialog } from "@/components/media/gallery-image-dialog";
import { MediaDocument } from "@/components/media/media--document";
import { FormattedText } from "@/components/formatted-text";
import { formatDateTimestamp } from "@/lib/utils";
import type { ArticleType } from "@/types/graphql";
import type { FragmentTextFragment } from "@/lib/gql/graphql";

type ArticleWithHeaderFooter = ArticleType & {
  header?: FragmentTextFragment;
  footer?: FragmentTextFragment;
};

interface ArticleProps {
  article: ArticleWithHeaderFooter;
}

export function NodeArticle({ article, ...props }: ArticleProps) {
  const t = useTranslations();

  return (
    <article {...props} className="mx-auto max-w-4xl px-4 py-8">
      {/* Article Header */}
      <header className="mb-8">
        <h1 className="mb-4 text-4xl font-bold leading-tight text-gray-900 dark:text-white md:text-5xl">
          {article.title}
        </h1>

        {article.excerpt && (
          <p className="mb-6 text-xl leading-relaxed text-gray-600 dark:text-gray-300">
            {article.excerpt}
          </p>
        )}

        {/* Article Meta */}
        <div className="mb-6 flex flex-wrap items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
          {article.author?.name && (
            <div className="flex items-center gap-2">
              <User size={16} aria-hidden="true" />
              <span>{t("posted-by", { author: article.author.name })}</span>
            </div>
          )}
          <div className="flex items-center gap-2">
            <Calendar size={16} aria-hidden="true" />
            <time
              dateTime={new Date(
                article.created.timestamp * 1000,
              ).toISOString()}
            >
              {formatDateTimestamp(article.created.timestamp, "en")}
            </time>
          </div>
        </div>

        {/* Tags */}
        {Array.isArray(article.tags) && article.tags.length > 0 && (
          <div className="mb-6">
            <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
              <Tag size={16} aria-hidden="true" />
              <span className="sr-only">{t("tags")}</span>
              <div className="flex flex-wrap gap-2">
                {article.tags.map((tag) => (
                  <Link
                    key={tag.id}
                    href={tag.path}
                    className="bg-primary-100 text-primary-800 hover:bg-primary-200 dark:bg-primary-900 dark:text-primary-200 dark:hover:bg-primary-800 rounded-full px-3 py-1 text-sm font-medium transition-colors"
                  >
                    {tag.name}
                  </Link>
                ))}
              </div>
            </div>
          </div>
        )}
      </header>
      {/* Header Content */}
      {(article.header?.processed || article.header?.value) && (
        <div className="prose prose-lg mx-auto mb-8 max-w-none dark:prose-invert">
          <FormattedText
            html={
              (article.header?.processed as string) ??
              (article.header?.value as string)
            }
          />
        </div>
      )}

      {/* Featured Image */}
      {article.featuredImage?.mediaImage ? (
        <figure className="mb-8">
          <div className="relative aspect-video overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
            <Image
              src={article.featuredImage.mediaImage.url}
              width={article.featuredImage.mediaImage.width || 1200}
              height={article.featuredImage.mediaImage.height || 675}
              alt={article.featuredImage.mediaImage.alt || article.title}
              className="h-full w-full object-cover transition-transform duration-300 hover:scale-105"
              priority
              sizes="(max-width: 768px) 100vw, (max-width: 1200px) 80vw, 1200px"
            />
          </div>
          {article.featuredImage.mediaImage.title && (
            <figcaption className="mt-3 text-center text-sm italic text-gray-600 dark:text-gray-400">
              {article.featuredImage.mediaImage.title}
            </figcaption>
          )}
        </figure>
      ) : (
        // Fallback to old image field
        article.image && (
          <figure className="mb-8">
            <div className="relative aspect-video overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
              <Image
                src={article.image.url}
                width={article.image.width || 1200}
                height={article.image.height || 675}
                alt={article.image.alt || article.title}
                className="h-full w-full object-cover transition-transform duration-300 hover:scale-105"
                priority
                sizes="(max-width: 768px) 100vw, (max-width: 1200px) 80vw, 1200px"
              />
            </div>
            {article.image.title && (
              <figcaption className="mt-3 text-center text-sm italic text-gray-600 dark:text-gray-400">
                {article.image.title}
              </figcaption>
            )}
          </figure>
        )
      )}
      {/* Main Content */}
      {article.body?.processed && (
        <div className="prose-a:text-primary-600 dark:prose-a:text-primary-400 prose prose-lg mx-auto mb-8 max-w-none dark:prose-invert prose-headings:text-gray-900 prose-p:text-gray-700 dark:prose-headings:text-white dark:prose-p:text-gray-300">
          <FormattedText html={article.body?.processed} />
        </div>
      )}

      {/* Footer Content */}
      {(article.footer?.processed || article.footer?.value) && (
        <div className="prose prose-lg mx-auto mb-8 max-w-none dark:prose-invert">
          <FormattedText
            html={
              (article.footer?.processed as string) ??
              (article.footer?.value as string)
            }
          />
        </div>
      )}

      {/* Image Gallery */}
      {Array.isArray(article.gallery) && article.gallery.length > 0 && (
        <section className="mb-12" aria-labelledby="gallery-heading">
          <h2
            id="gallery-heading"
            className="mb-6 text-2xl font-bold text-gray-900 dark:text-white"
          >
            Gallery
          </h2>
          <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {article.gallery.map((media) => (
              <div
                key={media.id}
                className="group relative overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800"
              >
                <GalleryImageDialog media={media} />
              </div>
            ))}
          </div>
        </section>
      )}

      {/* Attachments */}
      {Array.isArray(article.attachments) && article.attachments.length > 0 && (
        <section className="mb-8" aria-labelledby="attachments-heading">
          <h2
            id="attachments-heading"
            className="mb-4 text-2xl font-bold text-gray-900 dark:text-white"
          >
            {t("downloadable-files")}
          </h2>
          <div className="space-y-3">
            {article.attachments.map((doc) => (
              <div
                key={doc.id}
                className="rounded-lg border border-gray-200 bg-white p-4 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700"
              >
                <a
                  href={doc.mediaDocumentFile.url}
                  className="text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 group flex items-center gap-3 transition-colors"
                  download
                  aria-label={`${t("download")} ${doc.name}`}
                >
                  <MediaDocument media={doc} />
                </a>
              </div>
            ))}
          </div>
        </section>
      )}
    </article>
  );
}
