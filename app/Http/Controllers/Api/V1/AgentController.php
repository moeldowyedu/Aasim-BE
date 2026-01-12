<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class AgentController extends Controller
{
    /**
     * List all available agents for the tenant.
     *
     * @OA\Get(
     *     path="/api/v1/tenant/agents",
     *     summary="List available agents",
     *     description="Get a list of all available agents for the tenant. This is a read-only endpoint.",
     *     operationId="tenantListAgents",
     *     tags={"Tenant - Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by category slug or ID",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by agent name or description",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=12)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Agents retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="per_page", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', 12);

        $agents = Agent::query()
            ->active()
            ->when($category, function ($query, $category) {
                return $query->whereHas('categories', function ($q) use ($category) {
                    $q->where('slug', $category)->orWhere('id', $category);
                });
            })
            ->when($search, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->with('categories')
            ->orderBy('is_featured', 'desc')
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $agents,
        ]);
    }

    /**
     * Get specific agent details for the tenant.
     *
     * @OA\Get(
     *     path="/api/v1/tenant/agents/{id}",
     *     summary="Get agent details",
     *     description="Get detailed information about a specific agent. This is a read-only endpoint.",
     *     operationId="tenantShowAgent",
     *     tags={"Tenant - Agents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Agent ID (UUID)",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Agent details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="slug", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="long_description", type="string"),
     *                 @OA\Property(property="icon_url", type="string"),
     *                 @OA\Property(property="banner_url", type="string"),
     *                 @OA\Property(property="capabilities", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="supported_languages", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="price_model", type="string"),
     *                 @OA\Property(property="base_price", type="number"),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="is_featured", type="boolean"),
     *                 @OA\Property(property="rating", type="number"),
     *                 @OA\Property(property="total_installs", type="integer"),
     *                 @OA\Property(property="categories", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=404, description="Agent not found")
     * )
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $agent = Agent::active()
            ->with('categories')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $agent,
        ]);
    }
}