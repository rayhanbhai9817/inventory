"use client";

import { useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { BusinessSettings } from "@/types";
import { Card } from "@/components/ui/Card";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";

export default function SettingsPage() {
  const [settings, setSettings] = useState<BusinessSettings | null>(null);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api.get<{ data: BusinessSettings }>("/settings").then((res) => setSettings(res.data));
  }, []);

  async function handleSave() {
    if (!settings) return;
    setSaving(true);
    setSaved(false);
    setError(null);
    try {
      const res = await api.put<{ data: BusinessSettings }>("/settings", {
        company_name: settings.general.company_name,
        timezone: settings.general.timezone,
        date_format: settings.general.date_format,
        number_format: settings.general.number_format,
        default_min_stock_threshold: settings.inventory.default_min_stock_threshold,
        low_stock_notifications_enabled: settings.notifications.low_stock_notifications_enabled,
        out_of_stock_notifications_enabled: settings.notifications.out_of_stock_notifications_enabled,
      });
      setSettings(res.data);
      setSaved(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to save settings.");
    } finally {
      setSaving(false);
    }
  }

  if (!settings) return <p className="text-sm text-slate-500">Loading…</p>;

  return (
    <div className="max-w-2xl">
      <h1 className="mb-6 text-2xl font-semibold text-slate-900">Settings</h1>

      <Card className="mb-6 p-6">
        <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">General</h2>
        <div className="grid grid-cols-2 gap-4">
          <Input
            label="Company name"
            value={settings.general.company_name}
            onChange={(e) => setSettings({ ...settings, general: { ...settings.general, company_name: e.target.value } })}
          />
          <Input
            label="Timezone"
            value={settings.general.timezone}
            onChange={(e) => setSettings({ ...settings, general: { ...settings.general, timezone: e.target.value } })}
          />
          <Input
            label="Date format"
            value={settings.general.date_format}
            onChange={(e) => setSettings({ ...settings, general: { ...settings.general, date_format: e.target.value } })}
          />
          <Input
            label="Number format"
            value={settings.general.number_format}
            onChange={(e) => setSettings({ ...settings, general: { ...settings.general, number_format: e.target.value } })}
          />
        </div>
      </Card>

      <Card className="mb-6 p-6">
        <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Inventory</h2>
        <Input
          label="Default minimum stock threshold"
          type="number"
          min={0}
          value={settings.inventory.default_min_stock_threshold}
          onChange={(e) =>
            setSettings({ ...settings, inventory: { default_min_stock_threshold: Number(e.target.value) } })
          }
        />
        <p className="mt-1 text-xs text-slate-400">
          Used as a suggested default — each product&apos;s own threshold still controls its low-stock alert.
        </p>
      </Card>

      <Card className="mb-6 p-6">
        <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Notifications</h2>
        <label className="mb-3 flex items-center gap-2 text-sm text-slate-700">
          <input
            type="checkbox"
            checked={settings.notifications.low_stock_notifications_enabled}
            onChange={(e) =>
              setSettings({
                ...settings,
                notifications: { ...settings.notifications, low_stock_notifications_enabled: e.target.checked },
              })
            }
          />
          Notify on low stock
        </label>
        <label className="flex items-center gap-2 text-sm text-slate-700">
          <input
            type="checkbox"
            checked={settings.notifications.out_of_stock_notifications_enabled}
            onChange={(e) =>
              setSettings({
                ...settings,
                notifications: { ...settings.notifications, out_of_stock_notifications_enabled: e.target.checked },
              })
            }
          />
          Notify on out of stock
        </label>
      </Card>

      {error && <p className="mb-4 text-sm text-red-600">{error}</p>}
      {saved && <p className="mb-4 text-sm text-emerald-600">Settings saved.</p>}

      <Button onClick={handleSave} disabled={saving}>
        {saving ? "Saving…" : "Save Settings"}
      </Button>
    </div>
  );
}
