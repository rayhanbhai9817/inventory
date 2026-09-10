export type Status = "active" | "inactive";

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
  status: Status;
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
  status: Status;
  created_at: string;
}

export interface Brand {
  id: number;
  name: string;
  slug: string;
  status: Status;
  created_at: string;
}

export interface Unit {
  id: number;
  name: string;
  short_name: string;
}

export interface Warehouse {
  id: number;
  name: string;
  code: string;
  address: string | null;
  phone: string | null;
  is_default: boolean;
  status: Status;
}

export interface Supplier {
  id: number;
  name: string;
  company_name: string | null;
  email: string | null;
  phone: string | null;
  address: string | null;
  opening_balance: number;
  current_balance: number;
  status: Status;
}

export interface Customer {
  id: number;
  name: string;
  email: string | null;
  phone: string | null;
  address: string | null;
  opening_balance: number;
  current_balance: number;
  status: Status;
}

export interface WarehouseStock {
  warehouse_id: number;
  warehouse_name?: string;
  quantity: number;
}

export interface Product {
  id: number;
  name: string;
  sku: string;
  barcode: string | null;
  category: { id: number; name: string } | null;
  brand: { id: number; name: string } | null;
  unit: { id: number; name: string; short_name: string } | null;
  cost_price: number;
  selling_price: number;
  min_stock_level: number;
  total_stock?: number;
  stocks?: WarehouseStock[];
  description: string | null;
  image_path: string | null;
  status: Status;
  created_at: string;
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
