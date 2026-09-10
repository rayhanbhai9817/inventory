"use client";

import { useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { Product, SingleResponse } from "@/types";
import { EntityForm } from "@/components/entity/EntityForm";
import { useProductFormFields } from "@/components/entity/useProductFormFields";

export function ProductEditForm({ id }: { id: number }) {
  const fields = useProductFormFields();
  const [initialValues, setInitialValues] = useState<Record<string, unknown> | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    api
      .get<SingleResponse<Product>>(`/products/${id}`)
      .then((res) => {
        if (cancelled) return;
        const p = res.data;
        setInitialValues({
          name: p.name,
          sku: p.sku,
          barcode: p.barcode ?? "",
          category_id: p.category?.id ?? "",
          brand_id: p.brand?.id ?? "",
          unit_id: p.unit?.id ?? "",
          cost_price: p.cost_price,
          selling_price: p.selling_price,
          min_stock_level: p.min_stock_level,
          description: p.description ?? "",
          status: p.status,
        });
      })
      .catch((err) => {
        if (!cancelled) setError(err instanceof ApiError ? err.message : "Failed to load.");
      });
    return () => {
      cancelled = true;
    };
  }, [id]);

  if (error) return <p className="text-sm text-red-600">{error}</p>;
  if (!fields || !initialValues) return <p className="text-sm text-slate-500">Loading…</p>;

  return (
    <EntityForm
      title="Edit Product"
      fields={fields}
      initialValues={initialValues}
      cancelHref="/products"
      onSubmit={async (values) => {
        await api.put(`/products/${id}`, values);
      }}
    />
  );
}
