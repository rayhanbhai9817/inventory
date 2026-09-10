"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useAuth, ApiError } from "@/lib/auth-context";
import { Card } from "@/components/ui/Card";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";

export default function RegisterPage() {
  const { register } = useAuth();
  const router = useRouter();
  const [form, setForm] = useState({
    business_name: "",
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
  });
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  function update(field: keyof typeof form, value: string) {
    setForm((f) => ({ ...f, [field]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setFieldErrors({});
    setSubmitting(true);
    try {
      await register(form);
      router.push("/dashboard");
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors());
        setError(err.body.errors ? null : err.message);
      } else {
        setError("Something went wrong.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Card className="p-6">
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <Input
          label="Business name"
          name="business_name"
          value={form.business_name}
          onChange={(e) => update("business_name", e.target.value)}
          error={fieldErrors.business_name}
          required
          autoFocus
        />
        <Input
          label="Your name"
          name="name"
          value={form.name}
          onChange={(e) => update("name", e.target.value)}
          error={fieldErrors.name}
          required
        />
        <Input
          label="Email"
          type="email"
          name="email"
          value={form.email}
          onChange={(e) => update("email", e.target.value)}
          error={fieldErrors.email}
          required
        />
        <Input
          label="Password"
          type="password"
          name="password"
          value={form.password}
          onChange={(e) => update("password", e.target.value)}
          error={fieldErrors.password}
          required
        />
        <Input
          label="Confirm password"
          type="password"
          name="password_confirmation"
          value={form.password_confirmation}
          onChange={(e) => update("password_confirmation", e.target.value)}
          required
        />
        {error && <p className="text-sm text-red-600">{error}</p>}
        <Button type="submit" disabled={submitting} className="w-full">
          {submitting ? "Creating account…" : "Create business account"}
        </Button>
      </form>
      <p className="mt-4 text-center text-sm text-slate-500">
        Already registered?{" "}
        <Link href="/login" className="font-medium text-indigo-600 hover:underline">
          Sign in
        </Link>
      </p>
    </Card>
  );
}
