"use client";

import { EntityListPage } from "@/components/entity/EntityListPage";
import { Badge } from "@/components/ui/Badge";
import type { Product } from "@/types";

export default function ProductsPage() {
  return (
    <EntityListPage<Product>
      title="Products"
      endpoint="/products"
      module="products"
      newHref="/products/new"
      editHref={(row) => `/products/${row.id}/edit`}
      columns={[
        { key: "name", label: "Name", render: (r) => r.name },
        { key: "sku", label: "SKU", render: (r) => r.sku },
        { key: "category", label: "Category", render: (r) => r.category?.name ?? "—" },
        {
          key: "stock",
          label: "Stock",
          render: (r) => {
            const stock = r.total_stock ?? 0;
            const low = stock <= r.min_stock_level;
            return (
              <span className={low ? "font-medium text-amber-600" : ""}>
                {stock} {r.unit?.short_name}
                {low && " (low)"}
              </span>
            );
          },
        },
        {
          key: "price",
          label: "Price",
          render: (r) => r.selling_price.toLocaleString(undefined, { minimumFractionDigits: 2 }),
        },
        { key: "status", label: "Status", render: (r) => <Badge status={r.status} /> },
      ]}
    />
  );
}
