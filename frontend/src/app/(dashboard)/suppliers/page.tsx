"use client";

import { EntityListPage } from "@/components/entity/EntityListPage";
import { Badge } from "@/components/ui/Badge";
import type { Supplier } from "@/types";

export default function SuppliersPage() {
  return (
    <EntityListPage<Supplier>
      title="Suppliers"
      endpoint="/suppliers"
      module="suppliers"
      newHref="/suppliers/new"
      editHref={(row) => `/suppliers/${row.id}/edit`}
      columns={[
        { key: "name", label: "Name", render: (r) => r.name },
        { key: "phone", label: "Phone", render: (r) => r.phone ?? "—" },
        {
          key: "balance",
          label: "Balance",
          render: (r) => r.current_balance.toLocaleString(undefined, { minimumFractionDigits: 2 }),
        },
        { key: "status", label: "Status", render: (r) => <Badge status={r.status} /> },
      ]}
    />
  );
}
