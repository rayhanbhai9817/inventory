"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { ApiError } from "@/lib/api";
import { Input, Select } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";

export interface FieldConfig {
  name: string;
  label: string;
  type: "text" | "number" | "textarea" | "select" | "checkbox";
  required?: boolean;
  options?: { value: string | number; label: string }[];
  placeholder?: string;
}

interface EntityFormProps {
  title: string;
  fields: FieldConfig[];
  initialValues: Record<string, unknown>;
  onSubmit: (values: Record<string, unknown>) => Promise<void>;
  cancelHref: string;
}

export function EntityForm({ title, fields, initialValues, onSubmit, cancelHref }: EntityFormProps) {
  const router = useRouter();
  const [values, setValues] = useState<Record<string, unknown>>(initialValues);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  function update(name: string, value: unknown) {
    setValues((v) => ({ ...v, [name]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setFieldErrors({});
    setSubmitting(true);
    try {
      await onSubmit(values);
      router.push(cancelHref);
      router.refresh();
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
    <Card className="max-w-xl p-6">
      <h1 className="mb-6 text-xl font-semibold text-slate-900">{title}</h1>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        {fields.map((field) => {
          const value = values[field.name];

          if (field.type === "select") {
            return (
              <Select
                key={field.name}
                label={field.label}
                name={field.name}
                required={field.required}
                value={value == null ? "" : String(value)}
                error={fieldErrors[field.name]}
                onChange={(e) => update(field.name, e.target.value)}
              >
                <option value="">Select…</option>
                {field.options?.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </Select>
            );
          }

          if (field.type === "checkbox") {
            return (
              <label key={field.name} className="flex items-center gap-2 text-sm text-slate-700">
                <input
                  type="checkbox"
                  checked={Boolean(value)}
                  onChange={(e) => update(field.name, e.target.checked)}
                />
                {field.label}
              </label>
            );
          }

          if (field.type === "textarea") {
            return (
              <div key={field.name} className="flex flex-col gap-1">
                <label className="text-sm font-medium text-slate-700">{field.label}</label>
                <textarea
                  className="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  rows={3}
                  value={value == null ? "" : String(value)}
                  onChange={(e) => update(field.name, e.target.value)}
                />
                {fieldErrors[field.name] && (
                  <span className="text-xs text-red-600">{fieldErrors[field.name]}</span>
                )}
              </div>
            );
          }

          return (
            <Input
              key={field.name}
              label={field.label}
              name={field.name}
              type={field.type}
              required={field.required}
              placeholder={field.placeholder}
              value={value == null ? "" : String(value)}
              error={fieldErrors[field.name]}
              onChange={(e) => update(field.name, e.target.value)}
              step={field.type === "number" ? "0.01" : undefined}
            />
          );
        })}

        {error && <p className="text-sm text-red-600">{error}</p>}

        <div className="mt-2 flex gap-3">
          <Button type="submit" disabled={submitting}>
            {submitting ? "Saving…" : "Save"}
          </Button>
          <Button type="button" variant="secondary" onClick={() => router.push(cancelHref)}>
            Cancel
          </Button>
        </div>
      </form>
    </Card>
  );
}
