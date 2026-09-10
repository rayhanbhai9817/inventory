"use client";

import { useCallback, useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";
import { api, ApiError } from "@/lib/api";
import type { MovementType, PaginatedResponse, StockMovement } from "@/types";
import { Card } from "@/components/ui/Card";
import { Input, Select } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";

const TYPES: { value: MovementType | ""; label: string }[] = [
  { value: "", label: "All Types" },
  { value: "stock_in", label: "Stock In" },
  { value: "stock_out", label: "Stock Out" },
  { value: "adjustment_increase", label: "Adjustment (increase)" },
  { value: "adjustment_decrease", label: "Adjustment (decrease)" },
];

export default function StockLedgerPage() {
  const searchParams = useSearchParams();
  const [type, setType] = useState(() => searchParams.get("type") ?? "");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [page, setPage] = useState(1);
  const [data, setData] = useState<StockMovement[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get<PaginatedResponse<StockMovement>>("/stock-ledger", {
        type: type || undefined,
        from: from || undefined,
        to: to || undefined,
        page,
      });
      setData(res.data);
      setTotal(res.meta.total);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to load ledger.");
    } finally {
      setLoading(false);
    }
  }, [type, from, to, page]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  function exportUrl() {
    const params = new URLSearchParams({ export: "true", format: "csv" });
    if (type) params.set("type", type);
    if (from) params.set("from", from);
    if (to) params.set("to", to);
    return `${process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"}/api/v1/stock-ledger?${params.toString()}`;
  }

  const perPage = 25;
  const lastPage = Math.max(1, Math.ceil(total / perPage));

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-slate-900">Stock Ledger</h1>
        <a href={exportUrl()} target="_blank" rel="noreferrer">
          <Button variant="secondary">Export CSV</Button>
        </a>
      </div>

      <div className="mb-4 flex flex-wrap gap-3">
        <Select
          value={type}
          onChange={(e) => {
            setPage(1);
            setType(e.target.value);
          }}
        >
          {TYPES.map((t) => (
            <option key={t.value} value={t.value}>
              {t.label}
            </option>
          ))}
        </Select>
        <Input
          type="date"
          value={from}
          onChange={(e) => {
            setPage(1);
            setFrom(e.target.value);
          }}
        />
        <Input
          type="date"
          value={to}
          onChange={(e) => {
            setPage(1);
            setTo(e.target.value);
          }}
        />
      </div>

      <Card>
        {error && <p className="p-4 text-sm text-red-600">{error}</p>}
        {loading ? (
          <p className="p-4 text-sm text-slate-500">Loading…</p>
        ) : data.length === 0 ? (
          <p className="p-4 text-sm text-slate-500">No movements found.</p>
        ) : (
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 text-slate-500">
              <tr>
                <th className="px-4 py-3 font-medium">Date</th>
                <th className="px-4 py-3 font-medium">Reference</th>
                <th className="px-4 py-3 font-medium">Product</th>
                <th className="px-4 py-3 font-medium">SKU</th>
                <th className="px-4 py-3 font-medium">Type</th>
                <th className="px-4 py-3 font-medium">Units</th>
                <th className="px-4 py-3 font-medium">Balance</th>
                <th className="px-4 py-3 font-medium">User</th>
              </tr>
            </thead>
            <tbody>
              {data.map((m) => {
                const isIn = m.type.includes("in") || m.type === "adjustment_increase";
                return (
                  <tr key={m.id} className="border-b border-slate-100 last:border-0">
                    <td className="px-4 py-3 text-slate-500">{new Date(m.created_at).toLocaleString()}</td>
                    <td className="px-4 py-3 font-mono text-xs text-slate-500">{m.reference}</td>
                    <td className="px-4 py-3 text-slate-900">{m.product?.name}</td>
                    <td className="px-4 py-3 text-slate-500">{m.product?.sku}</td>
                    <td className="px-4 py-3 text-slate-700">{m.type.replace("_", " ")}</td>
                    <td className={`px-4 py-3 font-medium ${isIn ? "text-emerald-600" : "text-red-600"}`}>
                      {isIn ? "+" : "-"}
                      {m.units}
                    </td>
                    <td className="px-4 py-3 text-slate-700">{m.balance_after}</td>
                    <td className="px-4 py-3 text-slate-500">{m.user}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </Card>

      {lastPage > 1 && (
        <div className="mt-4 flex items-center justify-between text-sm text-slate-500">
          <span>
            Page {page} of {lastPage} ({total} total)
          </span>
          <div className="flex gap-2">
            <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
              Previous
            </Button>
            <Button variant="secondary" disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)}>
              Next
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
