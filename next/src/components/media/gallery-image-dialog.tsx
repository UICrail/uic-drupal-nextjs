"use client";

import NextImage from "next/image";

import { Dialog, DialogContent, DialogTrigger } from "@/components/ui/dialog";
import type { FragmentMediaImageFragment } from "@/lib/gql/graphql";

export function GalleryImageDialog({
  media,
}: {
  media: FragmentMediaImageFragment;
}) {
  if (!media) return null;

  const { url, width, height, alt, title } = media.mediaImage;

  return (
    <Dialog>
      <DialogTrigger asChild>
        <button
          type="button"
          className="group block w-full overflow-hidden rounded border border-border bg-background transition-all hover:scale-[1.01] hover:shadow-md focus:outline-none focus:ring-2 focus:ring-ring"
        >
          <NextImage
            src={url}
            width={width}
            height={height}
            alt={alt || "Image"}
            title={title || ""}
            className="h-auto w-full max-w-full object-cover transition-opacity group-hover:opacity-95"
          />
        </button>
      </DialogTrigger>
      <DialogContent className="max-w-[90vw] p-2 sm:p-4">
        <div className="flex max-h-[85vh] items-center justify-center overflow-auto">
          <NextImage
            src={url}
            width={width}
            height={height}
            alt={alt || "Image"}
            title={title || ""}
            className="h-auto max-h-[80vh] w-auto max-w-full object-contain"
            priority
          />
        </div>
      </DialogContent>
    </Dialog>
  );
}
