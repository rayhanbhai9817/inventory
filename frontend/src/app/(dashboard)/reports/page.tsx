"use client";

import Link from "next/link";
import { Card } from "@/components/ui/Card";

const REPORTS = [
  { title: "Inventory Summary", description: "Current stock levels across all products.", href: "/inventory" },
  { title: "Low Stock", description: "Products at or below their minimum threshold.", href: "/inventory?status=low_stock" },
  { title: "Out of Stock", description: "Products with zero available units.", href: "/inventory?status=out_of_stock" },
  { title: "Stock IN", description: "All stock received, filterable by date.", href: "/stock-ledger?type=stock_in" },
  { title: "Stock OUT", description: "All stock dispatched, filterable by date.", href: "/stock-ledger?type=stock_out" },
  { title: "Stock Movement", description: "Full movement ledger — every transaction.", href: "/stock-ledger" },
  { title: "Batch Inventory", description: "FIFO batches, by status and product.", href: "/batches" },
  { title: "Daily Activity", description: "Operational activity for a given day.", href: "/activity" },
];

export default function ReportsPage() {
  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-slate-900">Reports</h1>
        <p className="text-sm text-slate-500">
          Each report is a filtered, exportable view of live inventory data — not a separate copy.
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {REPORTS.map((report) => (
          <Link key={report.title} href={report.href}>
            <Card className="h-full p-5 transition-shadow hover:shadow-md">
              <p className="font-semibold text-slate-900">{report.title}</p>
              <p className="mt-1 text-sm text-slate-500">{report.description}</p>
            </Card>
          </Link>
        ))}
      </div>
    </div>
  );
}
