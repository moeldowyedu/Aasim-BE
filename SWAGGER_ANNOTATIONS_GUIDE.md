# Swagger/OpenAPI Annotations Guide

This guide provides complete Swagger annotations for all Admin endpoints.

## How to Apply

Copy the annotations from this guide and paste them above the corresponding controller methods.

---

## TenantManagementController Annotations

### 1. index() - List Tenants

```php
/**
 * @OA\Get(
 *     path="/api/v1/admin/tenants",
 *     summary="List all tenants with filtering and pagination",
 *     tags={"Admin - Tenants"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="search",
 *         in="query",
 *         description="Search by name, email, or subdomain",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
 *         description="Filter by status",
 *         required=false,
 *         @OA\Schema(type="string", enum={"active", "inactive", "pending_verification", "suspended"})
 *     ),
 *     @OA\Parameter(
 *         name="type",
 *         in="query",
 *         description="Filter by type",
 *         required=false,
 *         @OA\Schema(type="string", enum={"organization", "personal"})
 *     ),
 *     @OA\Parameter(
 *         name="plan_id",
 *         in="query",
 *         description="Filter by subscription plan ID",
 *         required=false,
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\Parameter(
 *         name="has_subscription",
 *         in="query",
 *         description="Filter by subscription status",
 *         required=false,
 *         @OA\Schema(type="string", enum={"true", "false"})
 *     ),
 *     @OA\Parameter(
 *         name="sort_by",
 *         in="query",
 *         description="Sort by field",
 *         required=false,
 *         @OA\Schema(type="string", enum={"created_at", "name", "email", "type", "status"}, default="created_at")
 *     ),
 *     @OA\Parameter(
 *         name="sort_order",
 *         in="query",
 *         description="Sort order",
 *         required=false,
 *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")
 *     ),
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         description="Items per page",
 *         required=false,
 *         @OA\Schema(type="integer", default=20, minimum=1, maximum=100)
 *     ),
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         description="Page number",
 *         required=false,
 *         @OA\Schema(type="integer", default=1, minimum=1)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Tenants retrieved successfully",
 *         @OA\JsonContent(ref="#/components/schemas/PaginatedResponse")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/Forbidden")
 * )
 */
public function index(Request $request): JsonResponse
```

### 2. statistics() - Get Tenant Statistics

```php
/**
 * @OA\Get(
 *     path="/api/v1/admin/tenants/statistics",
 *     summary="Get tenant statistics and metrics",
 *     tags={"Admin - Tenants"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Statistics retrieved successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="total_tenants", type="integer", example=1234),
 *                 @OA\Property(property="active_tenants", type="integer", example=1156),
 *                 @OA\Property(property="inactive_tenants", type="integer", example=33),
 *                 @OA\Property(property="suspended_tenants", type="integer", example=45),
 *                 @OA\Property(property="trial_tenants", type="integer", example=178),
 *                 @OA\Property(property="paid_tenants", type="integer", example=978),
 *                 @OA\Property(property="revenue_this_month", type="number", format="float", example=45678.50),
 *                 @OA\Property(property="new_tenants_this_month", type="integer", example=23),
 *                 @OA\Property(property="churn_rate", type="number", format="float", example=2.3),
 *                 @OA\Property(
 *                     property="by_plan",
 *                     type="array",
 *                     @OA\Items(
 *                         @OA\Property(property="plan_name", type="string", example="Professional"),
 *                         @OA\Property(property="count", type="integer", example=567)
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/Forbidden")
 * )
 */
public function statistics(Request $request): JsonResponse
```

### 3. show() - Get Single Tenant

```php
/**
 * @OA\Get(
 *     path="/api/v1/admin/tenants/{id}",
 *     summary="Get detailed information about a specific tenant",
 *     tags={"Admin - Tenants"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="Tenant ID",
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Tenant details retrieved successfully",
 *         @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
 *     @OA\Response(response=404, ref="#/components/responses/NotFound")
 * )
 */
public function show(string $id): JsonResponse
```

### 4. store() - Create Tenant

```php
/**
 * @OA\Post(
 *     path="/api/v1/admin/tenants",
 *     summary="Create a new tenant",
 *     tags={"Admin - Tenants"},
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name", "subdomain_preference", "type"},
 *             @OA\Property(property="name", type="string", example="Acme Corporation"),
 *             @OA\Property(property="short_name", type="string", example="Acme"),
 *             @OA\Property(property="subdomain_preference", type="string", example="acme"),
 *             @OA\Property(property="domain", type="string", nullable=true, example="acme.com"),
 *             @OA\Property(property="type", type="string", enum={"organization", "personal"}, example="organization"),
 *             @OA\Property(property="status", type="string", enum={"active", "pending_verification"}, default="active"),
 *             @OA\Property(property="plan_id", type="string", format="uuid", nullable=true),
 *             @OA\Property(property="trial_ends_at", type="string", format="date-time", nullable=true),
 *             @OA\Property(property="organization_id", type="string", format="uuid", nullable=true),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 nullable=true,
 *                 @OA\Property(property="owner_email", type="string", format="email"),
 *                 @OA\Property(property="owner_name", type="string"),
 *                 @OA\Property(property="phone", type="string"),
 *                 @OA\Property(property="industry", type="string")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Tenant created successfully",
 *         @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError")
 * )
 */
public function store(Request $request): JsonResponse
```

### 5. update() - Update Tenant

```php
/**
 * @OA\Put(
 *     path="/api/v1/admin/tenants/{id}",
 *     summary="Update tenant information",
 *     tags={"Admin - Tenants"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="Tenant ID",
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(property="short_name", type="string"),
 *             @OA\Property(property="subdomain_preference", type="string"),
 *             @OA\Property(property="domain", type="string", nullable=true),
 *             @OA\Property(property="type", type="string", enum={"organization", "personal"}),
 *             @OA\Property(property="organization_id", type="string", format="uuid", nullable=true),
 *             @OA\Property(property="data", type="object", nullable=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Tenant updated successfully",
 *         @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
 *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError")
 * )
 */
public function update(Request $request, string $id): JsonResponse
```

