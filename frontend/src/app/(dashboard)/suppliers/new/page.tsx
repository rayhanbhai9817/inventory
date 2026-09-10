"use client";

import { api } from "@/lib/api";
import { EntityForm, type FieldConfig } from "@/components/entity/EntityForm";

const fields: FieldConfig[] = [
  { name: "name", label: "Supplier Name", type: "text", required: true },
  { name: "company_name", label: "Company Name", type: "text" },
  { name: "contact_person", label: "Contact Person", type: "text" },
  { name: "email", label: "Email", type: "text" },
  { name: "phone", label: "Phone", type: "text" },
  { name: "address", label: "Address", type: "textarea" },
  { name: "city", label: "City", type: "text" },
  { name: "state", label: "State / Province", type: "text" },
  { name: "country", label: "Country", type: "text" },
  { name: "website", label: "Website", type: "text" },
  { name: "tax_number", label: "Tax Number", type: "text" },
  { name: "notes", label: "Notes", type: "textarea" },
];

export default function NewSupplierPage() {
  return (
    <EntityForm
      title="New Supplier"
      fields={fields}
      initialValues={{ name: "", company_name: "", contact_person: "", email: "", phone: "" }}
      cancelHref="/suppliers"
      onSubmit={async (values) => {
        await api.post("/suppliers", values);
      }}
    />
  );
}
