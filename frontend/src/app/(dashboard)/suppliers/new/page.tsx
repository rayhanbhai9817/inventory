"use client";

import { api } from "@/lib/api";
import { EntityForm, type FieldConfig } from "@/components/entity/EntityForm";

const fields: FieldConfig[] = [
  { name: "name", label: "Name", type: "text", required: true },
  { name: "company_name", label: "Company name", type: "text" },
  { name: "email", label: "Email", type: "text" },
  { name: "phone", label: "Phone", type: "text" },
  { name: "address", label: "Address", type: "text" },
  { name: "opening_balance", label: "Opening balance", type: "number" },
];

export default function NewSupplierPage() {
  return (
    <EntityForm
      title="New Supplier"
      fields={fields}
      initialValues={{ name: "", company_name: "", email: "", phone: "", address: "", opening_balance: 0 }}
      cancelHref="/suppliers"
      onSubmit={async (values) => {
        await api.post("/suppliers", values);
      }}
    />
  );
}
