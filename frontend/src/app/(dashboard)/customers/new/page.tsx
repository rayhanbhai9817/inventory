"use client";

import { api } from "@/lib/api";
import { EntityForm, type FieldConfig } from "@/components/entity/EntityForm";

const fields: FieldConfig[] = [
  { name: "name", label: "Name", type: "text", required: true },
  { name: "email", label: "Email", type: "text" },
  { name: "phone", label: "Phone", type: "text" },
  { name: "address", label: "Address", type: "text" },
  { name: "opening_balance", label: "Opening balance", type: "number" },
];

export default function NewCustomerPage() {
  return (
    <EntityForm
      title="New Customer"
      fields={fields}
      initialValues={{ name: "", email: "", phone: "", address: "", opening_balance: 0 }}
      cancelHref="/customers"
      onSubmit={async (values) => {
        await api.post("/customers", values);
      }}
    />
  );
}
