"use client";

import { useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { SupplierReportRow } from "@/types";
import { Card } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";

export default function SupplierReportPage() {
  const [rows, setRows] = useState<SupplierReportRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .get<{ data: SupplierReportRow[] }>("/reports/suppliers")
      .then((res) => setRows(res.data))
      .catch((err) => setError(err instanceof ApiError ? err.message : "Failed to load report."))
      .finally(() => setLoading(false));
  }, []);

  function exportUrl() {
    const params = new URLSearchParams({ export: "true", format: "csv" });
    return `${process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"}/api/v1/reports/suppliers?${params.toString()}`;
  }

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">Supplier Report</h1>
          <p className="text-sm text-slate-500">Products supplied and total units received, per supplier.</p>
        </div>
        <a href={exportUrl()} target="_blank" rel="noreferrer">
          <Button variant="secondary">Export CSV</Button>
        </a>
      </div>

      <Card>
        {error && <p className="p-4 text-sm text-red-600">{error}</p>}
        {loading ? (
          <p className="p-4 text-sm text-slate-500">Loading…</p>
        ) : rows.length === 0 ? (
          <p className="p-4 text-sm text-slate-500">No suppliers yet.</p>
        ) : (
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 text-slate-500">
              <tr>
                <th className="px-4 py-3 font-medium">Supplier</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Products Supplied</th>
                <th className="px-4 py-3 font-medium">Batches Received</th>
                <th className="px-4 py-3 font-medium">Total Units Supplied</th>
                <th className="px-4 py-3 font-medium">Last Stock In</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.supplier_id} className="border-b border-slate-100 last:border-0">
                  <td className="px-4 py-3 font-medium text-slate-900">{r.supplier_name}</td>
                  <td className="px-4 py-3">
                    <Badge status={r.status} />
                  </td>
                  <td className="px-4 py-3 text-slate-700">{r.products_supplied}</td>
                  <td className="px-4 py-3 text-slate-700">{r.batches_received}</td>
                  <td className="px-4 py-3 font-medium text-slate-900">{r.total_units_supplied}</td>
                  <td className="px-4 py-3 text-slate-500">{r.last_stock_in_at || "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  );
}
