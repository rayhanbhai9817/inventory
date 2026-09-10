"use client";

import { EntityListPage } from "@/components/entity/EntityListPage";
import type { Unit } from "@/types";

export default function UnitsPage() {
  return (
    <EntityListPage<Unit>
      title="Units"
      endpoint="/units"
      module="units"
      newHref="/units/new"
      editHref={(row) => `/units/${row.id}/edit`}
      columns={[
        { key: "name", label: "Name", render: (r) => r.name },
        { key: "short_name", label: "Short name", render: (r) => r.short_name },
      ]}
    />
  );
}
