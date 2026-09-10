"use client";

import { Fragment, useCallback, useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";
import { api, ApiError } from "@/lib/api";
import type { InventoryRow, InventorySummary, PaginatedResponse } from "@/types";
import { Card, StatCard } from "@/components/ui/Card";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { StockStatusBadge } from "@/components/ui/Badge";
import { InventoryRowDetail } from "./InventoryRowDetail";

export default function InventoryControlCenterPage() {
  const searchParams = useSearchParams();
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState(() => searchParams.get("status") ?? "");
  const [page, setPage] = useState(1);
  const [rows, setRows] = useState<InventoryRow[]>([]);
  const [summary, setSummary] = useState<InventorySummary | null>(null);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [expanded, setExpanded] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get<PaginatedResponse<InventoryRow> & { summary: InventorySummary }>("/inventory", {
        search: search || undefined,
        status: status || undefined,
        page,
      });
      setRows(res.data);
      setTotal(res.meta.total);
      setSummary(res.summary);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to load inventory.");
    } finally {
      setLoading(false);
    }
  }, [search, status, page]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  function exportUrl() {
    const params = new URLSearchParams({ export: "true", format: "csv" });
    return `${process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"}/api/v1/inventory?${params.toString()}`;
  }

  const perPage = 15;
  const lastPage = Math.max(1, Math.ceil(total / perPage));

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">Inventory Control Center</h1>
          <p className="text-sm text-slate-500">Real-time stock management and movement analytics.</p>
        </div>
        <a href={exportUrl()} target="_blank" rel="noreferrer">
          <Button variant="secondary">Export</Button>
        </a>
      </div>

      {summary && (
        <div className="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
          <StatCard label="Total Products" value={summary.total_products} />
          <StatCard label="Warehouse Boxes" value={summary.total_boxes} />
          <StatCard label="Available Units" value={summary.available_units} />
          <StatCard label="Low Stock" value={summary.low_stock} />
          <StatCard label="Out of Stock" value={summary.out_of_stock} />
        </div>
      )}

      <div className="mb-4 flex flex-wrap gap-3">
        <div className="w-64">
          <Input
            placeholder="Search SKU or Name…"
            value={search}
            onChange={(e) => {
              setPage(1);
              setSearch(e.target.value);
            }}
          />
        </div>
        <select
          value={status}
          onChange={(e) => {
            setPage(1);
            setStatus(e.target.value);
          }}
          className="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option value="">All Status</option>
          <option value="in_stock">In Stock</option>
          <option value="low_stock">Low Stock</option>
          <option value="out_of_stock">Out of Stock</option>
        </select>
      </div>

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
                <th className="px-4 py-3 font-medium">Boxes</th>
                <th className="px-4 py-3 font-medium">Units/Box</th>
                <th className="px-4 py-3 font-medium">Total Units</th>
                <th className="px-4 py-3 font-medium">Stock In</th>
                <th className="px-4 py-3 font-medium">Stock Out</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium" />
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <Fragment key={row.id}>
                  <tr className="border-b border-slate-100 last:border-0">
                    <td className="px-4 py-3 font-medium text-slate-900">{row.name}</td>
                    <td className="px-4 py-3 text-slate-500">{row.category ?? "—"}</td>
                    <td className="px-4 py-3 text-slate-700">{row.boxes}</td>
                    <td className="px-4 py-3 text-slate-400">{row.units_per_box ?? "—"}</td>
                    <td className="px-4 py-3 font-medium text-slate-900">{row.total_units}</td>
                    <td className="px-4 py-3 text-emerald-600">+{row.stock_in_total}</td>
                    <td className="px-4 py-3 text-red-600">-{row.stock_out_total}</td>
                    <td className="px-4 py-3">
                      <StockStatusBadge status={row.status} />
                    </td>
                    <td className="px-4 py-3 text-right">
                      <button
                        onClick={() => setExpanded(expanded === row.id ? null : row.id)}
                        className="text-slate-400 hover:text-slate-700"
                        aria-label="Toggle details"
                      >
                        {expanded === row.id ? "▲" : "▼"}
                      </button>
                    </td>
                  </tr>
                  {expanded === row.id && (
                    <tr>
                      <td colSpan={9} className="p-0">
                        <InventoryRowDetail productId={row.id} />
                      </td>
                    </tr>
                  )}
                </Fragment>
              ))}
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
