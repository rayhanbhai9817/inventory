export function Badge({ status }: { status: "active" | "inactive" | string }) {
  const isActive = status === "active";
  return (
    <span
      className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
        isActive ? "bg-emerald-100 text-emerald-700" : "bg-slate-100 text-slate-600"
      }`}
    >
      {status}
    </span>
  );
}
