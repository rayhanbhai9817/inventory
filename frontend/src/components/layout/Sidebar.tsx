"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useAuth } from "@/lib/auth-context";

interface NavItem {
  href: string;
  label: string;
  permission?: string;
}

interface NavGroup {
  label: string;
  items: NavItem[];
}

const NAV: NavGroup[] = [
  { label: "", items: [{ href: "/dashboard", label: "Dashboard", permission: "dashboard.view" }] },
  {
    label: "Inventory",
    items: [
      { href: "/inventory", label: "Inventory Overview", permission: "inventory.view" },
      { href: "/stock/in", label: "Stock IN", permission: "stock.in" },
      { href: "/stock/out", label: "Stock OUT", permission: "stock.out" },
      { href: "/stock-ledger", label: "Stock Ledger", permission: "ledger.view" },
      { href: "/stock/adjustments", label: "Stock Adjustments", permission: "stock.adjust" },
      { href: "/batches", label: "Batch Inventory", permission: "batches.view" },
    ],
  },
  {
    label: "Products",
    items: [
      { href: "/products", label: "Products", permission: "products.view" },
      { href: "/categories", label: "Categories", permission: "categories.view" },
      { href: "/product-prices", label: "Product Prices", permission: "product_prices.view" },
    ],
  },
  {
    label: "Suppliers",
    items: [{ href: "/suppliers", label: "Suppliers", permission: "suppliers.view" }],
  },
  {
    label: "Activity",
    items: [
      { href: "/activity", label: "Daily Activity", permission: "activity.view" },
      { href: "/audit-log", label: "Audit Log", permission: "audit.view" },
    ],
  },
  {
    label: "Reports",
    items: [{ href: "/reports", label: "Reports", permission: "reports.view" }],
  },
  {
    label: "Administration",
    items: [
      { href: "/notifications", label: "Notifications" },
      { href: "/users", label: "Admin Users", permission: "users.view" },
      { href: "/roles", label: "Roles & Permissions", permission: "roles.view" },
      { href: "/settings", label: "Settings", permission: "settings.manage" },
    ],
  },
];

export function Sidebar() {
  const pathname = usePathname();
  const { can } = useAuth();

  return (
    <nav className="flex w-64 shrink-0 flex-col gap-4 overflow-y-auto border-r border-slate-800 bg-slate-900 p-4">
      <div className="px-2 py-1 text-base font-semibold tracking-wide text-white">
        OZIPCO <span className="font-normal text-slate-400">INVENTORY</span>
      </div>

      {NAV.map((group) => {
        const items = group.items.filter((item) => !item.permission || can(item.permission));
        if (items.length === 0) return null;

        return (
          <div key={group.label || "top"} className="flex flex-col gap-1">
            {group.label && (
              <div className="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">
                {group.label}
              </div>
            )}
            {items.map((item) => {
              const active = pathname === item.href || pathname.startsWith(`${item.href}/`);
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={`rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                    active
                      ? "bg-indigo-600 text-white"
                      : "text-slate-300 hover:bg-slate-800 hover:text-white"
                  }`}
                >
                  {item.label}
                </Link>
              );
            })}
          </div>
        );
      })}
    </nav>
  );
}
