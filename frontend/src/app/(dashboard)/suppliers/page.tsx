"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { api, ApiError } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import type { PaginatedResponse, Supplier } from "@/types";
import { Card } from "@/components/ui/Card";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";

const TABS: { value: "active" | "archived" | "all"; label: string }[] = [
  { value: "active", label: "Active" },
  { value: "archived", label: "Archived" },
  { value: "all", label: "All" },
];

export default function SuppliersPage() {
  const { can } = useAuth();
  const [tab, setTab] = useState<"active" | "archived" | "all">("active");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [data, setData] = useState<Supplier[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get<PaginatedResponse<Supplier>>("/suppliers", {
        tab,
        search: search || undefined,
        page,
      });
      setData(res.data);
      setTotal(res.meta.total);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to load suppliers.");
    } finally {
      setLoading(false);
    }
  }, [tab, search, page]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  async function handleAction(action: "archive" | "restore", supplier: Supplier) {
    try {
      await api.post(`/suppliers/${supplier.id}/${action}`);
      await load();
    } catch (err) {
      alert(err instanceof ApiError ? err.message : "Action failed.");
    }
  }

  function exportUrl() {
    const params = new URLSearchParams({ tab, export: "true", format: "csv" });
    return `${process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"}/api/v1/suppliers?${params.toString()}`;
  }

  const perPage = 15;
  const lastPage = Math.max(1, Math.ceil(total / perPage));

  return (
    <div>
      <div className="mb-2 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">Suppliers</h1>
          <p className="text-sm text-slate-500">Vendors and manufacturers that supply your products.</p>
        </div>
        <div className="flex gap-3">
          <a href={exportUrl()} target="_blank" rel="noreferrer">
            <Button variant="secondary">Export CSV</Button>
          </a>
          {can("suppliers.create") && (
            <Link href="/suppliers/new">
              <Button>+ Add Supplier</Button>
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
          placeholder="Search by name, company, or email"
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
          <p className="p-4 text-sm text-slate-500">No suppliers found.</p>
        ) : (
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 text-slate-500">
              <tr>
                <th className="px-4 py-3 font-medium">Name</th>
                <th className="px-4 py-3 font-medium">Contact</th>
                <th className="px-4 py-3 font-medium">Products</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium" />
              </tr>
            </thead>
            <tbody>
              {data.map((s) => (
                <tr key={s.id} className="border-b border-slate-100 last:border-0">
                  <td className="px-4 py-3">
                    <Link href={`/suppliers/${s.id}`} className="font-medium text-indigo-600 hover:underline">
                      {s.name}
                    </Link>
                    {s.company_name && <p className="text-xs text-slate-400">{s.company_name}</p>}
                  </td>
                  <td className="px-4 py-3 text-slate-600">
                    {s.email || "—"}
                    {s.phone && <p className="text-xs text-slate-400">{s.phone}</p>}
                  </td>
                  <td className="px-4 py-3 text-slate-700">{s.product_count ?? 0}</td>
                  <td className="px-4 py-3">
                    <Badge status={s.status} />
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex justify-end gap-3">
                      {s.status !== "archived" && can("suppliers.update") && (
                        <Link href={`/suppliers/${s.id}/edit`} className="text-indigo-600 hover:underline">
                          Edit
                        </Link>
                      )}
                      {s.status !== "archived" && can("suppliers.archive") && (
                        <button onClick={() => handleAction("archive", s)} className="text-red-600 hover:underline">
                          Archive
                        </button>
                      )}
                      {s.status === "archived" && can("suppliers.archive") && (
                        <button
                          onClick={() => handleAction("restore", s)}
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
