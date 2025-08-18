import Image from "next/image";
import { useTranslations } from "next-intl";

import { FormattedText } from "@/components/formatted-text";
import { HeadingPage } from "@/components/heading--page";
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
    <article {...props}>
      <HeadingPage>{article.title}</HeadingPage>
      {article.excerpt && <div className="my-4 text-xl">{article.excerpt}</div>}
      <div className="mb-4">
        {article.author?.name && (
          <span>{t("posted-by", { author: article.author.name })} - </span>
        )}
        <span>{formatDateTimestamp(article.created.timestamp, "en")}</span>
      </div>
      {(article.header?.processed || article.header?.value) && (
        <FormattedText
          className="text-md/xl mt-4 sm:text-lg"
          html={
            (article.header?.processed as string) ??
            (article.header?.value as string)
          }
        />
      )}
      {article.image && (
        <figure>
          <Image
            src={article.image.url}
            width={article.image.width}
            height={article.image.height}
            style={{ width: 768, height: 480 }}
            alt={article.image.alt}
            className="object-cover"
            priority
          />
          {article.image.title && (
            <figcaption className="py-2 text-center text-sm">
              {article.image.title}
            </figcaption>
          )}
        </figure>
      )}
      {article.body?.processed && (
        <FormattedText
          className="text-md/xl mt-4 sm:text-lg"
          html={article.body?.processed}
        />
      )}
      {(article.footer?.processed || article.footer?.value) && (
        <FormattedText
          className="text-md/xl mt-4 sm:text-lg"
          html={
            (article.footer?.processed as string) ??
            (article.footer?.value as string)
          }
        />
      )}
    </article>
  );
}
