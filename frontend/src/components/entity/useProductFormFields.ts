"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import type { Category, PaginatedResponse } from "@/types";
import type { FieldConfig } from "@/components/entity/EntityForm";

/** Loads categories and builds the field config for the product form. */
export function useProductFormFields(): FieldConfig[] | null {
  const [fields, setFields] = useState<FieldConfig[] | null>(null);

  useEffect(() => {
    let cancelled = false;

    api.get<PaginatedResponse<Category>>("/categories", { per_page: 100 }).then((categories) => {
      if (cancelled) return;

      setFields([
        { name: "name", label: "Name", type: "text", required: true },
        { name: "sku", label: "SKU", type: "text", required: true },
        {
          name: "category_id",
          label: "Category",
          type: "select",
          options: categories.data.map((c) => ({ value: c.id, label: c.name })),
        },
        { name: "min_stock_level", label: "Minimum stock level (units)", type: "number" },
        { name: "description", label: "Description", type: "textarea" },
      ]);
    });

    return () => {
      cancelled = true;
    };
  }, []);

  return fields;
}
