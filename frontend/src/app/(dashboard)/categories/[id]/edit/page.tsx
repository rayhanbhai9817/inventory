"use client";

import { use } from "react";
import { EntityEditPage } from "@/components/entity/EntityEditPage";
import type { Category } from "@/types";
import type { FieldConfig } from "@/components/entity/EntityForm";

const fields: FieldConfig[] = [
  { name: "name", label: "Name", type: "text", required: true },
  {
    name: "status",
    label: "Status",
    type: "select",
    options: [
      { value: "active", label: "Active" },
      { value: "inactive", label: "Inactive" },
    ],
  },
];

export default function EditCategoryPage(props: PageProps<"/categories/[id]/edit">) {
  const { id } = use(props.params);

  return (
    <EntityEditPage<Category>
      id={Number(id)}
      endpoint="/categories"
      title="Edit Category"
      fields={fields}
      cancelHref="/categories"
      toValues={(c) => ({ name: c.name, status: c.status })}
    />
  );
}
