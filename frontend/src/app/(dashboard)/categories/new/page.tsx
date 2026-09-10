"use client";

import { api } from "@/lib/api";
import { EntityForm, type FieldConfig } from "@/components/entity/EntityForm";

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

export default function NewCategoryPage() {
  return (
    <EntityForm
      title="New Category"
      fields={fields}
      initialValues={{ name: "", status: "active" }}
      cancelHref="/categories"
      onSubmit={async (values) => {
        await api.post("/categories", values);
      }}
    />
  );
}
