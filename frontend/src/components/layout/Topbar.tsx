"use client";

import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth-context";
import { Button } from "@/components/ui/Button";

export function Topbar() {
  const { user, business, logout } = useAuth();
  const router = useRouter();

  async function handleLogout() {
    await logout();
    router.push("/login");
  }

  return (
    <header className="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-6">
      <div>
        <p className="text-sm font-medium text-slate-900">{business?.name}</p>
      </div>
      <div className="flex items-center gap-4">
        <div className="text-right">
          <p className="text-sm font-medium text-slate-900">{user?.name}</p>
          <p className="text-xs text-slate-500">{user?.roles?.join(", ")}</p>
        </div>
        <Button variant="secondary" onClick={handleLogout}>
          Sign out
        </Button>
      </div>
    </header>
  );
}
