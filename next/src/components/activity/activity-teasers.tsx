import { useTranslations } from "next-intl";

import { HeadingParagraph } from "@/components/heading--paragraph";
import { ArrowLinkButton } from "@/components/ui/arrow-link-button";

import { ActivityTeaser, type ActivityTeaserItem } from "./activity-teaser";

interface LatestActivitiesProps {
  activities?: ActivityTeaserItem[];
  heading: string;
}

export function ActivityTeasers({
  activities,
  heading,
}: LatestActivitiesProps) {
  const t = useTranslations();

  return (
    <>
      <HeadingParagraph>{heading}</HeadingParagraph>
      <ul className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
        {activities?.map((activity) => (
          <li key={activity.id}>
            <ActivityTeaser activity={activity} />
          </li>
        ))}
      </ul>
      <div className="flex items-center justify-center">
        {!activities?.length && <p className="py-4">{t("no-content-found")}</p>}
      </div>
    </>
  );
}
