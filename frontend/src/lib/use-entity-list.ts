"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { PaginatedResponse } from "@/types";

export function useEntityList<T extends { id: number }>(endpoint: string) {
  const [data, setData] = useState<T[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get<PaginatedResponse<T>>(endpoint, {
        page,
        search: search || undefined,
      });
      setData(res.data);
      setTotal(res.meta.total);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to load data.");
    } finally {
      setLoading(false);
    }
  }, [endpoint, page, search]);

  useEffect(() => {
    // Standard fetch-on-mount/dependency-change pattern: load() sets state
    // asynchronously after its own request/await completes.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  async function remove(id: number) {
    await api.delete(`${endpoint}/${id}`);
    await load();
  }

  return { data, total, page, setPage, search, setSearch, loading, error, reload: load, remove };
}
