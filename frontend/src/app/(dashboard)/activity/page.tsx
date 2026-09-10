"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { AuditLogEntry, PaginatedResponse } from "@/types";
import { AuditLogTable } from "@/components/entity/AuditLogTable";
import { Input } from "@/components/ui/Input";

export default function DailyActivityPage() {
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [data, setData] = useState<AuditLogEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get<PaginatedResponse<AuditLogEntry>>("/activity", { date });
      setData(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to load activity.");
    } finally {
      setLoading(false);
    }
  }, [date]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-slate-900">Daily Activity</h1>
        <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} />
      </div>

      <AuditLogTable loading={loading} error={error} data={data} />
    </div>
  );
}
