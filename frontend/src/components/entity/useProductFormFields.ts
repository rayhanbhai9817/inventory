"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import type { Brand, Category, PaginatedResponse, Unit } from "@/types";
import type { FieldConfig } from "@/components/entity/EntityForm";

/**
 * Loads categories/brands/units and builds the field config for the
 * product create/edit form. Returns null while loading.
 */
export function useProductFormFields(): FieldConfig[] | null {
  const [fields, setFields] = useState<FieldConfig[] | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      const [categories, brands, units] = await Promise.all([
        api.get<PaginatedResponse<Category>>("/categories", { per_page: 100 }),
        api.get<PaginatedResponse<Brand>>("/brands", { per_page: 100 }),
        api.get<PaginatedResponse<Unit>>("/units", { per_page: 100 }),
      ]);

      if (cancelled) return;

      setFields([
        { name: "name", label: "Name", type: "text", required: true },
        { name: "sku", label: "SKU", type: "text", required: true },
        { name: "barcode", label: "Barcode", type: "text" },
        {
          name: "category_id",
          label: "Category",
          type: "select",
          options: categories.data.map((c) => ({ value: c.id, label: c.name })),
        },
        {
          name: "brand_id",
          label: "Brand",
          type: "select",
          options: brands.data.map((b) => ({ value: b.id, label: b.name })),
        },
        {
          name: "unit_id",
          label: "Unit",
          type: "select",
          required: true,
          options: units.data.map((u) => ({ value: u.id, label: `${u.name} (${u.short_name})` })),
        },
        { name: "cost_price", label: "Cost price", type: "number", required: true },
        { name: "selling_price", label: "Selling price", type: "number", required: true },
        { name: "min_stock_level", label: "Minimum stock level", type: "number" },
        { name: "description", label: "Description", type: "textarea" },
        {
          name: "status",
          label: "Status",
          type: "select",
          options: [
            { value: "active", label: "Active" },
            { value: "inactive", label: "Inactive" },
          ],
        },
      ]);
    }

    load();
    return () => {
      cancelled = true;
    };
  }, []);

  return fields;
}
