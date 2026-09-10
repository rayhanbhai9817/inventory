"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { api, ApiError } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import type { PaginatedResponse, Product, ProductLifecycleStatus } from "@/types";
import { Card } from "@/components/ui/Card";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";

const TABS: { value: ProductLifecycleStatus; label: string }[] = [
  { value: "active", label: "Active" },
  { value: "archived", label: "Archived" },
  { value: "trashed", label: "Trashed" },
];

export default function ProductsPage() {
  const { can } = useAuth();
  const [tab, setTab] = useState<ProductLifecycleStatus>("active");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [data, setData] = useState<Product[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get<PaginatedResponse<Product>>("/products", {
        tab,
        search: search || undefined,
        page,
      });
      setData(res.data);
      setTotal(res.meta.total);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to load products.");
    } finally {
      setLoading(false);
    }
  }, [tab, search, page]);

  useEffect(() => {
    // Fetch-on-dependency-change: load() sets state asynchronously.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  async function handleAction(action: string, product: Product) {
    try {
      if (action === "archive") await api.post(`/products/${product.id}/archive`);
      if (action === "restore") await api.post(`/products/${product.id}/restore`);
      if (action === "trash") {
        if (!confirm(`Move "${product.name}" to trash?`)) return;
        await api.delete(`/products/${product.id}`);
      }
      if (action === "restore-from-trash") await api.post(`/products/trashed/${product.id}/restore`);
      await load();
    } catch (err) {
      alert(err instanceof ApiError ? err.message : "Action failed.");
    }
  }

  function exportUrl() {
    const params = new URLSearchParams({ tab, export: "true", format: "csv" });
    return `${process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"}/api/v1/products?${params.toString()}`;
  }

  const perPage = 15;
  const lastPage = Math.max(1, Math.ceil(total / perPage));

  return (
    <div>
      <div className="mb-2 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">Catalogue Management</h1>
          <p className="text-sm text-slate-500">Master product directory for inventory identification.</p>
        </div>
        <div className="flex gap-3">
          <a href={exportUrl()} target="_blank" rel="noreferrer">
            <Button variant="secondary">Export CSV</Button>
          </a>
          {can("products.create") && (
            <Link href="/products/new">
              <Button>+ Add Product</Button>
            </Link>
          )}
        </div>
      </div>

      <div className="my-4 flex gap-1 border-b border-slate-200">
        {TABS.map((t) => (
          <button
            key={t.value}
            onClick={() => {
              setTab(t.value);
              setPage(1);
            }}
            className={`px-4 py-2 text-sm font-medium ${
              tab === t.value
                ? "border-b-2 border-indigo-600 text-indigo-700"
                : "text-slate-500 hover:text-slate-700"
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      <div className="mb-4 max-w-xs">
        <Input
          placeholder="Filter by name or SKU"
          value={search}
          onChange={(e) => {
            setPage(1);
            setSearch(e.target.value);
          }}
        />
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
                <th className="px-4 py-3 font-medium">SKU</th>
                <th className="px-4 py-3 font-medium">Product</th>
                <th className="px-4 py-3 font-medium">Description</th>
                <th className="px-4 py-3 font-medium">Boxes</th>
                <th className="px-4 py-3 font-medium">Total Units</th>
                <th className="px-4 py-3 font-medium" />
              </tr>
            </thead>
            <tbody>
              {data.map((p) => (
                <tr key={p.id} className="border-b border-slate-100 last:border-0">
                  <td className="px-4 py-3 text-slate-700">{p.sku}</td>
                  <td className="px-4 py-3">
                    <p className="font-medium text-slate-900">{p.name}</p>
                    {p.category && (
                      <p className="text-xs uppercase tracking-wide text-slate-400">{p.category.name}</p>
                    )}
                  </td>
                  <td className="px-4 py-3 text-slate-500">{p.description || "—"}</td>
                  <td className="px-4 py-3 text-slate-700">{p.total_boxes ?? "—"}</td>
                  <td className="px-4 py-3 text-slate-700">{p.total_units ?? "—"}</td>
                  <td className="px-4 py-3">
                    <div className="flex justify-end gap-3">
                      {tab === "active" && can("products.edit") && (
                        <Link href={`/products/${p.id}/edit`} className="text-indigo-600 hover:underline">
                          Edit
                        </Link>
                      )}
                      {tab === "active" && can("products.delete") && (
                        <>
                          <button onClick={() => handleAction("archive", p)} className="text-slate-600 hover:underline">
                            Archive
                          </button>
                          <button onClick={() => handleAction("trash", p)} className="text-red-600 hover:underline">
                            Trash
                          </button>
                        </>
                      )}
                      {tab === "archived" && can("products.delete") && (
                        <button onClick={() => handleAction("restore", p)} className="text-indigo-600 hover:underline">
                          Restore
                        </button>
                      )}
                      {tab === "trashed" && can("products.delete") && (
                        <button
                          onClick={() => handleAction("restore-from-trash", p)}
                          className="text-indigo-600 hover:underline"
                        >
                          Restore
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
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
