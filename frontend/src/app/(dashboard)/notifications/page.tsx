"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { AppNotification, PaginatedResponse } from "@/types";
import { Card } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";

const TYPE_STYLES: Record<string, string> = {
  low_stock: "bg-amber-100 text-amber-700",
  out_of_stock: "bg-red-100 text-red-700",
  stock_in: "bg-emerald-100 text-emerald-700",
  stock_out: "bg-slate-100 text-slate-700",
  adjustment: "bg-indigo-100 text-indigo-700",
  admin_activity: "bg-slate-100 text-slate-600",
  supplier_added: "bg-sky-100 text-sky-700",
  product_missing_supplier: "bg-amber-100 text-amber-700",
  product_missing_price: "bg-amber-100 text-amber-700",
};

export default function NotificationsPage() {
  const [data, setData] = useState<AppNotification[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get<PaginatedResponse<AppNotification>>("/notifications", { per_page: 30 });
      setData(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to load notifications.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  async function markRead(id: number) {
    await api.post(`/notifications/${id}/read`);
    await load();
  }

  async function markAllRead() {
    await api.post("/notifications/read-all");
    await load();
  }

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-slate-900">Notifications</h1>
        <Button variant="secondary" onClick={markAllRead}>
          Mark all as read
        </Button>
      </div>

      <Card>
        {error && <p className="p-4 text-sm text-red-600">{error}</p>}
        {loading ? (
          <p className="p-4 text-sm text-slate-500">Loading…</p>
        ) : data.length === 0 ? (
          <p className="p-4 text-sm text-slate-500">No notifications yet.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {data.map((n) => (
              <li key={n.id} className={`flex items-start justify-between gap-4 p-4 ${n.read ? "" : "bg-indigo-50/50"}`}>
                <div>
                  <span
                    className={`mb-1 inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase ${
                      TYPE_STYLES[n.type] ?? "bg-slate-100 text-slate-600"
                    }`}
                  >
                    {n.type.replace("_", " ")}
                  </span>
                  <p className="text-sm font-medium text-slate-900">{n.title}</p>
                  <p className="mt-0.5 text-xs text-slate-400">{new Date(n.created_at).toLocaleString()}</p>
                </div>
                {!n.read && (
                  <button onClick={() => markRead(n.id)} className="shrink-0 text-xs font-medium text-indigo-600 hover:underline">
                    Mark read
                  </button>
                )}
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  );
}
