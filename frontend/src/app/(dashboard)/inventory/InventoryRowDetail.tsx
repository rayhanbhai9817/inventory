"use client";

import { useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import { formatDate } from "@/lib/format";
import type { InventoryDetail, SingleResponse } from "@/types";
import { BatchStatusBadge, StockStatusBadge } from "@/components/ui/Badge";

export function InventoryRowDetail({ productId }: { productId: number }) {
  const [detail, setDetail] = useState<InventoryDetail | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    api
      .get<SingleResponse<InventoryDetail>>(`/inventory/${productId}`)
      .then((res) => {
        if (!cancelled) setDetail(res.data);
      })
      .catch((err) => {
        if (!cancelled) setError(err instanceof ApiError ? err.message : "Failed to load detail.");
      });
    return () => {
      cancelled = true;
    };
  }, [productId]);

  if (error) return <p className="p-4 text-sm text-red-600">{error}</p>;
  if (!detail) return <p className="p-4 text-sm text-slate-500">Loading…</p>;

  return (
    <div className="space-y-4 bg-slate-50 p-4">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
        <DetailCard title="Product Information">
          <Row label="Product Name" value={detail.product.name} />
          <Row label="SKU" value={detail.product.sku} />
          <Row label="Category" value={detail.product.category ?? "—"} />
        </DetailCard>
        <DetailCard title="Inventory Summary">
          <Row label="Total Boxes" value={detail.inventory_summary.total_boxes} />
          <Row label="Total Units" value={detail.inventory_summary.total_units} />
          <Row label="Status" value={<StockStatusBadge status={detail.inventory_summary.status} />} />
        </DetailCard>
        <DetailCard title="Movement Stats">
          <Row label="Total Stock In" value={`${detail.movement_stats.total_stock_in} Units`} />
          <Row label="Total Stock Out" value={`${detail.movement_stats.total_stock_out} Units`} />
          <Row label="Current Balance" value={`${detail.movement_stats.current_balance} Units`} />
        </DetailCard>
        <DetailCard title="Last Updated">
          <Row
            label="Last Movement"
            value={
              detail.movement_stats.last_updated
                ? new Date(detail.movement_stats.last_updated).toLocaleDateString()
                : "—"
            }
          />
        </DetailCard>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div className="rounded-lg border border-slate-200 bg-white">
          <div className="border-b border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">
            FIFO Batch Inventory
          </div>
          {detail.batches.length === 0 ? (
            <p className="p-4 text-sm text-slate-500">No batches yet.</p>
          ) : (
            <table className="w-full text-left text-xs">
              <thead className="text-slate-400">
                <tr>
                  <th className="px-4 py-2 font-medium">Date</th>
                  <th className="px-4 py-2 font-medium">Batch</th>
                  <th className="px-4 py-2 font-medium">Boxes</th>
                  <th className="px-4 py-2 font-medium">Ratio</th>
                  <th className="px-4 py-2 font-medium">Units</th>
                  <th className="px-4 py-2 font-medium">Status</th>
                </tr>
              </thead>
              <tbody>
                {detail.batches.map((b) => (
                  <tr key={b.id} className="border-t border-slate-100">
                    <td className="px-4 py-2 text-slate-600">{formatDate(b.received_at)}</td>
                    <td className="px-4 py-2 font-mono text-slate-600">#{b.batch_code}</td>
                    <td className="px-4 py-2 text-slate-600">
                      {b.remaining_boxes}/{b.boxes}
                    </td>
                    <td className="px-4 py-2 text-slate-600">{b.units_per_box}/box</td>
                    <td className="px-4 py-2 text-slate-600">{b.remaining_units}</td>
                    <td className="px-4 py-2">
                      <BatchStatusBadge status={b.status} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        <div className="rounded-lg border border-slate-200 bg-white">
          <div className="border-b border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">
            Recent Movement History
          </div>
          {detail.recent_movements.length === 0 ? (
            <p className="p-4 text-sm text-slate-500">No movements yet.</p>
          ) : (
            <table className="w-full text-left text-xs">
              <thead className="text-slate-400">
                <tr>
                  <th className="px-4 py-2 font-medium">Date</th>
                  <th className="px-4 py-2 font-medium">Type</th>
                  <th className="px-4 py-2 font-medium">Qty</th>
                  <th className="px-4 py-2 font-medium">Balance</th>
                </tr>
              </thead>
              <tbody>
                {detail.recent_movements.map((m) => {
                  const isIn = m.type.includes("in") || m.type === "adjustment_increase";
                  return (
                    <tr key={m.id} className="border-t border-slate-100">
                      <td className="px-4 py-2 text-slate-600">{new Date(m.created_at).toLocaleString()}</td>
                      <td className="px-4 py-2">
                        <span
                          className={`rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase ${
                            isIn ? "bg-emerald-100 text-emerald-700" : "bg-red-100 text-red-700"
                          }`}
                        >
                          {m.type.replace("_", " ")}
                        </span>
                      </td>
                      <td className={`px-4 py-2 font-medium ${isIn ? "text-emerald-600" : "text-red-600"}`}>
                        {isIn ? "+" : "-"}
                        {m.units}
                      </td>
                      <td className="px-4 py-2 text-slate-600">{m.balance_after}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  );
}

function DetailCard({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-4">
      <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">{title}</p>
      <div className="space-y-1">{children}</div>
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
