"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import type { Role } from "@/types";
import { EntityForm, type FieldConfig } from "@/components/entity/EntityForm";

export default function NewUserPage() {
  const [fields, setFields] = useState<FieldConfig[] | null>(null);

  useEffect(() => {
    api.get<{ data: Role[] }>("/roles").then((res) => {
      setFields([
        { name: "name", label: "Name", type: "text", required: true },
        { name: "email", label: "Email", type: "text", required: true },
        { name: "password", label: "Password", type: "text", required: true },
        {
          name: "role",
          label: "Role",
          type: "select",
          required: true,
          options: res.data.map((r) => ({ value: r.name, label: r.name })),
        },
      ]);
    });
  }, []);

  if (!fields) return <p className="text-sm text-slate-500">Loading…</p>;

  return (
    <EntityForm
      title="Add User"
      fields={fields}
      initialValues={{ name: "", email: "", password: "", role: "" }}
      cancelHref="/users"
      onSubmit={async (values) => {
        await api.post("/users", values);
      }}
    />
  );
}
