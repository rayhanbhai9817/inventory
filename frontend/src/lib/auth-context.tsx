"use client";

import { createContext, useCallback, useContext, useEffect, useState } from "react";
import { api, ApiError, getToken, setToken } from "@/lib/api";
import type { Business, User } from "@/types";

interface AuthContextValue {
  user: User | null;
  business: Business | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (data: {
    business_name: string;
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
  }) => Promise<void>;
  logout: () => Promise<void>;
  can: (permission: string) => boolean;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [business, setBusiness] = useState<Business | null>(null);
  const [loading, setLoading] = useState(true);

  const loadMe = useCallback(async () => {
    if (!getToken()) {
      setLoading(false);
      return;
    }

    try {
      const res = await api.get<{ user: User; business: Business }>("/auth/me");
      setUser(res.user);
      setBusiness(res.business);
    } catch {
      setToken(null);
      setUser(null);
      setBusiness(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    // Standard fetch-on-mount pattern: loadMe() sets state asynchronously
    // after its own request/await completes, not synchronously in this
    // effect body.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    loadMe();
  }, [loadMe]);

  const login = useCallback(async (email: string, password: string) => {
    const res = await api.post<{ user: User; token: string }>("/auth/login", {
      email,
      password,
    });
    setToken(res.token);
    setUser(res.user);
    const me = await api.get<{ user: User; business: Business }>("/auth/me");
    setBusiness(me.business);
  }, []);

  const register = useCallback(
    async (data: {
      business_name: string;
      name: string;
      email: string;
      password: string;
      password_confirmation: string;
    }) => {
      const res = await api.post<{ user: User; business: Business; token: string }>(
        "/auth/register",
        data,
      );
      setToken(res.token);
      setUser(res.user);
      setBusiness(res.business);
    },
    [],
  );

  const logout = useCallback(async () => {
    try {
      await api.post("/auth/logout");
    } catch {
      // Token may already be invalid/expired — clear local state regardless.
    }
    setToken(null);
    setUser(null);
    setBusiness(null);
  }, []);

  const can = useCallback(
    (permission: string) => user?.permissions?.includes(permission) ?? false,
    [user],
  );

  return (
    <AuthContext.Provider value={{ user, business, loading, login, register, logout, can }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within an AuthProvider");
  return ctx;
}

export { ApiError };
