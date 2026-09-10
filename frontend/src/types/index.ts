export interface Business {
  id: number;
  name: string;
  slug: string;
  email: string | null;
  currency_code: string;
  timezone: string;
  logo_path: string | null;
  status: "active" | "suspended";
}

export interface User {
  id: number;
  business_id: number;
  name: string;
  email: string;
  phone: string | null;
  status: "active" | "inactive";
  roles?: string[];
  permissions?: string[];
  last_login_at: string | null;
  created_at: string;
}

export interface Category {
  id: number;
  parent_id: number | null;
  name: string;
  slug: string;
  status: "active" | "inactive";
  product_count?: number;
  created_at: string;
}

export type ProductLifecycleStatus = "active" | "archived" | "trashed";
export type StockStatus = "in_stock" | "low_stock" | "out_of_stock";

export interface Product {
  id: number;
  sku: string;
  name: string;
  description: string | null;
  image_path: string | null;
  category: { id: number; name: string } | null;
  min_stock_level: number;
  total_units: number | null;
  total_boxes: number | null;
  units_per_box: number | null;
  status: ProductLifecycleStatus;
  archived_at: string | null;
  deleted_at: string | null;
  created_at: string;
}

export interface InventoryRow {
  id: number;
  sku: string;
  name: string;
  category: string | null;
  boxes: number;
  units_per_box: number | null;
  total_units: number;
  min_stock_level: number;
  stock_in_total: number;
  stock_out_total: number;
  status: StockStatus;
}

export interface InventorySummary {
  total_products: number;
  total_boxes: number;
  available_units: number;
  low_stock: number;
  out_of_stock: number;
}

export interface StockBatch {
  id: number;
  batch_code: string;
  product_id: number;
  product?: { id: number; name: string; sku: string };
  boxes: number;
  units_per_box: number;
  total_units: number;
  remaining_units: number;
  remaining_boxes: number;
  status: "full" | "partial" | "depleted";
  received_at: string;
  notes: string | null;
  created_by?: string;
  created_at: string;
}

export type MovementType = "stock_in" | "stock_out" | "adjustment_increase" | "adjustment_decrease";

export interface StockMovement {
  id: number;
  reference: string;
  type: MovementType;
  product?: { id: number; name: string; sku: string };
  units: number;
  balance_after: number;
  note: string | null;
  batches?: { batch_code: string; units: number }[];
  user?: string;
  created_at: string;
}

export type AdjustmentReason = "physical_count" | "damaged" | "lost" | "found" | "data_correction" | "other";

export interface StockAdjustment {
  id: number;
  product?: { id: number; name: string };
  direction: "increase" | "decrease";
  quantity: number;
  reason: AdjustmentReason;
  note: string | null;
  movement?: StockMovement;
  user?: string;
  created_at: string;
}

export interface InventoryDetail {
  product: { id: number; name: string; sku: string; category: string | null };
  inventory_summary: { total_boxes: number; total_units: number; status: StockStatus };
  movement_stats: {
    total_stock_in: number;
    total_stock_out: number;
    current_balance: number;
    last_updated: string | null;
  };
  batches: StockBatch[];
  recent_movements: StockMovement[];
}

export interface DashboardData {
  period: { from: string; to: string };
  period_stock_summary: { opening: number; net_flow: number; closing: number };
  stock_in_total: number;
  stock_out_total: number;
  totals: { total_products: number; total_units: number; total_boxes: number };
  inventory_health: {
    percent: number;
    status: "optimal" | "fair" | "attention_needed";
    low_stock_count: number;
    out_of_stock_count: number;
  };
  recent_movements: StockMovement[];
}

export interface AuditLogEntry {
  id: number;
  action: string;
  module: string | null;
  reference: string | null;
  description: string;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  user: string | null;
  ip_address: string | null;
  created_at: string;
}

export type NotificationType =
  | "low_stock"
  | "out_of_stock"
  | "stock_in"
  | "stock_out"
  | "adjustment"
  | "admin_activity";

export interface AppNotification {
  id: number;
  type: NotificationType;
  title: string;
  body: string | null;
  data: Record<string, unknown> | null;
  read: boolean;
  read_at: string | null;
  created_at: string;
}

export interface Role {
  id: number;
  name: string;
  permissions: string[];
}

export interface BusinessSettings {
  general: {
    company_name: string;
    logo_path: string | null;
    timezone: string;
    date_format: string;
    number_format: string;
  };
  inventory: {
    default_min_stock_threshold: number;
  };
  notifications: {
    low_stock_notifications_enabled: boolean;
    out_of_stock_notifications_enabled: boolean;
  };
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginationMeta;
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
}

export interface SingleResponse<T> {
  data: T;
}

export interface ApiErrorBody {
  message: string;
  errors?: Record<string, string[]>;
}
