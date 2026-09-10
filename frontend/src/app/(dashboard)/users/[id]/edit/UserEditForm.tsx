"use client";

import { useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { Role, SingleResponse, User } from "@/types";
import { EntityForm, type FieldConfig } from "@/components/entity/EntityForm";

export function UserEditForm({ id }: { id: number }) {
  const [fields, setFields] = useState<FieldConfig[] | null>(null);
  const [initialValues, setInitialValues] = useState<Record<string, unknown> | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    Promise.all([api.get<{ data: Role[] }>("/roles"), api.get<SingleResponse<User>>(`/users/${id}`)])
      .then(([roles, res]) => {
        if (cancelled) return;
        const user = res.data;
        setFields([
          { name: "name", label: "Name", type: "text", required: true },
          { name: "email", label: "Email", type: "text", required: true },
          { name: "password", label: "New password (leave blank to keep current)", type: "text" },
          {
            name: "role",
            label: "Role",
            type: "select",
            required: true,
            options: roles.data.map((r) => ({ value: r.name, label: r.name })),
          },
        ]);
        setInitialValues({
          name: user.name,
          email: user.email,
          password: "",
          role: user.roles?.[0] ?? "",
        });
      })
      .catch((err) => {
        if (!cancelled) setError(err instanceof ApiError ? err.message : "Failed to load user.");
      });

    return () => {
      cancelled = true;
    };
  }, [id]);

  if (error) return <p className="text-sm text-red-600">{error}</p>;
  if (!fields || !initialValues) return <p className="text-sm text-slate-500">Loading…</p>;

  return (
    <EntityForm
      title="Edit User"
      fields={fields}
      initialValues={initialValues}
      cancelHref="/users"
      onSubmit={async (values) => {
        const payload = { ...values };
        if (!payload.password) delete payload.password;
        await api.put(`/users/${id}`, payload);
      }}
    />
  );
}
