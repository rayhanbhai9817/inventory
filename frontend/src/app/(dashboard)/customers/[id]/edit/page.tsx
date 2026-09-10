"use client";

import { use } from "react";
import { EntityEditPage } from "@/components/entity/EntityEditPage";
import type { Customer } from "@/types";
import type { FieldConfig } from "@/components/entity/EntityForm";

const fields: FieldConfig[] = [
  { name: "name", label: "Name", type: "text", required: true },
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

export default function EditCustomerPage(props: PageProps<"/customers/[id]/edit">) {
  const { id } = use(props.params);

  return (
    <EntityEditPage<Customer>
      id={Number(id)}
      endpoint="/customers"
      title="Edit Customer"
      fields={fields}
      cancelHref="/customers"
      toValues={(c) => ({
        name: c.name,
        email: c.email ?? "",
        phone: c.phone ?? "",
        address: c.address ?? "",
        status: c.status,
      })}
    />
  );
}
