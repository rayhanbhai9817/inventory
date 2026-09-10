"use client";

import { EntityListPage } from "@/components/entity/EntityListPage";
import { Badge } from "@/components/ui/Badge";
import type { Brand } from "@/types";

export default function BrandsPage() {
  return (
    <EntityListPage<Brand>
      title="Brands"
      endpoint="/brands"
      module="brands"
      newHref="/brands/new"
      editHref={(row) => `/brands/${row.id}/edit`}
      columns={[
        { key: "name", label: "Name", render: (r) => r.name },
        { key: "slug", label: "Slug", render: (r) => r.slug },
        { key: "status", label: "Status", render: (r) => <Badge status={r.status} /> },
      ]}
    />
  );
}
