"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

const NAV_ITEMS = [
  { href: "/dashboard", label: "Dashboard" },
  { href: "/products", label: "Products" },
  { href: "/categories", label: "Categories" },
  { href: "/brands", label: "Brands" },
  { href: "/units", label: "Units" },
  { href: "/warehouses", label: "Warehouses" },
  { href: "/suppliers", label: "Suppliers" },
  { href: "/customers", label: "Customers" },
];

export function Sidebar() {
  const pathname = usePathname();

  return (
    <nav className="flex w-56 shrink-0 flex-col gap-1 border-r border-slate-200 bg-white p-4">
      <div className="mb-4 px-2 text-lg font-semibold text-slate-900">Inventory SaaS</div>
      {NAV_ITEMS.map((item) => {
        const active = pathname === item.href || pathname.startsWith(`${item.href}/`);
        return (
          <Link
            key={item.href}
            href={item.href}
            className={`rounded-md px-3 py-2 text-sm font-medium transition-colors ${
              active
                ? "bg-indigo-50 text-indigo-700"
                : "text-slate-600 hover:bg-slate-100 hover:text-slate-900"
            }`}
          >
            {item.label}
          </Link>
        );
      })}
    </nav>
  );
}
