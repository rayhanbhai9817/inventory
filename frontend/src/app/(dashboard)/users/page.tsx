"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { api, ApiError } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import type { PaginatedResponse, User } from "@/types";
import { Card } from "@/components/ui/Card";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";

export default function AdminUsersPage() {
  const { can, user: currentUser } = useAuth();
  const [data, setData] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get<PaginatedResponse<User>>("/users", { per_page: 50 });
      setData(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to load users.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  async function toggleStatus(u: User) {
    const next = u.status === "active" ? "inactive" : "active";
    try {
      await api.patch(`/users/${u.id}/status`, { status: next });
    } catch (err) {
      alert(err instanceof ApiError ? err.message : "Failed to update status.");
    }
    await load();
  }

  async function resetPassword(u: User) {
    const password = prompt(`New password for ${u.name} (min 8 characters):`);
    if (!password) return;
    try {
      await api.post(`/users/${u.id}/reset-password`, { password });
      alert("Password reset.");
    } catch (err) {
      alert(err instanceof ApiError ? err.message : "Failed to reset password.");
    }
  }

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-slate-900">Admin Users</h1>
        {can("users.create") && (
          <Link href="/users/new">
            <Button>Add User</Button>
          </Link>
        )}
      </div>

      <Card>
        {error && <p className="p-4 text-sm text-red-600">{error}</p>}
        {loading ? (
          <p className="p-4 text-sm text-slate-500">Loading…</p>
        ) : (
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 text-slate-500">
              <tr>
                <th className="px-4 py-3 font-medium">Name</th>
                <th className="px-4 py-3 font-medium">Email</th>
                <th className="px-4 py-3 font-medium">Role</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Last Login</th>
                <th className="px-4 py-3 font-medium" />
              </tr>
            </thead>
            <tbody>
              {data.map((u) => (
                <tr key={u.id} className="border-b border-slate-100 last:border-0">
                  <td className="px-4 py-3 font-medium text-slate-900">{u.name}</td>
                  <td className="px-4 py-3 text-slate-500">{u.email}</td>
                  <td className="px-4 py-3 text-slate-700">{u.roles?.join(", ")}</td>
                  <td className="px-4 py-3">
                    <Badge status={u.status} />
                  </td>
                  <td className="px-4 py-3 text-slate-500">
                    {u.last_login_at ? new Date(u.last_login_at).toLocaleString() : "Never"}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <div className="flex justify-end gap-3">
                      {can("users.edit") && (
                        <>
                          <Link href={`/users/${u.id}/edit`} className="text-indigo-600 hover:underline">
                            Edit
                          </Link>
                          <button onClick={() => resetPassword(u)} className="text-slate-600 hover:underline">
                            Reset password
                          </button>
                          {u.id !== currentUser?.id && (
                            <button onClick={() => toggleStatus(u)} className="text-slate-600 hover:underline">
                              {u.status === "active" ? "Deactivate" : "Activate"}
                            </button>
                          )}
                        </>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  );
}
