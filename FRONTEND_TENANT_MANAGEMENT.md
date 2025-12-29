# Frontend Implementation Guide: Tenant Management

**Module:** Admin Console - Tenant Management
**Base Path:** `/api/v1/admin/tenants`
**Authentication:** JWT Bearer Token + System Admin Role
**Guard:** `console`

---

## Table of Contents

1. [Overview](#overview)
2. [API Endpoints Reference](#api-endpoints-reference)
3. [UI/UX Specifications](#uiux-specifications)
4. [Component Architecture](#component-architecture)
5. [State Management](#state-management)
6. [Implementation Steps](#implementation-steps)
7. [Code Examples](#code-examples)
8. [Error Handling](#error-handling)

---

## Overview

The Tenant Management module allows system administrators to:
- View all tenants with filtering and pagination
- Create new tenants
- Update tenant information
- Manage tenant status (active/inactive)
- Manage subscriptions
- Extend trial periods
- View subscription history
- Delete tenants

---

## API Endpoints Reference

### 1. List Tenants
**Endpoint:** `GET /api/v1/admin/tenants`

**Query Parameters:**
```typescript
interface TenantListParams {
  page?: number;           // Page number (default: 1)
  per_page?: number;       // Items per page (default: 15)
  search?: string;         // Search by name, email, domain
  status?: 'active' | 'inactive' | 'pending_verification' | 'suspended';
  plan_id?: string;        // Filter by subscription plan
  sort_by?: 'name' | 'created_at' | 'subdomain';
  sort_order?: 'asc' | 'desc';
}
```

**Response:**
```typescript
interface TenantListResponse {
  success: boolean;
  data: {
    data: Tenant[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

interface Tenant {
  id: string;                    // UUID
  name: string;
  short_name?: string;
  domain?: string;
  subdomain_preference?: string;
  subdomain_activated_at?: string;
  status: 'active' | 'inactive' | 'pending_verification' | 'suspended';
  type: 'organization' | 'personal';
  plan_id?: string;
  trial_ends_at?: string;
  organization_id?: string;
  data?: object;
  created_at: string;
  updated_at: string;

  // Relations
  plan?: {
    id: string;
    name: string;
    price: number;
  };
  organization?: {
    id: string;
    name: string;
  };
}
```

**Example Request:**
```bash
GET /api/v1/admin/tenants?page=1&per_page=15&status=active&search=acme
Authorization: Bearer {jwt_token}
```

---

### 2. Get Tenant Statistics
**Endpoint:** `GET /api/v1/admin/tenants/statistics`

**Response:**
```typescript
interface TenantStatistics {
  success: boolean;
  data: {
    total_tenants: number;
    active_tenants: number;
    inactive_tenants: number;
    suspended_tenants: number;
    trial_tenants: number;
    paid_tenants: number;
    revenue_this_month: number;
    new_tenants_this_month: number;
    churn_rate: number;
    by_plan: {
      plan_name: string;
      count: number;
    }[];
  };
}
```

**Example Request:**
```bash
GET /api/v1/admin/tenants/statistics
Authorization: Bearer {jwt_token}
```

---

### 3. Get Single Tenant
**Endpoint:** `GET /api/v1/admin/tenants/{id}`

**Response:**
```typescript
interface TenantDetailResponse {
  success: boolean;
  data: Tenant & {
    users_count?: number;
    agents_count?: number;
    subscriptions?: Subscription[];
    current_subscription?: Subscription;
  };
}
```

**Example Request:**
```bash
GET /api/v1/admin/tenants/123e4567-e89b-12d3-a456-426614174000
Authorization: Bearer {jwt_token}
```

---

### 4. Create Tenant
**Endpoint:** `POST /api/v1/admin/tenants`

**Request Body:**
```typescript
interface CreateTenantRequest {
  name: string;                  // Required
  short_name?: string;
  subdomain_preference: string;  // Required, unique
  type: 'organization' | 'personal'; // Required
  status?: 'active' | 'pending_verification';
  plan_id?: string;
  trial_ends_at?: string;        // ISO 8601 date
  organization_id?: string;
  data?: {
    owner_email?: string;
    owner_name?: string;
    phone?: string;
    industry?: string;
    company_size?: string;
  };
}
```

**Response:**
```typescript
interface CreateTenantResponse {
  success: boolean;
  message: string;
  data: Tenant;
}
```

**Example Request:**
```bash
POST /api/v1/admin/tenants
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "name": "Acme Corporation",
  "short_name": "Acme",
  "subdomain_preference": "acme",
  "type": "organization",
  "plan_id": "plan-uuid",
  "trial_ends_at": "2025-01-30T00:00:00Z",
  "data": {
    "owner_email": "admin@acme.com",
    "owner_name": "John Doe",
    "industry": "Technology"
  }
}
```

---

### 5. Update Tenant
**Endpoint:** `PUT /api/v1/admin/tenants/{id}`

**Request Body:**
```typescript
interface UpdateTenantRequest {
  name?: string;
  short_name?: string;
  subdomain_preference?: string;
  domain?: string;
  type?: 'organization' | 'personal';
  organization_id?: string;
  data?: object;
}
```

**Response:**
```typescript
interface UpdateTenantResponse {
  success: boolean;
  message: string;
  data: Tenant;
}
```

**Example Request:**
```bash
PUT /api/v1/admin/tenants/123e4567-e89b-12d3-a456-426614174000
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "name": "Acme Corporation Inc.",
  "domain": "acme.com"
}
```

---

### 6. Update Tenant Status
**Endpoint:** `PUT /api/v1/admin/tenants/{id}/status`

**Request Body:**
```typescript
interface UpdateStatusRequest {
  status: 'active' | 'inactive' | 'suspended' | 'pending_verification';
  reason?: string; // For suspension/deactivation
}
```

**Response:**
```typescript
interface UpdateStatusResponse {
  success: boolean;
  message: string;
  data: Tenant;
}
```

**Example Request:**
```bash
PUT /api/v1/admin/tenants/123e4567-e89b-12d3-a456-426614174000/status
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "status": "suspended",
  "reason": "Payment overdue"
}
```

---

### 7. Deactivate Tenant
**Endpoint:** `POST /api/v1/admin/tenants/{id}/deactivate`

**Request Body:**
```typescript
interface DeactivateRequest {
  reason?: string;
}
```

**Response:**
```typescript
interface DeactivateResponse {
  success: boolean;
  message: string;
  data: Tenant;
}
```

**Example Request:**
```bash
POST /api/v1/admin/tenants/123e4567-e89b-12d3-a456-426614174000/deactivate
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "reason": "Account closed by user request"
}
```

---

### 8. Reactivate Tenant
**Endpoint:** `POST /api/v1/admin/tenants/{id}/reactivate`

**Response:**
```typescript
interface ReactivateResponse {
  success: boolean;
  message: string;
  data: Tenant;
}
```

**Example Request:**
```bash
POST /api/v1/admin/tenants/123e4567-e89b-12d3-a456-426614174000/reactivate
Authorization: Bearer {jwt_token}
```

---

### 9. Change Subscription
**Endpoint:** `PUT /api/v1/admin/tenants/{id}/subscription`

**Request Body:**
```typescript
interface ChangeSubscriptionRequest {
  plan_id: string;              // Required
  effective_date?: string;      // ISO 8601 date (default: now)
  prorate?: boolean;            // Default: true
  trial_ends_at?: string | null; // Extend or remove trial
}
```

**Response:**
```typescript
interface ChangeSubscriptionResponse {
  success: boolean;
  message: string;
  data: {
    tenant: Tenant;
    subscription: Subscription;
  };
}
```

**Example Request:**
```bash
PUT /api/v1/admin/tenants/123e4567-e89b-12d3-a456-426614174000/subscription
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "plan_id": "new-plan-uuid",
  "effective_date": "2025-01-01T00:00:00Z",
  "prorate": true
}
```

---

### 10. Get Subscription History
**Endpoint:** `GET /api/v1/admin/tenants/{id}/subscription-history`

**Response:**
```typescript
interface SubscriptionHistoryResponse {
  success: boolean;
  data: {
    id: string;
    tenant_id: string;
    plan_id: string;
    status: 'active' | 'cancelled' | 'expired';
    started_at: string;
    ended_at?: string;
    cancelled_at?: string;
    plan: {
      id: string;
      name: string;
      price: number;
    };
  }[];
}
```

**Example Request:**
```bash
GET /api/v1/admin/tenants/123e4567-e89b-12d3-a456-426614174000/subscription-history
Authorization: Bearer {jwt_token}
```

---

### 11. Extend Trial
**Endpoint:** `POST /api/v1/admin/tenants/{id}/extend-trial`

**Request Body:**
```typescript
interface ExtendTrialRequest {
  days: number;                 // Number of days to extend
  reason?: string;
}
```

**Response:**
```typescript
interface ExtendTrialResponse {
  success: boolean;
  message: string;
  data: {
    tenant: Tenant;
    old_trial_end: string;
    new_trial_end: string;
  };
}
```

**Example Request:**
```bash
POST /api/v1/admin/tenants/123e4567-e89b-12d3-a456-426614174000/extend-trial
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "days": 14,
  "reason": "Customer requested extension"
}
```

---

### 12. Delete Tenant
**Endpoint:** `DELETE /api/v1/admin/tenants/{id}`

**Query Parameters:**
```typescript
interface DeleteTenantParams {
  force?: boolean; // Permanent deletion (default: soft delete)
}
```

**Response:**
```typescript
interface DeleteTenantResponse {
  success: boolean;
  message: string;
}
```

**Example Request:**
```bash
DELETE /api/v1/admin/tenants/123e4567-e89b-12d3-a456-426614174000?force=false
Authorization: Bearer {jwt_token}
```

---

## UI/UX Specifications

### Page Structure

```
Admin Console
└── Tenants
    ├── Dashboard (Statistics Overview)
    ├── List View (Table with filters)
    ├── Create Tenant (Modal/Form)
    ├── Edit Tenant (Modal/Form)
    └── Tenant Detail View
        ├── Overview Tab
        ├── Subscription Tab
        ├── Users Tab
        ├── Activity Log Tab
        └── Settings Tab
```

### 1. Tenants Dashboard (Statistics)

**Layout:**
```
┌─────────────────────────────────────────────────┐
│  TENANT MANAGEMENT                              │
├─────────────────────────────────────────────────┤
│  ┌──────────┐  ┌──────────┐  ┌──────────┐      │
│  │  Total   │  │  Active  │  │ Suspended│      │
│  │  1,234   │  │  1,156   │  │    45    │      │
│  └──────────┘  └──────────┘  └──────────┘      │
│                                                  │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐      │
│  │  Trial   │  │   Paid   │  │   New    │      │
│  │   178    │  │   978    │  │    23    │      │
│  └──────────┘  └──────────┘  └──────────┘      │
│                                                  │
│  Revenue This Month: $45,678                    │
│  Churn Rate: 2.3%                               │
│                                                  │
│  Tenants by Plan:                               │
│  ────────────────────────────────────────       │
│  ▓▓▓▓▓▓▓▓░░ Starter (567)                      │
│  ▓▓▓▓▓▓░░░░ Professional (423)                 │
│  ▓▓░░░░░░░░ Enterprise (234)                   │
└─────────────────────────────────────────────────┘
```

**Components:**
- StatCard (Total, Active, Suspended, Trial, Paid, New)
- RevenueCard
- ChurnRateCard
- TenantsByPlanChart (Horizontal bar chart)

---

### 2. Tenants List View

**Layout:**
```
┌─────────────────────────────────────────────────┐
│ [+ New Tenant]          [Search: ________]      │
│                                                  │
│ Filters:                                         │
│ [Status: All ▼] [Plan: All ▼] [Type: All ▼]    │
│                                                  │
├─────────────────────────────────────────────────┤
│ Name        │ Status  │ Plan   │ Created  │ ⚙  │
├─────────────────────────────────────────────────┤
│ Acme Corp   │ ●Active │ Pro    │ Jan 2025 │... │
│ Beta Inc    │ ●Active │ Start  │ Jan 2025 │... │
│ Gamma LLC   │ ○Trial  │ Free   │ Dec 2024 │... │
│ Delta Co    │ ⊗Susp   │ Pro    │ Dec 2024 │... │
├─────────────────────────────────────────────────┤
│                Showing 1-15 of 234              │
│          [< Previous]  [1][2][3]  [Next >]      │
└─────────────────────────────────────────────────┘
```

**Features:**
- Search by name, email, domain
- Filter by status, plan, type
- Sort by name, created date, subdomain
- Pagination
- Quick actions dropdown (View, Edit, Suspend, Delete)
- Bulk actions (Export, Bulk status change)
- Status badges with colors:
  - 🟢 Active (Green)
  - 🟡 Trial (Yellow)
  - 🔴 Suspended (Red)
  - ⚪ Inactive (Gray)

---

### 3. Create/Edit Tenant Form

**Layout:**
```
┌─────────────────────────────────────────────────┐
│ Create New Tenant                          [✕]  │
├─────────────────────────────────────────────────┤
│                                                  │
│ Basic Information                                │
│ ───────────────                                  │
│ Tenant Name *                                    │
│ [_______________________________]                │
│                                                  │
│ Short Name                                       │
│ [_______________________________]                │
│                                                  │
│ Subdomain * (unique)                             │
│ [_______] .obsolio.com                           │
│                                                  │
│ Custom Domain (optional)                         │
│ [_______________________________]                │
│                                                  │
│ Type *                                           │
│ ○ Organization  ○ Personal                       │
│                                                  │
│ ───────────────                                  │
│ Subscription                                     │
│ ───────────────                                  │
│ Plan                                             │
│ [Select Plan ▼]                                  │
│                                                  │
│ Trial End Date                                   │
│ [📅 Select Date]                                 │
│                                                  │
│ ───────────────                                  │
│ Owner Information                                │
│ ───────────────                                  │
│ Owner Email                                      │
│ [_______________________________]                │
│                                                  │
│ Owner Name                                       │
│ [_______________________________]                │
│                                                  │
│ Phone                                            │
│ [_______________________________]                │
│                                                  │
├─────────────────────────────────────────────────┤
│              [Cancel]  [Create Tenant]           │
└─────────────────────────────────────────────────┘
```

**Validation Rules:**
- Tenant Name: Required, min 2 chars
- Subdomain: Required, unique, lowercase, alphanumeric + hyphens
- Type: Required
- Owner Email: Valid email format if provided
- Plan: Optional but recommended

---

### 4. Tenant Detail View

**Layout:**
```
┌─────────────────────────────────────────────────┐
│ ← Back to Tenants                               │
├─────────────────────────────────────────────────┤
│ Acme Corporation                                 │
│ acme.obsolio.com               ●Active           │
│                                                  │
│ [Overview][Subscription][Users][Activity][⚙]    │
├─────────────────────────────────────────────────┤
│ OVERVIEW TAB                                     │
│                                                  │
│ Tenant Information                               │
│ ──────────────────                               │
│ ID: 123e4567-e89b-12d3-a456-426614174000        │
│ Name: Acme Corporation                           │
│ Subdomain: acme.obsolio.com                      │
│ Custom Domain: acme.com                          │
│ Type: Organization                               │
│ Status: Active                                   │
│ Created: Jan 15, 2025                            │
│                                                  │
│ Subscription                                     │
│ ──────────────────                               │
│ Plan: Professional ($99/month)                   │
│ Status: Active                                   │
│ Started: Jan 15, 2025                            │
│ Next Billing: Feb 15, 2025                       │
│ Trial: No                                        │
│                                                  │
│ Statistics                                       │
│ ──────────────────                               │
│ Users: 15                                        │
│ Agents Installed: 8                              │
│ Storage Used: 2.3 GB / 10 GB                     │
│                                                  │
│ Quick Actions                                    │
│ ──────────────────                               │
│ [Change Subscription] [Extend Trial]             │
│ [Suspend Tenant] [Impersonate]                   │
│                                                  │
└─────────────────────────────────────────────────┘
```

---

## Component Architecture

### Recommended Structure (React/Vue/Angular)

```
src/
├── pages/
│   └── admin/
│       └── tenants/
│           ├── index.tsx                 # List view
│           ├── dashboard.tsx             # Statistics dashboard
│           ├── create.tsx                # Create tenant
│           ├── [id]/
│           │   ├── index.tsx             # Detail view
│           │   ├── overview.tsx          # Overview tab
│           │   ├── subscription.tsx      # Subscription tab
│           │   ├── users.tsx             # Users tab
│           │   └── activity.tsx          # Activity log
│           └── components/
│               ├── TenantTable.tsx       # Main table
│               ├── TenantFilters.tsx     # Filter controls
│               ├── TenantForm.tsx        # Create/edit form
│               ├── TenantCard.tsx        # Tenant card
│               ├── StatusBadge.tsx       # Status indicator
│               ├── ActionMenu.tsx        # Actions dropdown
│               └── stats/
│                   ├── StatCard.tsx      # Stat card component
│                   └── PlanChart.tsx     # Plan distribution
│
├── api/
│   └── tenants.ts                        # API client
│
├── hooks/
│   ├── useTenants.ts                     # Tenants list hook
│   ├── useTenant.ts                      # Single tenant hook
│   └── useTenantStats.ts                 # Statistics hook
│
├── stores/
│   └── tenantStore.ts                    # State management
│
└── types/
    └── tenant.ts                         # TypeScript interfaces
```

---

## State Management

### Zustand Example

```typescript
// stores/tenantStore.ts
import create from 'zustand';
import { Tenant } from '@/types/tenant';

interface TenantStore {
  tenants: Tenant[];
  currentTenant: Tenant | null;
  statistics: TenantStatistics | null;
  filters: TenantFilters;
  pagination: Pagination;
  loading: boolean;
  error: string | null;

  // Actions
  setTenants: (tenants: Tenant[]) => void;
  setCurrentTenant: (tenant: Tenant | null) => void;
  setStatistics: (stats: TenantStatistics) => void;
  setFilters: (filters: Partial<TenantFilters>) => void;
  setPagination: (pagination: Pagination) => void;
  setLoading: (loading: boolean) => void;
  setError: (error: string | null) => void;
  reset: () => void;
}

export const useTenantStore = create<TenantStore>((set) => ({
  tenants: [],
  currentTenant: null,
  statistics: null,
  filters: {
    search: '',
    status: null,
    plan_id: null,
    type: null,
  },
  pagination: {
    page: 1,
    per_page: 15,
    total: 0,
  },
  loading: false,
  error: null,

  setTenants: (tenants) => set({ tenants }),
  setCurrentTenant: (tenant) => set({ currentTenant: tenant }),
  setStatistics: (statistics) => set({ statistics }),
  setFilters: (filters) => set((state) => ({
    filters: { ...state.filters, ...filters }
  })),
  setPagination: (pagination) => set({ pagination }),
  setLoading: (loading) => set({ loading }),
  setError: (error) => set({ error }),
  reset: () => set({
    tenants: [],
    currentTenant: null,
    filters: { search: '', status: null, plan_id: null, type: null },
    pagination: { page: 1, per_page: 15, total: 0 },
    loading: false,
    error: null,
  }),
}));
```

---

## Implementation Steps

### Phase 1: Setup & Configuration (Day 1)

1. **Install Dependencies**
   ```bash
   npm install axios @tanstack/react-query zustand date-fns
   ```

2. **Configure API Client**
   ```typescript
   // api/client.ts
   import axios from 'axios';

   const apiClient = axios.create({
     baseURL: process.env.NEXT_PUBLIC_API_URL,
     headers: {
       'Content-Type': 'application/json',
     },
   });

   // Add JWT interceptor
   apiClient.interceptors.request.use((config) => {
     const token = localStorage.getItem('jwt_token');
     if (token) {
       config.headers.Authorization = `Bearer ${token}`;
     }
     return config;
   });

   export default apiClient;
   ```

3. **Create TypeScript Types**
   ```typescript
   // types/tenant.ts
   export interface Tenant {
     id: string;
     name: string;
     short_name?: string;
     domain?: string;
     subdomain_preference?: string;
     subdomain_activated_at?: string;
     status: 'active' | 'inactive' | 'pending_verification' | 'suspended';
     type: 'organization' | 'personal';
     plan_id?: string;
     trial_ends_at?: string;
     organization_id?: string;
     created_at: string;
     updated_at: string;
     plan?: Plan;
     organization?: Organization;
   }

   // Add more interfaces...
   ```

### Phase 2: API Integration (Day 2-3)

1. **Create API Service**
   ```typescript
   // api/tenants.ts
   import apiClient from './client';
   import { Tenant, TenantListParams } from '@/types/tenant';

   export const tenantsApi = {
     list: async (params: TenantListParams) => {
       const { data } = await apiClient.get('/admin/tenants', { params });
       return data;
     },

     get: async (id: string) => {
       const { data } = await apiClient.get(`/admin/tenants/${id}`);
       return data;
     },

     create: async (tenant: CreateTenantRequest) => {
       const { data } = await apiClient.post('/admin/tenants', tenant);
       return data;
     },

     update: async (id: string, tenant: UpdateTenantRequest) => {
       const { data } = await apiClient.put(`/admin/tenants/${id}`, tenant);
       return data;
     },

     delete: async (id: string, force = false) => {
       const { data } = await apiClient.delete(`/admin/tenants/${id}`, {
         params: { force },
       });
       return data;
     },

     updateStatus: async (id: string, status: string, reason?: string) => {
       const { data } = await apiClient.put(`/admin/tenants/${id}/status`, {
         status,
         reason,
       });
       return data;
     },

     changeSubscription: async (id: string, req: ChangeSubscriptionRequest) => {
       const { data } = await apiClient.put(
         `/admin/tenants/${id}/subscription`,
         req
       );
       return data;
     },

     extendTrial: async (id: string, days: number, reason?: string) => {
       const { data } = await apiClient.post(
         `/admin/tenants/${id}/extend-trial`,
         { days, reason }
       );
       return data;
     },

     statistics: async () => {
       const { data } = await apiClient.get('/admin/tenants/statistics');
       return data;
     },
   };
   ```

2. **Create React Query Hooks**
   ```typescript
   // hooks/useTenants.ts
   import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
   import { tenantsApi } from '@/api/tenants';

   export const useTenants = (params: TenantListParams) => {
     return useQuery({
       queryKey: ['tenants', params],
       queryFn: () => tenantsApi.list(params),
     });
   };

   export const useTenant = (id: string) => {
     return useQuery({
       queryKey: ['tenant', id],
       queryFn: () => tenantsApi.get(id),
       enabled: !!id,
     });
   };

   export const useCreateTenant = () => {
     const queryClient = useQueryClient();

     return useMutation({
       mutationFn: tenantsApi.create,
       onSuccess: () => {
         queryClient.invalidateQueries({ queryKey: ['tenants'] });
       },
     });
   };

   // Add more hooks...
   ```

### Phase 3: UI Components (Day 4-7)

1. **Stat Cards Component**
2. **Tenants Table Component**
3. **Filters Component**
4. **Tenant Form Component**
5. **Detail View Components**

### Phase 4: Pages (Day 8-10)

1. **Dashboard Page**
2. **List Page**
3. **Detail Page with Tabs**

### Phase 5: Testing & Polish (Day 11-12)

1. **Unit Tests**
2. **Integration Tests**
3. **UI Polish**
4. **Error Handling**

---

## Code Examples

### React Component Example: Tenant List

```tsx
// pages/admin/tenants/index.tsx
import { useState } from 'react';
import { useTenants, useDeleteTenant } from '@/hooks/useTenants';
import TenantTable from './components/TenantTable';
import TenantFilters from './components/TenantFilters';
import CreateTenantModal from './components/CreateTenantModal';

export default function TenantsPage() {
  const [filters, setFilters] = useState({
    page: 1,
    per_page: 15,
    search: '',
    status: null,
  });

  const [showCreateModal, setShowCreateModal] = useState(false);

  const { data, isLoading, error } = useTenants(filters);
  const deleteTenant = useDeleteTenant();

  const handleFilterChange = (newFilters) => {
    setFilters({ ...filters, ...newFilters, page: 1 });
  };

  const handlePageChange = (page) => {
    setFilters({ ...filters, page });
  };

  const handleDelete = async (id) => {
    if (confirm('Are you sure you want to delete this tenant?')) {
      await deleteTenant.mutateAsync(id);
    }
  };

  if (error) return <ErrorMessage error={error} />;

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Tenant Management</h1>
        <button
          onClick={() => setShowCreateModal(true)}
          className="btn btn-primary"
        >
          + New Tenant
        </button>
      </div>

      <TenantFilters
        filters={filters}
        onChange={handleFilterChange}
      />

      <TenantTable
        tenants={data?.data.data || []}
        loading={isLoading}
        onDelete={handleDelete}
        pagination={{
          current: data?.data.current_page || 1,
          total: data?.data.last_page || 1,
          onChange: handlePageChange,
        }}
      />

      {showCreateModal && (
        <CreateTenantModal
          onClose={() => setShowCreateModal(false)}
        />
      )}
    </div>
  );
}
```

---

## Error Handling

### Common Error Scenarios

1. **401 Unauthorized**
   - Token expired
   - Action: Redirect to login

2. **403 Forbidden**
   - Insufficient permissions
   - Action: Show permission error

3. **404 Not Found**
   - Tenant doesn't exist
   - Action: Redirect to list with error message

4. **422 Validation Error**
   - Invalid input data
   - Action: Show field-level errors

5. **500 Server Error**
   - Backend error
   - Action: Show generic error message

### Error Handling Pattern

```typescript
try {
  await tenantsApi.create(formData);
  toast.success('Tenant created successfully');
  navigate('/admin/tenants');
} catch (error) {
  if (error.response?.status === 422) {
    setFieldErrors(error.response.data.errors);
  } else if (error.response?.status === 403) {
    toast.error('You don\'t have permission to create tenants');
  } else {
    toast.error('Failed to create tenant. Please try again.');
  }
}
```

---

## Summary Checklist

### API Integration
- [ ] Configure API client with JWT interceptor
- [ ] Create TypeScript types
- [ ] Implement all API methods
- [ ] Create React Query hooks
- [ ] Add error handling

### UI Components
- [ ] StatCard component
- [ ] TenantTable component
- [ ] TenantFilters component
- [ ] StatusBadge component
- [ ] TenantForm component
- [ ] ActionMenu component

### Pages
- [ ] Dashboard page
- [ ] List page
- [ ] Create page/modal
- [ ] Edit page/modal
- [ ] Detail page with tabs

### Features
- [ ] Search functionality
- [ ] Filter by status/plan/type
- [ ] Pagination
- [ ] Sort functionality
- [ ] CRUD operations
- [ ] Status management
- [ ] Subscription management
- [ ] Trial extension
- [ ] Bulk actions

### Testing
- [ ] Unit tests for components
- [ ] Integration tests for API
- [ ] E2E tests for critical flows

---

**Last Updated:** 2025-12-29
