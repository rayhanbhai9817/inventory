"use client";

import { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { api, ApiError } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import type { PaginatedResponse, Product, ProductPrice, SingleResponse } from "@/types";
import { Card } from "@/components/ui/Card";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";

export function ProductPriceHistoryView({ productId }: { productId: number }) {
  const router = useRouter();
  const { can } = useAuth();
  const [product, setProduct] = useState<Product | null>(null);
  const [history, setHistory] = useState<ProductPrice[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [price, setPrice] = useState("");
  const [effectiveDate, setEffectiveDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [notes, setNotes] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitting, setSubmitting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [productRes, historyRes] = await Promise.all([
        api.get<SingleResponse<Product>>(`/products/${productId}`),
        api.get<PaginatedResponse<ProductPrice>>(`/products/${productId}/prices`),
      ]);
      setProduct(productRes.data);
      setHistory(historyRes.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to load price history.");
    } finally {
      setLoading(false);
    }
  }, [productId]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setFieldErrors({});
    setSubmitting(true);
    try {
      await api.post(`/products/${productId}/prices`, {
        price,
        effective_date: effectiveDate,
        notes: notes || undefined,
      });
      setPrice("");
      setNotes("");
      await load();
    } catch (err) {
      if (err instanceof ApiError) setFieldErrors(err.fieldErrors());
    } finally {
      setSubmitting(false);
    }
  }

  if (error) return <p className="text-sm text-red-600">{error}</p>;
  if (loading || !product) return <p className="text-sm text-slate-500">Loading…</p>;

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">{product.name} — Price History</h1>
          <p className="text-sm text-slate-500">
            SKU {product.sku}. Every entry is permanent — recording a new price never overwrites prior history.
          </p>
        </div>
        <Button variant="secondary" onClick={() => router.push("/product-prices")}>
          Back to Catalog
        </Button>
      </div>

      {can("product_prices.create") && (
        <Card className="mb-4 max-w-xl p-6">
          <p className="mb-4 text-sm font-semibold text-slate-700">Record New Price</p>
          <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            <div className="grid grid-cols-2 gap-4">
              <Input
                label="Price"
                type="number"
                step="0.01"
                min={0}
                required
                value={price}
                error={fieldErrors.price}
                onChange={(e) => setPrice(e.target.value)}
              />
              <Input
                label="Effective Date"
                type="date"
                required
                value={effectiveDate}
                error={fieldErrors.effective_date}
                onChange={(e) => setEffectiveDate(e.target.value)}
              />
            </div>
            <div className="flex flex-col gap-1">
              <label className="text-sm font-medium text-slate-700">Notes</label>
              <textarea
                className="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                rows={2}
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
              />
            </div>
            <div>
              <Button type="submit" disabled={submitting || !price}>
                {submitting ? "Saving…" : "Record Price"}
              </Button>
            </div>
          </form>
        </Card>
      )}

      <Card>
        <div className="border-b border-slate-200 px-4 py-3">
          <p className="text-sm font-semibold text-slate-700">Price History</p>
        </div>
        {history.length === 0 ? (
          <p className="p-4 text-sm text-slate-500">No prices recorded yet.</p>
        ) : (
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 text-slate-500">
              <tr>
                <th className="px-4 py-2 font-medium">Effective Date</th>
                <th className="px-4 py-2 font-medium">Price</th>
                <th className="px-4 py-2 font-medium">Notes</th>
                <th className="px-4 py-2 font-medium">Recorded By</th>
              </tr>
            </thead>
            <tbody>
              {history.map((p) => (
                <tr key={p.id} className="border-t border-slate-100">
                  <td className="px-4 py-2 text-slate-700">{p.effective_date}</td>
                  <td className="px-4 py-2 font-medium text-slate-900">
                    {p.currency} {p.price}
                  </td>
                  <td className="px-4 py-2 text-slate-500">{p.notes || "—"}</td>
                  <td className="px-4 py-2 text-slate-500">{p.created_by || "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  );
}
