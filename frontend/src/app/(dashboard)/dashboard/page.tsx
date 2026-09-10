"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { api } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import type { DashboardData } from "@/types";
import { Card, StatCard } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";

const PERIODS = [
  { value: "today", label: "Today" },
  { value: "yesterday", label: "Yesterday" },
  { value: "week", label: "This Week" },
  { value: "month", label: "This Month" },
];

export default function DashboardPage() {
  const { user, can } = useAuth();
  const [period, setPeriod] = useState("today");
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    // Reset loading when the period changes; the fetch below sets it
    // false again asynchronously once the request settles.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setLoading(true);
    api
      .get<{ data: DashboardData }>("/dashboard", { period })
      .then((res) => {
        if (!cancelled) setData(res.data);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [period]);

  const greetingName = user?.name?.split(" ")[0] ?? "Admin";

  return (
    <div>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">Welcome back, {greetingName}! 👋</h1>
          <p className="mt-1 text-sm text-slate-500">Here&apos;s what&apos;s happening with your inventory today.</p>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex rounded-md border border-slate-300 bg-white p-1 text-sm">
            {PERIODS.map((p) => (
              <button
                key={p.value}
                onClick={() => setPeriod(p.value)}
                className={`rounded px-3 py-1.5 font-medium transition-colors ${
                  period === p.value ? "bg-slate-900 text-white" : "text-slate-600 hover:bg-slate-100"
                }`}
              >
                {p.label}
              </button>
            ))}
          </div>
          {can("stock.in") && (
            <Link href="/stock/in">
              <Button>Stock IN</Button>
            </Link>
          )}
          {can("stock.out") && (
            <Link href="/stock/out">
              <Button variant="secondary">Stock OUT</Button>
            </Link>
          )}
        </div>
      </div>

      {loading || !data ? (
        <p className="text-slate-500">Loading…</p>
      ) : (
        <>
          <div className="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <Card className="p-5">
              <p className="text-sm font-medium text-slate-500">Period Stock Summary</p>
              <p className="mt-1 text-xs text-slate-400">
                {data.period.from} – {data.period.to}
              </p>
              <div className="mt-4 grid grid-cols-3 gap-4">
                <div>
                  <p className="text-xs uppercase tracking-wide text-slate-400">Opening</p>
                  <p className="mt-1 text-xl font-semibold text-slate-900">{data.period_stock_summary.opening}</p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-wide text-slate-400">Net Flow</p>
                  <p
                    className={`mt-1 text-xl font-semibold ${
                      data.period_stock_summary.net_flow >= 0 ? "text-emerald-600" : "text-red-600"
                    }`}
                  >
                    {data.period_stock_summary.net_flow >= 0 ? "+" : ""}
                    {data.period_stock_summary.net_flow}
                  </p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-wide text-slate-400">Closing Stock</p>
                  <p className="mt-1 text-xl font-semibold text-slate-900">{data.period_stock_summary.closing}</p>
                </div>
              </div>
            </Card>

            <div className="grid grid-cols-2 gap-4">
              <Card className="p-5">
                <p className="text-xs font-semibold uppercase tracking-wide text-emerald-600">Stock In</p>
                <p className="mt-2 text-2xl font-semibold text-slate-900">{data.stock_in_total} Units</p>
                <p className="mt-1 text-xs text-slate-400">Units received this period</p>
              </Card>
              <Card className="p-5">
                <p className="text-xs font-semibold uppercase tracking-wide text-red-600">Stock Out</p>
                <p className="mt-2 text-2xl font-semibold text-slate-900">{data.stock_out_total} Units</p>
                <p className="mt-1 text-xs text-slate-400">Units dispatched this period</p>
              </Card>
            </div>
          </div>

          <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Total Products" value={data.totals.total_products} />
            <StatCard label="Total Units" value={data.totals.total_units} hint="Global stock balance" />
            <StatCard label="Total Boxes" value={data.totals.total_boxes} hint="Approximate box count" />
            <Card className="p-5">
              <p className="text-sm font-medium text-slate-500">Inventory Health</p>
              <p
                className={`mt-2 text-2xl font-semibold ${
                  data.inventory_health.status === "optimal"
                    ? "text-emerald-600"
                    : data.inventory_health.status === "fair"
                      ? "text-amber-600"
                      : "text-red-600"
                }`}
              >
                {data.inventory_health.percent}%
              </p>
              <p className="mt-1 text-xs text-slate-400">
                {data.inventory_health.low_stock_count} low · {data.inventory_health.out_of_stock_count} out of stock
              </p>
            </Card>
          </div>

          {(can("supplier_reports.view") || can("product_prices.view")) && (
            <div className="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
              {can("supplier_reports.view") && (
                <Card className="p-5">
                  <p className="text-sm font-medium text-slate-500">Supplier Summary</p>
                  <div className="mt-4 grid grid-cols-3 gap-4">
                    <div>
                      <p className="text-xs uppercase tracking-wide text-slate-400">Total Suppliers</p>
                      <p className="mt-1 text-xl font-semibold text-slate-900">
                        {data.supplier_summary.total_suppliers}
                      </p>
                    </div>
                    <div>
                      <p className="text-xs uppercase tracking-wide text-slate-400">Active</p>
                      <p className="mt-1 text-xl font-semibold text-slate-900">
                        {data.supplier_summary.active_suppliers}
                      </p>
                    </div>
                    <div>
                      <p className="text-xs uppercase tracking-wide text-slate-400">Used This Period</p>
                      <p className="mt-1 text-xl font-semibold text-slate-900">
                        {data.supplier_summary.recently_used_suppliers}
                      </p>
                    </div>
                  </div>
                  {data.supplier_summary.top_suppliers_by_quantity.length > 0 && (
                    <div className="mt-4 border-t border-slate-100 pt-3">
                      <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        Top Suppliers by Quantity
                      </p>
                      <ul className="space-y-1 text-sm text-slate-600">
                        {data.supplier_summary.top_suppliers_by_quantity.map((s) => (
                          <li key={s.id} className="flex justify-between">
                            <span>{s.name}</span>
                            <span className="font-medium text-slate-900">{s.total_units_supplied} units</span>
                          </li>
                        ))}
                      </ul>
                    </div>
                  )}
                </Card>
              )}

              <Card className="p-5">
                <p className="text-sm font-medium text-slate-500">Data Quality Alerts</p>
                <div className="mt-3 space-y-2 text-sm">
                  <div className="flex items-center justify-between rounded-md bg-amber-50 px-3 py-2">
                    <span className="text-amber-800">Products missing a supplier</span>
                    <span className="font-semibold text-amber-900">
                      {data.product_alerts.missing_supplier_count}
                    </span>
                  </div>
                  <div className="flex items-center justify-between rounded-md bg-amber-50 px-3 py-2">
                    <span className="text-amber-800">Products missing a reference price</span>
                    <span className="font-semibold text-amber-900">
                      {data.product_alerts.missing_price_count}
                    </span>
                  </div>
                </div>
                {data.product_alerts.missing_supplier_count > 0 && (
                  <Link href="/reports/product-suppliers" className="mt-3 inline-block text-xs text-indigo-600 hover:underline">
                    View products missing a supplier →
                  </Link>
                )}
              </Card>
            </div>
          )}

          <Card>
            <div className="border-b border-slate-200 px-4 py-3">
              <p className="text-sm font-medium text-slate-900">Recent Stock Movements</p>
            </div>
            {data.recent_movements.length === 0 ? (
              <p className="p-4 text-sm text-slate-500">No movements yet.</p>
            ) : (
              <table className="w-full text-left text-sm">
                <thead className="border-b border-slate-200 text-slate-500">
                  <tr>
                    <th className="px-4 py-2 font-medium">Reference</th>
                    <th className="px-4 py-2 font-medium">Product</th>
                    <th className="px-4 py-2 font-medium">Type</th>
                    <th className="px-4 py-2 font-medium">Units</th>
                    <th className="px-4 py-2 font-medium">Date</th>
                  </tr>
                </thead>
                <tbody>
                  {data.recent_movements.map((m) => (
                    <tr key={m.id} className="border-b border-slate-100 last:border-0">
                      <td className="px-4 py-2 font-mono text-xs text-slate-500">{m.reference}</td>
                      <td className="px-4 py-2 text-slate-700">{m.product?.name}</td>
                      <td className="px-4 py-2 text-slate-700">{m.type.replace("_", " ")}</td>
                      <td className="px-4 py-2 text-slate-700">{m.units}</td>
                      <td className="px-4 py-2 text-slate-500">{new Date(m.created_at).toLocaleString()}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </Card>
        </>
      )}
    </div>
  );
}
