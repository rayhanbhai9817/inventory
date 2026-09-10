"use client";

import { use } from "react";
import { EntityEditPage } from "@/components/entity/EntityEditPage";
import type { Supplier } from "@/types";
import type { FieldConfig } from "@/components/entity/EntityForm";

const fields: FieldConfig[] = [
  { name: "name", label: "Name", type: "text", required: true },
  { name: "company_name", label: "Company name", type: "text" },
  { name: "email", label: "Email", type: "text" },
  { name: "phone", label: "Phone", type: "text" },
  { name: "address", label: "Address", type: "text" },
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

export default function EditSupplierPage(props: PageProps<"/suppliers/[id]/edit">) {
  const { id } = use(props.params);

  return (
    <EntityEditPage<Supplier>
      id={Number(id)}
      endpoint="/suppliers"
      title="Edit Supplier"
      fields={fields}
      cancelHref="/suppliers"
      toValues={(s) => ({
        name: s.name,
        company_name: s.company_name ?? "",
        email: s.email ?? "",
        phone: s.phone ?? "",
        address: s.address ?? "",
        status: s.status,
      })}
    />
  );
}