### 6. destroy() - Delete Tenant

```php
/**
 * @OA\Delete(
 *     path="/api/v1/admin/tenants/{id}",
 *     summary="Delete a tenant",
 *     tags={"Admin - Tenants"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="Tenant ID",
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\Parameter(
 *         name="force",
 *         in="query",
 *         description="Force permanent deletion",
 *         required=false,
 *         @OA\Schema(type="boolean", default=false)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Tenant deleted successfully",
 *         @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
 *     @OA\Response(response=404, ref="#/components/responses/NotFound")
 * )
 */
public function destroy(Request $request, string $id): JsonResponse
```

### 7. updateStatus() - Update Tenant Status

```php
/**
 * @OA\Put(
 *     path="/api/v1/admin/tenants/{id}/status",
 *     summary="Update tenant status",
 *     tags={"Admin - Tenants"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="Tenant ID",
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"status"},
 *             @OA\Property(
 *                 property="status",
 *                 type="string",
 *                 enum={"active", "inactive", "suspended", "pending_verification"}
 *             ),
 *             @OA\Property(property="reason", type="string", nullable=true, description="Reason for status change")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Status updated successfully",
 *         @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
 *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError")
 * )
 */
public function updateStatus(Request $request, string $id): JsonResponse
```

### 8. deactivate() - Deactivate Tenant

```php
/**
 * @OA\Post(
 *     path="/api/v1/admin/tenants/{id}/deactivate",
 *     summary="Deactivate a tenant",
 *     tags={"Admin - Tenants"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="Tenant ID",
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             @OA\Property(property="reason", type="string", nullable=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Tenant deactivated successfully",
 *         @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
 *     @OA\Response(response=404, ref="#/components/responses/NotFound")
 * )
 */
public function deactivate(Request $request, string $id): JsonResponse
```

### 9. reactivate() - Reactivate Tenant

```php
/**
 * @OA\Post(
 *     path="/api/v1/admin/tenants/{id}/reactivate",
 *     summary="Reactivate a deactivated tenant",
 *     tags={"Admin - Tenants"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="Tenant ID",
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Tenant reactivated successfully",
 *         @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
 *     @OA\Response(response=404, ref="#/components/responses/NotFound")
 * )
 */
public function reactivate(string $id): JsonResponse
```

### 10. changeSubscription() - Change Subscription

```php
/**
 * @OA\Put(
 *     path="/api/v1/admin/tenants/{id}/subscription",
 *     summary="Change tenant subscription plan",
 *     tags={"Admin - Tenants"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="Tenant ID",
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"plan_id"},
 *             @OA\Property(property="plan_id", type="string", format="uuid"),
 *             @OA\Property(property="effective_date", type="string", format="date-time", nullable=true),
 *             @OA\Property(property="prorate", type="boolean", default=true),
 *             @OA\Property(property="trial_ends_at", type="string", format="date-time", nullable=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Subscription changed successfully",
 *         @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
 *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError")
 * )
 */
public function changeSubscription(Request $request, string $id): JsonResponse
```

### 11. subscriptionHistory() - Get Subscription History

```php
/**
 * @OA\Get(
 *     path="/api/v1/admin/tenants/{id}/subscription-history",
 *     summary="Get tenant subscription history",
 *     tags={"Admin - Tenants"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="Tenant ID",
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Subscription history retrieved successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="id", type="string", format="uuid"),
 *                     @OA\Property(property="tenant_id", type="string", format="uuid"),
 *                     @OA\Property(property="plan_id", type="string", format="uuid"),
 *                     @OA\Property(property="status", type="string", enum={"active", "cancelled", "expired"}),
 *                     @OA\Property(property="started_at", type="string", format="date-time"),
 *                     @OA\Property(property="ended_at", type="string", format="date-time", nullable=true),
 *                     @OA\Property(property="cancelled_at", type="string", format="date-time", nullable=true)
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
 *     @OA\Response(response=404, ref="#/components/responses/NotFound")
 * )
 */
public function subscriptionHistory(string $id): JsonResponse
```

### 12. extendTrial() - Extend Trial

```php
/**
 * @OA\Post(
 *     path="/api/v1/admin/tenants/{id}/extend-trial",
 *     summary="Extend tenant trial period",
 *     tags={"Admin - Tenants"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="Tenant ID",
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"days"},
 *             @OA\Property(property="days", type="integer", minimum=1, example=14, description="Number of days to extend"),
 *             @OA\Property(property="reason", type="string", nullable=true, description="Reason for extension")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Trial extended successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="old_trial_end", type="string", format="date-time"),
 *                 @OA\Property(property="new_trial_end", type="string", format="date-time")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=403, ref="#/components/responses/Unauthorized"),
 *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError")
 * )
 */
public function extendTrial(Request $request, string $id): JsonResponse
```

---

## How to Apply These Annotations

1. Open the file: `app/Http/Controllers/Api/V1/Admin/TenantManagementController.php`

2. Find each method listed above

3. Copy the corresponding annotation block from this guide

4. Paste it directly above the method declaration

5. After adding all annotations, run:
```bash
php artisan l5-swagger:generate
```

6. View the documentation at: `http://your-domain/api/documentation`

---

## Remaining Controllers

Apply the same pattern to:
- AdminController
- AdminOrganizationController
- AdminSubscriptionController
- AdminImpersonationController
- TenantMembershipController
- All other controllers

---

**Last Updated:** 2025-12-29
