"use client";

import { EntityEditPage } from "@/components/entity/EntityEditPage";
import type { FieldConfig } from "@/components/entity/EntityForm";
import type { Supplier } from "@/types";

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

export function SupplierEditForm({ id }: { id: number }) {
  return (
    <EntityEditPage<Supplier>
      id={id}
      endpoint="/suppliers"
      title="Edit Supplier"
      fields={fields}
      cancelHref="/suppliers"
      toValues={(supplier) => ({
        name: supplier.name,
        company_name: supplier.company_name ?? "",
        contact_person: supplier.contact_person ?? "",
        email: supplier.email ?? "",
        phone: supplier.phone ?? "",
        address: supplier.address ?? "",
        city: supplier.city ?? "",
        state: supplier.state ?? "",
        country: supplier.country ?? "",
        website: supplier.website ?? "",
        tax_number: supplier.tax_number ?? "",
        notes: supplier.notes ?? "",
        status: supplier.status === "archived" ? "active" : supplier.status,
      })}
    />
  );
}
