"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { ProductSupplierReportRow } from "@/types";
import { Card } from "@/components/ui/Card";
import { Badge } from "@/components/ui/Badge";

export default function ProductSupplierReportPage() {
  const [rows, setRows] = useState<ProductSupplierReportRow[]>([]);
  const [missingOnly, setMissingOnly] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<{ data: ProductSupplierReportRow[] }>("/reports/product-suppliers", {
        missing_supplier: missingOnly || undefined,
      });
      setRows(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to load report.");
    } finally {
      setLoading(false);
    }
  }, [missingOnly]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-slate-900">Product ↔ Supplier Report</h1>
        <p className="text-sm text-slate-500">Which suppliers each product is linked to, and its primary supplier.</p>
      </div>

      <label className="mb-4 flex items-center gap-2 text-sm text-slate-600">
        <input type="checkbox" checked={missingOnly} onChange={(e) => setMissingOnly(e.target.checked)} />
        Show only products missing a supplier
      </label>

      <Card>
        {error && <p className="p-4 text-sm text-red-600">{error}</p>}
        {loading ? (
          <p className="p-4 text-sm text-slate-500">Loading…</p>
        ) : rows.length === 0 ? (
          <p className="p-4 text-sm text-slate-500">No products found.</p>
        ) : (
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 text-slate-500">
              <tr>
                <th className="px-4 py-3 font-medium">Product</th>
                <th className="px-4 py-3 font-medium">Category</th>
                <th className="px-4 py-3 font-medium">Primary Supplier</th>
                <th className="px-4 py-3 font-medium">All Suppliers</th>
                <th className="px-4 py-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.product_id} className="border-b border-slate-100 last:border-0">
                  <td className="px-4 py-3">
                    <p className="font-medium text-slate-900">{r.product_name}</p>
                    <p className="text-xs text-slate-400">{r.sku}</p>
                  </td>
                  <td className="px-4 py-3 text-slate-600">{r.category || "—"}</td>
                  <td className="px-4 py-3 text-slate-700">{r.primary_supplier || "—"}</td>
                  <td className="px-4 py-3 text-slate-600">{r.suppliers.join(", ") || "—"}</td>
                  <td className="px-4 py-3">
                    <Badge status={r.has_supplier ? "active" : "inactive"} />
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
