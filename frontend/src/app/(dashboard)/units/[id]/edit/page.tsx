"use client";

import { use } from "react";
import { EntityEditPage } from "@/components/entity/EntityEditPage";
import type { Unit } from "@/types";
import type { FieldConfig } from "@/components/entity/EntityForm";

const fields: FieldConfig[] = [
  { name: "name", label: "Name", type: "text", required: true },
  { name: "short_name", label: "Short name", type: "text", required: true },
];

export default function EditUnitPage(props: PageProps<"/units/[id]/edit">) {
  const { id } = use(props.params);

  return (
    <EntityEditPage<Unit>
      id={Number(id)}
      endpoint="/units"
      title="Edit Unit"
      fields={fields}
      cancelHref="/units"
      toValues={(u) => ({ name: u.name, short_name: u.short_name })}
    />
  );
}
