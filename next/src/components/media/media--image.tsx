import NextImage from "next/image";

import type { FragmentMediaImageFragment } from "@/lib/gql/graphql";

type MediaImageProps = {
  media: FragmentMediaImageFragment;
  priority?: boolean;
};

export function MediaImage({ media, priority }: MediaImageProps) {
  if (!media) {
    return null;
  }

  const { url, width, height, alt, title } = media.mediaImage;
  const computedAlt =
    typeof alt === "string" && alt.trim().length > 0
      ? alt
      : typeof title === "string" && title.trim().length > 0
        ? title
        : (() => {
            try {
              const pathname = new URL(url, "http://dummy").pathname;
              const filename = decodeURIComponent(
                pathname.split("/").pop() || "",
              );
              return filename || "Image";
            } catch {
              return "Image";
            }
          })();

  return (
    <NextImage
      src={url}
      width={width}
      height={height}
      alt={computedAlt}
      title={typeof title === "string" ? title : ""}
      priority={priority}
      className="h-auto max-w-full object-cover"
    />
  );
}
