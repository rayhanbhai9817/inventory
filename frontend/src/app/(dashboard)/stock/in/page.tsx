"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { api, ApiError } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import type { PaginatedResponse, Product, Supplier } from "@/types";
import { Card } from "@/components/ui/Card";
import { Input, Select } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";

export default function StockInPage() {
  const router = useRouter();
  const { can } = useAuth();
  const [products, setProducts] = useState<Product[]>([]);
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [productId, setProductId] = useState("");
  const [supplierId, setSupplierId] = useState("");
  const [boxes, setBoxes] = useState("");
  const [unitsPerBox, setUnitsPerBox] = useState("");
  const [receivedAt, setReceivedAt] = useState(() => new Date().toISOString().slice(0, 10));
  const [notes, setNotes] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    api.get<PaginatedResponse<Product>>("/products", { tab: "active", per_page: 200 }).then((res) => {
      setProducts(res.data);
    });
    if (can("suppliers.view")) {
      api.get<PaginatedResponse<Supplier>>("/suppliers", { tab: "active", per_page: 200 }).then((res) => {
        setSuppliers(res.data);
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const totalUnits = Number(boxes || 0) * Number(unitsPerBox || 0);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setFieldErrors({});
    setSuccess(null);
    setSubmitting(true);
    try {
      const res = await api.post<{ data: { reference: string } }>("/stock/in", {
        product_id: Number(productId),
        boxes: Number(boxes),
        units_per_box: Number(unitsPerBox),
        received_at: receivedAt,
        notes: notes || undefined,
        supplier_id: supplierId ? Number(supplierId) : undefined,
      });
      setSuccess(`Stock IN recorded (${res.data.reference}). ${totalUnits} units added.`);
      setBoxes("");
      setUnitsPerBox("");
      setNotes("");
      setSupplierId("");
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors());
        setError(Object.keys(err.body.errors ?? {}).length ? null : err.message);
      } else {
        setError("Something went wrong.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div>
      <h1 className="mb-6 text-2xl font-semibold text-slate-900">Stock IN</h1>

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

          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Boxes"
              type="number"
              min={1}
              required
              value={boxes}
              error={fieldErrors.boxes}
              onChange={(e) => setBoxes(e.target.value)}
            />
            <Input
              label="Units per box"
              type="number"
              min={1}
              required
              value={unitsPerBox}
              error={fieldErrors.units_per_box}
              onChange={(e) => setUnitsPerBox(e.target.value)}
            />
          </div>

          <p className="text-sm text-slate-500">
            Total units: <span className="font-semibold text-slate-900">{totalUnits}</span>
          </p>

          {suppliers.length > 0 && (
            <Select
              label="Supplier (optional)"
              value={supplierId}
              error={fieldErrors.supplier_id}
              onChange={(e) => setSupplierId(e.target.value)}
            >
              <option value="">No supplier / unknown</option>
              {suppliers.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </Select>
          )}

          <Input
            label="Received date"
            type="date"
            required
            value={receivedAt}
            error={fieldErrors.received_at}
            onChange={(e) => setReceivedAt(e.target.value)}
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
              {submitting ? "Recording…" : "Record Stock IN"}
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
