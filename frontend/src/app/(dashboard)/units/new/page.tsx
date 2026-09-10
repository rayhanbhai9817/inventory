"use client";

import { api } from "@/lib/api";
import { EntityForm, type FieldConfig } from "@/components/entity/EntityForm";

const fields: FieldConfig[] = [
  { name: "name", label: "Name", type: "text", required: true, placeholder: "Piece" },
  { name: "short_name", label: "Short name", type: "text", required: true, placeholder: "pc" },
];

export default function NewUnitPage() {
  return (
    <EntityForm
      title="New Unit"
      fields={fields}
      initialValues={{ name: "", short_name: "" }}
      cancelHref="/units"
      onSubmit={async (values) => {
        await api.post("/units", values);
      }}
    />
  );
}
