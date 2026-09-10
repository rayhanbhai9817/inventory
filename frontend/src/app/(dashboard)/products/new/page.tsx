"use client";

import { api } from "@/lib/api";
import { EntityForm } from "@/components/entity/EntityForm";
import { useProductFormFields } from "@/components/entity/useProductFormFields";

export default function NewProductPage() {
  const fields = useProductFormFields();

  if (!fields) return <p className="text-sm text-slate-500">Loading…</p>;

  return (
    <EntityForm
      title="Add Product"
      fields={fields}
      initialValues={{ name: "", sku: "", category_id: "", min_stock_level: 0, description: "" }}
      cancelHref="/products"
      onSubmit={async (values) => {
        await api.post("/products", values);
      }}
    />
  );
}
