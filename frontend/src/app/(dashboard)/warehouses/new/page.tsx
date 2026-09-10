"use client";

import { api } from "@/lib/api";
import { EntityForm, type FieldConfig } from "@/components/entity/EntityForm";

const fields: FieldConfig[] = [
  { name: "name", label: "Name", type: "text", required: true },
  { name: "code", label: "Code", type: "text", required: true },
  { name: "address", label: "Address", type: "text" },
  { name: "phone", label: "Phone", type: "text" },
  { name: "is_default", label: "Default warehouse", type: "checkbox" },
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

export default function NewWarehousePage() {
  return (
    <EntityForm
      title="New Warehouse"
      fields={fields}
      initialValues={{ name: "", code: "", address: "", phone: "", is_default: false, status: "active" }}
      cancelHref="/warehouses"
      onSubmit={async (values) => {
        await api.post("/warehouses", values);
      }}
    />
  );
}
