"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { PaginatedResponse, Product, StockAdjustment } from "@/types";
import { Card } from "@/components/ui/Card";
import { Input, Select } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";

const REASONS = [
  { value: "physical_count", label: "Physical count correction" },
  { value: "damaged", label: "Damaged stock" },
  { value: "lost", label: "Lost stock" },
  { value: "found", label: "Found stock" },
  { value: "data_correction", label: "Data correction" },
  { value: "other", label: "Other" },
];

export default function StockAdjustmentsPage() {
  const [products, setProducts] = useState<Product[]>([]);
  const [adjustments, setAdjustments] = useState<StockAdjustment[]>([]);
  const [loading, setLoading] = useState(true);

  const [productId, setProductId] = useState("");
  const [direction, setDirection] = useState<"increase" | "decrease">("increase");
  const [quantity, setQuantity] = useState("");
  const [reason, setReason] = useState("physical_count");
  const [note, setNote] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const loadAdjustments = useCallback(async () => {
    setLoading(true);
    const res = await api.get<PaginatedResponse<StockAdjustment>>("/stock/adjustments", { per_page: 20 });
    setAdjustments(res.data);
    setLoading(false);
  }, []);

  useEffect(() => {
    api.get<PaginatedResponse<Product>>("/products", { tab: "active", per_page: 200 }).then((res) => setProducts(res.data));
    // eslint-disable-next-line react-hooks/set-state-in-effect
    loadAdjustments();
  }, [loadAdjustments]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setFieldErrors({});
    setSubmitting(true);
    try {
      await api.post("/stock/adjustments", {
        product_id: Number(productId),
        direction,
        quantity: Number(quantity),
        reason,
        note: note || undefined,
      });
      setQuantity("");
      setNote("");
      await loadAdjustments();
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.status === 422 && !err.body.errors) {
          setError(err.message);
        } else {
          setFieldErrors(err.fieldErrors());
        }
      } else {
        setError("Something went wrong.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div>
      <h1 className="mb-6 text-2xl font-semibold text-slate-900">Stock Adjustments</h1>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <Card className="p-6 lg:col-span-1">
          <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            <Select label="Product" required value={productId} error={fieldErrors.product_id} onChange={(e) => setProductId(e.target.value)}>
              <option value="">Select a product…</option>
              {products.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name} ({p.sku})
                </option>
              ))}
            </Select>

            <Select label="Direction" value={direction} onChange={(e) => setDirection(e.target.value as "increase" | "decrease")}>
              <option value="increase">Increase</option>
              <option value="decrease">Decrease</option>
            </Select>

            <Input
              label="Quantity"
              type="number"
              min={1}
              required
              value={quantity}
              error={fieldErrors.quantity}
              onChange={(e) => setQuantity(e.target.value)}
            />

            <Select label="Reason" value={reason} onChange={(e) => setReason(e.target.value)}>
              {REASONS.map((r) => (
                <option key={r.value} value={r.value}>
                  {r.label}
                </option>
              ))}
            </Select>

            <div className="flex flex-col gap-1">
              <label className="text-sm font-medium text-slate-700">Note</label>
              <textarea
                className="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                rows={2}
                value={note}
                onChange={(e) => setNote(e.target.value)}
              />
            </div>

            {error && <p className="text-sm text-red-600">{error}</p>}

            <Button type="submit" disabled={submitting || !productId}>
              {submitting ? "Saving…" : "Submit Adjustment"}
            </Button>
          </form>
        </Card>

        <Card className="lg:col-span-2">
          <div className="border-b border-slate-200 px-4 py-3 text-sm font-medium text-slate-900">
            Recent Adjustments
          </div>
          {loading ? (
            <p className="p-4 text-sm text-slate-500">Loading…</p>
          ) : adjustments.length === 0 ? (
            <p className="p-4 text-sm text-slate-500">No adjustments yet.</p>
          ) : (
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 text-slate-500">
                <tr>
                  <th className="px-4 py-2 font-medium">Date</th>
                  <th className="px-4 py-2 font-medium">Product</th>
                  <th className="px-4 py-2 font-medium">Direction</th>
                  <th className="px-4 py-2 font-medium">Qty</th>
                  <th className="px-4 py-2 font-medium">Reason</th>
                  <th className="px-4 py-2 font-medium">User</th>
                </tr>
              </thead>
              <tbody>
                {adjustments.map((a) => (
                  <tr key={a.id} className="border-b border-slate-100 last:border-0">
                    <td className="px-4 py-2 text-slate-500">{new Date(a.created_at).toLocaleString()}</td>
                    <td className="px-4 py-2 text-slate-900">{a.product?.name}</td>
                    <td className={`px-4 py-2 font-medium ${a.direction === "increase" ? "text-emerald-600" : "text-red-600"}`}>
                      {a.direction}
                    </td>
                    <td className="px-4 py-2 text-slate-700">{a.quantity}</td>
                    <td className="px-4 py-2 text-slate-500">{a.reason.replace("_", " ")}</td>
                    <td className="px-4 py-2 text-slate-500">{a.user}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Card>
      </div>
    </div>
  );
}
