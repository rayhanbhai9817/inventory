"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import type { PaginatedResponse, Product } from "@/types";
import { StatCard } from "@/components/ui/Card";

interface Counts {
  products: number;
  lowStock: number;
  suppliers: number;
  customers: number;
  stockValue: number;
}

export default function DashboardPage() {
  const [counts, setCounts] = useState<Counts | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      const [products, lowStock, suppliers, customers] = await Promise.all([
        api.get<PaginatedResponse<Product>>("/products", { per_page: 100 }),
        api.get<PaginatedResponse<Product>>("/products", { low_stock: true, per_page: 1 }),
        api.get<PaginatedResponse<unknown>>("/suppliers", { per_page: 1 }),
        api.get<PaginatedResponse<unknown>>("/customers", { per_page: 1 }),
      ]);

      const stockValue = products.data.reduce(
        (sum, p) => sum + p.cost_price * (p.total_stock ?? 0),
        0,
      );

      if (!cancelled) {
        setCounts({
          products: products.meta.total,
          lowStock: lowStock.meta.total,
          suppliers: suppliers.meta.total,
          customers: customers.meta.total,
          stockValue,
        });
        setLoading(false);
      }
    }

    load();
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div>
      <h1 className="mb-6 text-2xl font-semibold text-slate-900">Dashboard</h1>

      {loading ? (
        <p className="text-slate-500">Loading…</p>
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard label="Products" value={counts!.products} />
          <StatCard
            label="Low stock products"
            value={counts!.lowStock}
            hint="At or below minimum stock level"
          />
          <StatCard
            label="Stock value (cost)"
            value={counts!.stockValue.toLocaleString(undefined, {
              maximumFractionDigits: 2,
            })}
            hint="First 100 products, current page only"
          />
          <StatCard label="Suppliers" value={counts!.suppliers} />
          <StatCard label="Customers" value={counts!.customers} />
        </div>
      )}

      <p className="mt-8 text-sm text-slate-400">
        Sales, purchases, and revenue KPIs will appear here once those modules are built.
      </p>
    </div>
  );
}
