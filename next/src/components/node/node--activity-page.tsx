import { useTranslations } from "next-intl";
import Image from "next/image";

import { GalleryImageDialog } from "@/components/media/gallery-image-dialog";
import { MediaDocument } from "@/components/media/media--document";
import { FormattedText } from "@/components/formatted-text";
import type { FragmentTextFragment } from "@/lib/gql/graphql";
import type { TypedRouteEntity } from "@/types/graphql";

type ActivityWithHeaderFooter = Extract<
  TypedRouteEntity,
  { __typename: "NodeActivityPage" }
> & {
  header?: FragmentTextFragment;
  footer?: FragmentTextFragment;
};

export function NodeActivityPage({
  page,
  ...props
}: {
  page: ActivityWithHeaderFooter;
}) {
  const t = useTranslations();

  return (
    <article {...props} className="mx-auto max-w-4xl px-4 py-8">
      <header className="mb-8">
        <h1 className="mb-2 text-4xl font-bold leading-tight text-gray-900 dark:text-white md:text-5xl">
          {page.title}
        </h1>
        {page.subtitle && (
          <p className="text-xl text-gray-600 dark:text-gray-300">
            {page.subtitle}
          </p>
        )}
      </header>

      {page.featuredImage?.mediaImage && (
        <figure className="mb-8">
          <div className="relative aspect-video overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
            <Image
              src={page.featuredImage.mediaImage.url}
              width={page.featuredImage.mediaImage.width || 1200}
              height={page.featuredImage.mediaImage.height || 675}
              alt={page.featuredImage.mediaImage.alt || page.title}
              className="h-full w-full object-cover transition-transform duration-300 hover:scale-105"
              priority
              sizes="(max-width: 768px) 100vw, (max-width: 1200px) 80vw, 1200px"
            />
          </div>
          {page.featuredImage.mediaImage.title && (
            <figcaption className="mt-3 text-center text-sm italic text-gray-600 dark:text-gray-400">
              {page.featuredImage.mediaImage.title}
            </figcaption>
          )}
        </figure>
      )}

      {(page.header?.processed || page.header?.value) && (
        <div className="prose prose-lg mx-auto mb-8 max-w-none dark:prose-invert">
          <FormattedText
            html={
              (page.header?.processed as string) ??
              (page.header?.value as string)
            }
          />
        </div>
      )}

      {page.body?.processed && (
        <div className="prose-a:text-primary-600 dark:prose-a:text-primary-400 prose prose-lg mx-auto mb-8 max-w-none dark:prose-invert prose-headings:text-gray-900 prose-p:text-gray-700 dark:prose-headings:text-white dark:prose-p:text-gray-300">
          <FormattedText html={page.body?.processed} />
        </div>
      )}

      {(page.footer?.processed || page.footer?.value) && (
        <div className="prose prose-lg mx-auto mb-8 max-w-none dark:prose-invert">
          <FormattedText
            html={
              (page.footer?.processed as string) ??
              (page.footer?.value as string)
            }
          />
        </div>
      )}

      {Array.isArray(page.gallery) && page.gallery.length > 0 && (
        <section className="mb-12" aria-labelledby="gallery-heading">
          <h2
            id="gallery-heading"
            className="mb-6 text-2xl font-bold text-gray-900 dark:text-white"
          >
            Gallery
          </h2>
          <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {page.gallery.map((media) => (
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

      {Array.isArray(page.attachments) && page.attachments.length > 0 && (
        <section className="mb-8" aria-labelledby="attachments-heading">
          <h2
            id="attachments-heading"
            className="mb-4 text-2xl font-bold text-gray-900 dark:text-white"
          >
            {t("downloadable-files")}
          </h2>
          <div className="space-y-3">
            {page.attachments.map((doc) => (
              <div
                key={doc.id}
                className="rounded-lg border border-gray-200 bg-white p-4 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700"
              >
                <a
                  href={
                    "mediaDocumentFile" in doc
                      ? doc.mediaDocumentFile.url
                      : undefined
                  }
                  className="text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 group flex items-center gap-3 transition-colors"
                  download
                >
                  <MediaDocument media={doc as any} />
                </a>
              </div>
            ))}
          </div>
        </section>
      )}
    </article>
  );
}
