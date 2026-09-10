"use client";

import { EntityListPage } from "@/components/entity/EntityListPage";
import { Badge } from "@/components/ui/Badge";
import type { Warehouse } from "@/types";

export default function WarehousesPage() {
  return (
    <EntityListPage<Warehouse>
      title="Warehouses"
      endpoint="/warehouses"
      module="warehouses"
      newHref="/warehouses/new"
      editHref={(row) => `/warehouses/${row.id}/edit`}
      columns={[
        { key: "name", label: "Name", render: (r) => r.name },
        { key: "code", label: "Code", render: (r) => r.code },
        { key: "default", label: "Default", render: (r) => (r.is_default ? "Yes" : "") },
        { key: "status", label: "Status", render: (r) => <Badge status={r.status} /> },
      ]}
    />
  );
}
