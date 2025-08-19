import Image from "next/image";
import Link from "next/link";
import { HTMLAttributes } from "react";
import { getLocale } from "next-intl/server";
import parse, {
  DOMNode,
  domToReact,
  Element,
  HTMLReactParserOptions,
} from "html-react-parser";

import { isRelative } from "@/lib/utils";

import { env } from "@/env";
import { Media } from "@/components/media";
import { drupalClientViewer } from "@/lib/drupal/drupal-client";
import { GET_MEDIA_BY_ID } from "@/lib/graphql/queries";
import type { FragmentMediaUnionFragment } from "@/lib/gql/graphql";

async function DrupalMediaEmbed({
  uuid,
  align,
}: {
  uuid: string;
  align?: string;
}) {
  const langcode = await getLocale();
  const data = await drupalClientViewer.doGraphQlRequest(GET_MEDIA_BY_ID, {
    id: uuid,
    langcode,
  });
  const media = data?.media as FragmentMediaUnionFragment | null;
  if (!media) return null;
  const wrapperClassName = align ? `text-${align}` : undefined;
  return (
    <div className={wrapperClassName}>
      <Media media={media} />
    </div>
  );
}

const isElement = (domNode: DOMNode): domNode is Element =>
  domNode instanceof Element;

const options: HTMLReactParserOptions = {
  /*
   * If `undefined` is returned from this `replace` function, nothing is changed and the given DOMNode is rendered as usual.
   * But if anything else is returned, that value replaces the original value.
   * For example, return `null` to remove it, or some other component to replace it.
   */
  replace: (domNode) => {
    if (!isElement(domNode)) return;

    switch (domNode.name) {
      case "img": {
        const { src, alt, width = 100, height = 100 } = domNode.attribs;

        const numberWidth = Number(width);
        const numberHeight = Number(height);

        if (isRelative(src)) {
          return (
            <Image
              src={`${env.NEXT_PUBLIC_DRUPAL_BASE_URL}${src}`}
              width={numberWidth}
              height={numberHeight}
              alt={alt}
              className="max-w-full object-cover"
            />
          );
        }
        break;
      }

      case "drupal-media": {
        const {
          ["data-entity-type"]: entityType,
          ["data-entity-uuid"]: entityUuid,
          ["data-align"]: align,
        } = domNode.attribs ?? {};

        if (entityType === "media" && entityUuid) {
          return <DrupalMediaEmbed uuid={entityUuid} align={align} />;
        }
        return null;
      }

      case "a": {
        const { href } = domNode.attribs;

        if (href && isRelative(href)) {
          return (
            <Link href={href} className="underline">
              {domToReact(domNode.children as DOMNode[], options)}
            </Link>
          );
        }
        break;
      }

      case "p": {
        const hasDrupalMediaChild = (domNode.children || []).some(
          (child) => isElement(child) && child.name === "drupal-media",
        );

        if (hasDrupalMediaChild) {
          return (
            <div className="mb-2 text-muted-foreground">
              {domToReact(domNode.children as DOMNode[], options)}
            </div>
          );
        }

        return (
          <p className="mb-2 text-muted-foreground">
            {domToReact(domNode.children as DOMNode[], options)}
          </p>
        );
      }

      case "input": {
        if (domNode.attribs.value === "") {
          delete domNode.attribs.value;
        }

        return domNode;
      }

      default: {
        return undefined;
      }
    }
  },
};

interface FormattedTextProps extends HTMLAttributes<HTMLDivElement> {
  html: string;
}

export function FormattedText({ html, ...props }: FormattedTextProps) {
  return <div {...props}>{parse(html, options)}</div>;
}
