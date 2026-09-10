"use client";

import { use } from "react";
import { EntityEditPage } from "@/components/entity/EntityEditPage";
import type { Brand } from "@/types";
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

export default function EditBrandPage(props: PageProps<"/brands/[id]/edit">) {
  const { id } = use(props.params);

  return (
    <EntityEditPage<Brand>
      id={Number(id)}
      endpoint="/brands"
      title="Edit Brand"
      fields={fields}
      cancelHref="/brands"
      toValues={(b) => ({ name: b.name, status: b.status })}
    />
  );
}
