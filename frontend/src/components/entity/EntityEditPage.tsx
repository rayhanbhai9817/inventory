"use client";

import { useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { SingleResponse } from "@/types";
import { EntityForm, type FieldConfig } from "@/components/entity/EntityForm";

interface EntityEditPageProps<T> {
  id: number;
  endpoint: string; // e.g. "/categories"
  title: string;
  fields: FieldConfig[];
  toValues: (entity: T) => Record<string, unknown>;
  cancelHref: string;
}

export function EntityEditPage<T>({
  id,
  endpoint,
  title,
  fields,
  toValues,
  cancelHref,
}: EntityEditPageProps<T>) {
  const [initialValues, setInitialValues] = useState<Record<string, unknown> | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    api
      .get<SingleResponse<T>>(`${endpoint}/${id}`)
      .then((res) => {
        if (!cancelled) setInitialValues(toValues(res.data));
      })
      .catch((err) => {
        if (!cancelled) setError(err instanceof ApiError ? err.message : "Failed to load.");
      });
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [endpoint, id]);

  if (error) return <p className="text-sm text-red-600">{error}</p>;
  if (!initialValues) return <p className="text-sm text-slate-500">Loading…</p>;

  return (
    <EntityForm
      title={title}
      fields={fields}
      initialValues={initialValues}
      cancelHref={cancelHref}
      onSubmit={async (values) => {
        await api.put(`${endpoint}/${id}`, values);
      }}
    />
  );
}
