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
  supplier_id: number | null;
  supplier?: { id: number; name: string } | null;
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

export type SupplierStatus = "active" | "inactive" | "archived";

export interface Supplier {
  id: number;
  name: string;
  company_name: string | null;
  contact_person: string | null;
  email: string | null;
  phone: string | null;
  address: string | null;
  city: string | null;
  state: string | null;
  country: string | null;
  website: string | null;
  tax_number: string | null;
  notes: string | null;
  status: SupplierStatus;
  product_count?: number;
  archived_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface SuppliedProduct {
  product_id: number;
  product_name: string | null;
  sku: string | null;
  supplier_sku: string | null;
  supplier_product_name: string | null;
  is_primary: boolean;
  status: string;
  first_supplied_at: string | null;
  last_supplied_at: string | null;
}

export interface SupplierDetail {
  supplier: Supplier;
  supplied_products: SuppliedProduct[];
  totals: { total_products_supplied: number; total_stock_in_quantity: number };
  recent_stock_in: StockBatch[];
}

export interface SupplierProduct {
  id: number;
  supplier?: { id: number; name: string };
  product?: { id: number; name: string; sku: string };
  supplier_sku: string | null;
  supplier_product_name: string | null;
  notes: string | null;
  is_primary: boolean;
  status: string;
  created_at: string;
}

export interface ProductPrice {
  id: number;
  product_id: number;
  price: string;
  currency: string;
  effective_date: string;
  notes: string | null;
  created_by?: string | null;
  created_at: string;
}

export interface ProductPriceCatalogRow {
  product_id: number;
  product_name: string;
  sku: string;
  category: string | null;
  status: string;
  current_price: string | null;
  currency: string | null;
  effective_date: string | null;
  has_price: boolean;
}

export interface SupplierReportRow {
  supplier_id: number;
  supplier_name: string;
  status: string;
  products_supplied: number;
  batches_received: number;
  total_units_supplied: number;
  last_stock_in_at: string | null;
}

export interface ProductSupplierReportRow {
  product_id: number;
  product_name: string;
  sku: string;
  category: string | null;
  primary_supplier: string | null;
  supplier_count: number;
  suppliers: string[];
  has_supplier: boolean;
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
  supplier_info: {
    primary_supplier: { id: number; name: string; supplier_sku: string | null } | null;
    other_suppliers: { id: number; name: string }[];
    last_stock_in_supplier: { id: number; name: string; received_at: string | null } | null;
    has_supplier: boolean;
  };
  price_info: {
    current_price: string | null;
    currency: string | null;
    effective_date: string | null;
    has_price: boolean;
  };
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
  supplier_summary: {
    total_suppliers: number;
    active_suppliers: number;
    recently_used_suppliers: number;
    top_suppliers_by_quantity: { id: number; name: string; total_units_supplied: number }[];
  };
  product_alerts: {
    missing_supplier_count: number;
    missing_supplier_sample: { id: number; name: string; sku: string }[];
    missing_price_count: number;
    missing_price_sample: { id: number; name: string; sku: string }[];
  };
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
  | "admin_activity"
  | "supplier_added"
  | "product_missing_supplier"
  | "product_missing_price";

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
