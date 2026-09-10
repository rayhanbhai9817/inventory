"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { api, ApiError } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import { formatDate } from "@/lib/format";
import type { PaginatedResponse, Product, SingleResponse, SupplierDetail } from "@/types";
import { Card } from "@/components/ui/Card";
import { Select } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";

export function SupplierDetailView({ id }: { id: number }) {
  const { can } = useAuth();
  const [detail, setDetail] = useState<SupplierDetail | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [products, setProducts] = useState<Product[]>([]);
  const [linkProductId, setLinkProductId] = useState("");
  const [linking, setLinking] = useState(false);
  const [linkError, setLinkError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const res = await api.get<SingleResponse<SupplierDetail>>(`/suppliers/${id}`);
      setDetail(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to load supplier.");
    }
  }, [id]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
    api.get<PaginatedResponse<Product>>("/products", { tab: "active", per_page: 200 }).then((res) => {
      setProducts(res.data);
    });
  }, [load]);

  async function handleLinkProduct(e: React.FormEvent) {
    e.preventDefault();
    if (!linkProductId) return;
    setLinking(true);
    setLinkError(null);
    try {
      await api.post("/supplier-products", { supplier_id: id, product_id: Number(linkProductId) });
      setLinkProductId("");
      await load();
    } catch (err) {
      setLinkError(err instanceof ApiError ? err.message : "Failed to link product.");
    } finally {
      setLinking(false);
    }
  }

  if (error) return <p className="text-sm text-red-600">{error}</p>;
  if (!detail) return <p className="text-sm text-slate-500">Loading…</p>;

  const { supplier, supplied_products, totals, recent_stock_in } = detail;
  const linkedProductIds = new Set(supplied_products.map((p) => p.product_id));
  const linkableProducts = products.filter((p) => !linkedProductIds.has(p.id));

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">{supplier.name}</h1>
          <p className="text-sm text-slate-500">{supplier.company_name || "Supplier profile & history"}</p>
        </div>
        <div className="flex items-center gap-3">
          <Badge status={supplier.status} />
          {supplier.status !== "archived" && can("suppliers.update") && (
            <Link href={`/suppliers/${supplier.id}/edit`}>
              <Button variant="secondary">Edit</Button>
            </Link>
          )}
        </div>
      </div>

      <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-3">
        <Card className="p-5">
          <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Supplier Info</p>
          <Row label="Contact" value={supplier.contact_person || "—"} />
          <Row label="Email" value={supplier.email || "—"} />
          <Row label="Phone" value={supplier.phone || "—"} />
          <Row label="Country" value={supplier.country || "—"} />
        </Card>
        <Card className="p-5">
          <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Totals</p>
          <Row label="Products Supplied" value={totals.total_products_supplied} />
          <Row label="Total Units Supplied" value={totals.total_stock_in_quantity} />
        </Card>
        <Card className="p-5">
          <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Notes</p>
          <p className="text-sm text-slate-600">{supplier.notes || "No notes on file."}</p>
        </Card>
      </div>

      <Card className="mb-4">
        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
          <p className="text-sm font-semibold text-slate-700">Supplied Products</p>
          {can("suppliers.update") && linkableProducts.length > 0 && (
            <form onSubmit={handleLinkProduct} className="flex items-center gap-2">
              <Select
                value={linkProductId}
                onChange={(e) => setLinkProductId(e.target.value)}
                className="w-56 py-1.5 text-xs"
              >
                <option value="">Link a product…</option>
                {linkableProducts.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name} ({p.sku})
                  </option>
                ))}
              </Select>
              <Button type="submit" disabled={linking || !linkProductId} className="py-1.5 text-xs">
                {linking ? "Linking…" : "Link"}
              </Button>
            </form>
          )}
        </div>
        {linkError && <p className="px-4 pt-2 text-xs text-red-600">{linkError}</p>}
        {supplied_products.length === 0 ? (
          <p className="p-4 text-sm text-slate-500">No products linked to this supplier yet.</p>
        ) : (
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 text-slate-500">
              <tr>
                <th className="px-4 py-2 font-medium">Product</th>
                <th className="px-4 py-2 font-medium">Supplier SKU</th>
                <th className="px-4 py-2 font-medium">Primary</th>
                <th className="px-4 py-2 font-medium">First Supplied</th>
                <th className="px-4 py-2 font-medium">Last Supplied</th>
              </tr>
            </thead>
            <tbody>
              {supplied_products.map((sp) => (
                <tr key={sp.product_id} className="border-t border-slate-100">
                  <td className="px-4 py-2">
                    <Link href={`/inventory?search=${encodeURIComponent(sp.sku ?? "")}`} className="text-indigo-600 hover:underline">
                      {sp.product_name}
                    </Link>{" "}
                    <span className="text-xs text-slate-400">({sp.sku})</span>
                  </td>
                  <td className="px-4 py-2 text-slate-600">{sp.supplier_sku || "—"}</td>
                  <td className="px-4 py-2">{sp.is_primary ? <Badge status="active" /> : "—"}</td>
                  <td className="px-4 py-2 text-slate-500">
                    {sp.first_supplied_at ? formatDate(sp.first_supplied_at) : "—"}
                  </td>
                  <td className="px-4 py-2 text-slate-500">
                    {sp.last_supplied_at ? formatDate(sp.last_supplied_at) : "—"}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>

      <Card>
        <div className="border-b border-slate-200 px-4 py-3">
          <p className="text-sm font-semibold text-slate-700">Recent Stock In</p>
        </div>
        {recent_stock_in.length === 0 ? (
          <p className="p-4 text-sm text-slate-500">No stock received from this supplier yet.</p>
        ) : (
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 text-slate-500">
              <tr>
                <th className="px-4 py-2 font-medium">Batch</th>
                <th className="px-4 py-2 font-medium">Product</th>
                <th className="px-4 py-2 font-medium">Received</th>
                <th className="px-4 py-2 font-medium">Units</th>
              </tr>
            </thead>
            <tbody>
              {recent_stock_in.map((b) => (
                <tr key={b.id} className="border-t border-slate-100">
                  <td className="px-4 py-2 font-mono text-xs text-slate-500">#{b.batch_code}</td>
                  <td className="px-4 py-2 text-slate-700">{b.product?.name}</td>
                  <td className="px-4 py-2 text-slate-500">{formatDate(b.received_at)}</td>
                  <td className="px-4 py-2 font-medium text-slate-900">{b.total_units}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  );
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between text-sm">
      <span className="text-slate-500">{label}</span>
      <span className="font-medium text-slate-900">{value}</span>
    </div>
  );
}
