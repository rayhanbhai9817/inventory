"use client";

import { useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { api, ApiError } from "@/lib/api";
import type { PaginatedResponse, Product } from "@/types";
import { Card } from "@/components/ui/Card";
import { Input, Select } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";

export default function StockOutPage() {
  const router = useRouter();
  const [products, setProducts] = useState<Product[]>([]);
  const [productId, setProductId] = useState("");
  const [units, setUnits] = useState("");
  const [notes, setNotes] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    api.get<PaginatedResponse<Product>>("/products", { tab: "active", per_page: 200 }).then((res) => {
      setProducts(res.data);
    });
  }, []);

  const selectedProduct = useMemo(() => products.find((p) => String(p.id) === productId), [products, productId]);
  const available = selectedProduct?.total_units ?? null;

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setFieldErrors({});
    setSuccess(null);
    setSubmitting(true);
    try {
      const res = await api.post<{ data: { reference: string; balance_after: number } }>("/stock/out", {
        product_id: Number(productId),
        units: Number(units),
        notes: notes || undefined,
      });
      setSuccess(`Stock OUT recorded (${res.data.reference}). ${res.data.balance_after} units remaining.`);
      setUnits("");
      setNotes("");
      // Refresh available stock for the selected product.
      const refreshed = await api.get<PaginatedResponse<Product>>("/products", { tab: "active", per_page: 200 });
      setProducts(refreshed.data);
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.status === 422 && !err.body.errors) {
          setError(err.message); // insufficient stock message from the backend
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
      <h1 className="mb-6 text-2xl font-semibold text-slate-900">Stock OUT</h1>

      <Card className="max-w-xl p-6">
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <Select
            label="Product"
            required
            value={productId}
            error={fieldErrors.product_id}
            onChange={(e) => setProductId(e.target.value)}
          >
            <option value="">Select a product…</option>
            {products.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name} ({p.sku})
              </option>
            ))}
          </Select>

          {available !== null && (
            <p className="text-sm text-slate-500">
              Available stock: <span className="font-semibold text-slate-900">{available} units</span>
            </p>
          )}

          <Input
            label="Units to dispatch"
            type="number"
            min={1}
            required
            value={units}
            error={fieldErrors.units}
            onChange={(e) => setUnits(e.target.value)}
          />

          <div className="flex flex-col gap-1">
            <label className="text-sm font-medium text-slate-700">Notes</label>
            <textarea
              className="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              rows={2}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
            />
          </div>

          {error && <p className="text-sm text-red-600">{error}</p>}
          {success && <p className="text-sm text-emerald-600">{success}</p>}

          <div className="mt-2 flex gap-3">
            <Button type="submit" disabled={submitting || !productId}>
              {submitting ? "Recording…" : "Record Stock OUT"}
            </Button>
            <Button type="button" variant="secondary" onClick={() => router.push("/inventory")}>
              Go to Inventory
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
}
