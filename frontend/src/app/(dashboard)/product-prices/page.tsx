"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { api, ApiError } from "@/lib/api";
import type { ProductPriceCatalogRow } from "@/types";
import { Card } from "@/components/ui/Card";
import { Input, Select } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";

interface CatalogResponse {
  data: ProductPriceCatalogRow[];
  meta: { current_page: number; last_page: number; total: number };
}

export default function ProductPriceCatalogPage() {
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [missingOnly, setMissingOnly] = useState(false);
  const [page, setPage] = useState(1);
  const [data, setData] = useState<ProductPriceCatalogRow[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get<CatalogResponse>("/product-price-catalog", {
        search: search || undefined,
        status: status || undefined,
        missing_price: missingOnly || undefined,
        page,
      });
      setData(res.data);
      setMeta(res.meta);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to load price catalog.");
    } finally {
      setLoading(false);
    }
  }, [search, status, missingOnly, page]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  function exportUrl() {
    const params = new URLSearchParams({ export: "true", format: "csv" });
    if (search) params.set("search", search);
    if (status) params.set("status", status);
    return `${process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"}/api/v1/product-price-catalog?${params.toString()}`;
  }

  return (
    <div>
      <div className="mb-2 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">Product Price Catalog</h1>
          <p className="text-sm text-slate-500">
            Reference pricing only — never affects inventory quantity, FIFO, or stock valuation.
          </p>
        </div>
        <a href={exportUrl()} target="_blank" rel="noreferrer">
          <Button variant="secondary">Export CSV</Button>
        </a>
      </div>

      <div className="mb-4 flex flex-wrap gap-3">
        <div className="w-64">
          <Input
            placeholder="Search by name or SKU"
            value={search}
            onChange={(e) => {
              setPage(1);
              setSearch(e.target.value);
            }}
          />
        </div>
        <Select
          value={status}
          onChange={(e) => {
            setPage(1);
            setStatus(e.target.value);
          }}
        >
          <option value="">All Products</option>
          <option value="active">Active</option>
          <option value="archived">Archived</option>
        </Select>
        <label className="flex items-center gap-2 text-sm text-slate-600">
          <input
            type="checkbox"
            checked={missingOnly}
            onChange={(e) => {
              setPage(1);
              setMissingOnly(e.target.checked);
            }}
          />
          Missing price only
        </label>
      </div>

      <Card>
        {error && <p className="p-4 text-sm text-red-600">{error}</p>}
        {loading ? (
          <p className="p-4 text-sm text-slate-500">Loading…</p>
        ) : data.length === 0 ? (
          <p className="p-4 text-sm text-slate-500">No products found.</p>
        ) : (
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 text-slate-500">
              <tr>
                <th className="px-4 py-3 font-medium">Product</th>
                <th className="px-4 py-3 font-medium">Category</th>
                <th className="px-4 py-3 font-medium">Current Price</th>
                <th className="px-4 py-3 font-medium">Effective Date</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium" />
              </tr>
            </thead>
            <tbody>
              {data.map((row) => (
                <tr key={row.product_id} className="border-b border-slate-100 last:border-0">
                  <td className="px-4 py-3">
                    <p className="font-medium text-slate-900">{row.product_name}</p>
                    <p className="text-xs text-slate-400">{row.sku}</p>
                  </td>
                  <td className="px-4 py-3 text-slate-600">{row.category || "—"}</td>
                  <td className="px-4 py-3 font-medium text-slate-900">
                    {row.has_price ? `${row.currency} ${row.current_price}` : "—"}
                  </td>
                  <td className="px-4 py-3 text-slate-500">{row.effective_date || "—"}</td>
                  <td className="px-4 py-3">
                    <Badge status={row.has_price ? "active" : "inactive"} />
                  </td>
                  <td className="px-4 py-3 text-right">
                    <Link href={`/product-prices/${row.product_id}`} className="text-indigo-600 hover:underline">
                      {row.has_price ? "View History" : "Set Price"}
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>

      {meta.last_page > 1 && (
        <div className="mt-4 flex items-center justify-between text-sm text-slate-500">
          <span>
            Page {meta.current_page} of {meta.last_page} ({meta.total} total)
          </span>
          <div className="flex gap-2">
            <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
              Previous
            </Button>
            <Button
              variant="secondary"
              disabled={page >= meta.last_page}
              onClick={() => setPage((p) => p + 1)}
            >
              Next
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
