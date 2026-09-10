"use client";

import type { AuditLogEntry } from "@/types";
import { Card } from "@/components/ui/Card";

export function AuditLogTable({
  loading,
  error,
  data,
}: {
  loading: boolean;
  error: string | null;
  data: AuditLogEntry[];
}) {
  return (
    <Card>
      {error && <p className="p-4 text-sm text-red-600">{error}</p>}
      {loading ? (
        <p className="p-4 text-sm text-slate-500">Loading…</p>
      ) : data.length === 0 ? (
        <p className="p-4 text-sm text-slate-500">No activity found.</p>
      ) : (
        <table className="w-full text-left text-sm">
          <thead className="border-b border-slate-200 text-slate-500">
            <tr>
              <th className="px-4 py-3 font-medium">Date</th>
              <th className="px-4 py-3 font-medium">User</th>
              <th className="px-4 py-3 font-medium">Action</th>
              <th className="px-4 py-3 font-medium">Module</th>
              <th className="px-4 py-3 font-medium">Reference</th>
            </tr>
          </thead>
          <tbody>
            {data.map((entry) => (
              <tr key={entry.id} className="border-b border-slate-100 last:border-0">
                <td className="px-4 py-3 text-slate-500">{new Date(entry.created_at).toLocaleString()}</td>
                <td className="px-4 py-3 text-slate-900">{entry.user ?? "System"}</td>
                <td className="px-4 py-3 text-slate-700">{entry.action.replace(/_/g, " ")}</td>
                <td className="px-4 py-3 text-slate-500">{entry.module ?? "—"}</td>
                <td className="px-4 py-3 text-slate-400">{entry.reference ?? "—"}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </Card>
  );
}
