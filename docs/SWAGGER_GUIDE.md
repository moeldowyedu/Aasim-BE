# Adding Swagger/OpenAPI Annotations

This guide shows you how to add Swagger annotations to document your API endpoints.

## Current Status

- **Complete Markdown Reference:** [`API_ENDPOINTS.md`](../API_ENDPOINTS.md) - All 170+ endpoints documented
- **Swagger UI:** http://127.0.0.1:8000/api/documentation - Currently shows 7 authentication endpoints
- **Swagger JSON:** `/storage/api-docs/api-docs.json`

## How to Add Swagger Annotations

### 1. Basic Controller Annotation

Add annotations directly above your controller methods:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

class AgentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/agents",
     *     summary="List all agents",
     *     description="Get a paginated list of all AI agents",
     *     operationId="getAgents",
     *     tags={"Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Agent"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        // Your code here
    }
}
```

### 2. Define Schemas

Create reusable schemas for your models:

```php
/**
 * @OA\Schema(
 *     schema="Agent",
 *     type="object",
 *     title="Agent",
 *     description="AI Agent model",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="name", type="string", example="Customer Support Agent"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="engine_id", type="string", format="uuid"),
 *     @OA\Property(property="is_active", type="boolean"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
```

### 3. POST Endpoint Example

```php
/**
 * @OA\Post(
 *     path="/agents",
 *     summary="Create new agent",
 *     operationId="createAgent",
 *     tags={"Agents"},
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name","engine_id","prompt"},
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(property="engine_id", type="string", format="uuid"),
 *             @OA\Property(property="prompt", type="string")
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Agent created successfully",
 *         @OA\JsonContent(ref="#/components/schemas/Agent")
 *     ),
 *     @OA\Response(response=422, description="Validation error")
 * )
 */
public function store(Request $request)
{
    // Your code
}
```

### 4. Regenerate Documentation

After adding annotations, regenerate the Swagger docs:

```bash
php artisan l5-swagger:generate
```

## Common Annotations

### Tags
```php
@OA\Tag(name="Agents", description="AI Agent management")
```

### Security (Bearer Token)
```php
security={{"bearerAuth":{}}}
```

### Request Parameters
```php
@OA\Parameter(
    name="id",
    in="path",
    description="Resource ID",
    required=true,
    @OA\Schema(type="string", format="uuid")
)
```

### Request Body
```php
@OA\RequestBody(
    required=true,
    @OA\JsonContent(
        required={"field1","field2"},
        @OA\Property(property="field1", type="string"),
        @OA\Property(property="field2", type="integer")
    )
)
```

### Responses
```php
@OA\Response(
    response=200,
    description="Success",
    @OA\JsonContent(
        @OA\Property(property="success", type="boolean", example=true),
        @OA\Property(property="data", type="object")
    )
)
```

## HTTP Methods

- `@OA\Get` - GET requests
- `@OA\Post` - POST requests
- `@OA\Put` - PUT requests
- `@OA\Patch` - PATCH requests
- `@OA\Delete` - DELETE requests

## Data Types

- `string` - Text
- `integer` - Whole numbers
- `number` - Floating point
- `boolean` - true/false
- `array` - Arrays
- `object` - Objects
- `uuid` - UUID format (use with format="uuid")
- `date-time` - ISO 8601 datetime

## Best Practices

1. **Group endpoints by tags** - Use consistent tag names (Agents, Users, Organizations, etc.)
2. **Document all responses** - Include success (200, 201) and error responses (400, 401, 404, 422, 500)
3. **Use schemas for reusability** - Define model schemas once, reference everywhere
4. **Include examples** - Add example values for better understanding
5. **Keep it updated** - Regenerate docs after changes

## Resources

- [OpenAPI 3.0 Specification](https://swagger.io/specification/)
- [Swagger-PHP Documentation](https://zircote.github.io/swagger-php/)
- [L5-Swagger Package](https://github.com/DarkaOnLine/L5-Swagger)

## Quick Reference

View the complete API reference in [`API_ENDPOINTS.md`](../API_ENDPOINTS.md) for all available endpoints.
