"use client";

import { EntityListPage } from "@/components/entity/EntityListPage";
import { Badge } from "@/components/ui/Badge";
import type { Category } from "@/types";

export default function CategoriesPage() {
  return (
    <EntityListPage<Category>
      title="Categories"
      endpoint="/categories"
      module="categories"
      newHref="/categories/new"
      editHref={(row) => `/categories/${row.id}/edit`}
      columns={[
        { key: "name", label: "Name", render: (r) => r.name },
        { key: "slug", label: "Slug", render: (r) => r.slug },
        { key: "product_count", label: "Products", render: (r) => r.product_count ?? 0 },
        { key: "status", label: "Status", render: (r) => <Badge status={r.status} /> },
      ]}
    />
  );
}
