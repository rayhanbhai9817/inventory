"use client";

import Link from "next/link";
import { ReactNode } from "react";
import { useAuth } from "@/lib/auth-context";
import { useEntityList } from "@/lib/use-entity-list";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Card } from "@/components/ui/Card";

export interface Column<T> {
  key: string;
  label: string;
  render: (row: T) => ReactNode;
}

interface EntityListPageProps<T extends { id: number }> {
  title: string;
  endpoint: string;
  module: string; // permission module name, e.g. "products"
  columns: Column<T>[];
  editHref: (row: T) => string;
  newHref: string;
}

export function EntityListPage<T extends { id: number }>({
  title,
  endpoint,
  module,
  columns,
  editHref,
  newHref,
}: EntityListPageProps<T>) {
  const { can } = useAuth();
  const { data, total, page, setPage, search, setSearch, loading, error, remove } =
    useEntityList<T>(endpoint);

  const perPage = 15;
  const lastPage = Math.max(1, Math.ceil(total / perPage));

  async function handleDelete(id: number) {
    if (!confirm("Delete this record? This cannot be undone.")) return;
    try {
      await remove(id);
    } catch (err) {
      alert(err instanceof Error ? err.message : "Delete failed.");
    }
  }

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-slate-900">{title}</h1>
        {can(`${module}.create`) && (
          <Link href={newHref}>
            <Button>New</Button>
          </Link>
        )}
      </div>

      <div className="mb-4 max-w-xs">
        <Input
          placeholder="Search…"
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
          <p className="p-4 text-sm text-slate-500">No records found.</p>
        ) : (
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 text-slate-500">
              <tr>
                {columns.map((col) => (
                  <th key={col.key} className="px-4 py-3 font-medium">
                    {col.label}
                  </th>
                ))}
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody>
              {data.map((row) => (
                <tr key={row.id} className="border-b border-slate-100 last:border-0">
                  {columns.map((col) => (
                    <td key={col.key} className="px-4 py-3 text-slate-700">
                      {col.render(row)}
                    </td>
                  ))}
                  <td className="px-4 py-3 text-right">
                    <div className="flex justify-end gap-3">
                      {can(`${module}.edit`) && (
                        <Link
                          href={editHref(row)}
                          className="text-indigo-600 hover:underline"
                        >
                          Edit
                        </Link>
                      )}
                      {can(`${module}.delete`) && (
                        <button
                          onClick={() => handleDelete(row.id)}
                          className="text-red-600 hover:underline"
                        >
                          Delete
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
            <Button
              variant="secondary"
              disabled={page <= 1}
              onClick={() => setPage((p) => p - 1)}
            >
              Previous
            </Button>
            <Button
              variant="secondary"
              disabled={page >= lastPage}
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
