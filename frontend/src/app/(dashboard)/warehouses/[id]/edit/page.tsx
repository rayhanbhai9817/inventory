"use client";

import { use } from "react";
import { EntityEditPage } from "@/components/entity/EntityEditPage";
import type { Warehouse } from "@/types";
import type { FieldConfig } from "@/components/entity/EntityForm";

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

export default function EditWarehousePage(props: PageProps<"/warehouses/[id]/edit">) {
  const { id } = use(props.params);

  return (
    <EntityEditPage<Warehouse>
      id={Number(id)}
      endpoint="/warehouses"
      title="Edit Warehouse"
      fields={fields}
      cancelHref="/warehouses"
      toValues={(w) => ({
        name: w.name,
        code: w.code,
        address: w.address ?? "",
        phone: w.phone ?? "",
        is_default: w.is_default,
        status: w.status,
      })}
    />
  );
}
