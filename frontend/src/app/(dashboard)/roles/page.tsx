"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import type { Role } from "@/types";
import { Card } from "@/components/ui/Card";

export default function RolesPage() {
  const [roles, setRoles] = useState<Role[] | null>(null);

  useEffect(() => {
    api.get<{ data: Role[] }>("/roles").then((res) => setRoles(res.data));
  }, []);

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-slate-900">Roles & Permissions</h1>
        <p className="text-sm text-slate-500">
          Owner, Manager, and Staff are provisioned automatically for every business with a fixed permission set.
        </p>
      </div>

      {!roles ? (
        <p className="text-sm text-slate-500">Loading…</p>
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
          {roles.map((role) => (
            <Card key={role.id} className="p-5">
              <p className="text-base font-semibold text-slate-900">{role.name}</p>
              <p className="mb-3 text-xs text-slate-400">{role.permissions.length} permissions</p>
              <ul className="flex flex-wrap gap-1">
                {role.permissions.map((p) => (
                  <li key={p} className="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                    {p}
                  </li>
                ))}
              </ul>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
