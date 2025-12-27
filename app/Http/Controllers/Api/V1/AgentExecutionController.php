<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentEndpoint;
use App\Models\AgentRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentExecutionController extends Controller
{
    /**
     * Execute an agent asynchronously.
     *
     * POST /v1/agents/{id}/run
     *
     * @param string $id Agent UUID
     * @param Request $request
     * @return JsonResponse
     */
    public function run(string $id, Request $request): JsonResponse
    {
        try {
            // Validate input
            $validated = $request->validate([
                'input' => 'required|array',
            ]);

            // Find the agent
            $agent = Agent::findOrFail($id);

            // Check if agent is active
            if (!$agent->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agent is not active',
                ], 400);
            }

            // Get the trigger endpoint
            $triggerEndpoint = AgentEndpoint::where('agent_id', $agent->id)
                ->where('type', 'trigger')
                ->where('is_active', true)
                ->first();

            if (!$triggerEndpoint) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active trigger endpoint configured for this agent',
                ], 400);
            }

            // Create a new agent run
            $run = AgentRun::create([
                'agent_id' => $agent->id,
                'status' => 'pending',
                'input' => $validated['input'],
            ]);

            // Send request to agent trigger webhook
            try {
                $response = Http::timeout($agent->execution_timeout_ms / 1000)
                    ->withHeaders([
                        'X-Agent-Secret' => $triggerEndpoint->secret,
                        'X-Run-Id' => $run->id,
                    ])
                    ->post($triggerEndpoint->url, [
                        'run_id' => $run->id,
                        'input' => $validated['input'],
                    ]);

                // Check if request was accepted
                if ($response->successful()) {
                    // Update run status to running
                    $run->markAsRunning();

                    return response()->json([
                        'success' => true,
                        'message' => 'Agent execution initiated',
                        'data' => [
                            'run_id' => $run->id,
                            'status' => 'running',
                            'agent' => [
                                'id' => $agent->id,
                                'name' => $agent->name,
                                'runtime_type' => $agent->runtime_type,
                            ],
                        ],
                    ], 202); // 202 Accepted
                } else {
                    // Mark run as failed
                    $run->markAsFailed('Agent trigger endpoint returned error: ' . $response->status());

                    return response()->json([
                        'success' => false,
                        'message' => 'Agent failed to accept execution request',
                        'data' => [
                            'run_id' => $run->id,
                            'status' => 'failed',
                        ],
                    ], 500);
                }
            } catch (\Exception $e) {
                // Mark run as failed
                $run->markAsFailed('Failed to connect to agent trigger endpoint: ' . $e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to connect to agent',
                    'data' => [
                        'run_id' => $run->id,
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ],
                ], 500);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Agent execution error: ' . $e->getMessage(), [
                'agent_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get agent run status.
     *
     * GET /v1/agent-runs/{run_id}
     *
     * @param string $runId Run UUID
     * @return JsonResponse
     */
    public function getRunStatus(string $runId): JsonResponse
    {
        try {
            $run = AgentRun::with('agent:id,name,runtime_type')->findOrFail($runId);

            return response()->json([
                'success' => true,
                'data' => [
                    'run_id' => $run->id,
                    'status' => $run->status,
                    'input' => $run->input,
                    'output' => $run->output,
                    'error' => $run->error,
                    'created_at' => $run->created_at,
                    'updated_at' => $run->updated_at,
                    'agent' => [
                        'id' => $run->agent->id,
                        'name' => $run->agent->name,
                        'runtime_type' => $run->agent->runtime_type,
                    ],
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Agent run not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get run status error: ' . $e->getMessage(), [
                'run_id' => $runId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Webhook callback for agent execution results.
     *
     * POST /v1/webhooks/agents/callback
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function callback(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'run_id' => 'required|uuid',
                'status' => 'required|in:completed,failed',
                'output' => 'nullable|array',
                'error' => 'nullable|string',
                'secret' => 'required|string',
            ]);

            // Find the run
            $run = AgentRun::with('agent')->findOrFail($validated['run_id']);

            // Get the callback endpoint for this agent
            $callbackEndpoint = AgentEndpoint::where('agent_id', $run->agent_id)
                ->where('type', 'callback')
                ->where('is_active', true)
                ->first();

            if (!$callbackEndpoint) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active callback endpoint configured for this agent',
                ], 400);
            }

            // Validate secret
            if (!$callbackEndpoint->validateSecret($validated['secret'])) {
                Log::warning('Invalid callback secret', [
                    'run_id' => $validated['run_id'],
                    'agent_id' => $run->agent_id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid secret',
                ], 401);
            }

            // Update run based on status
            if ($validated['status'] === 'completed') {
                $run->markAsCompleted($validated['output'] ?? []);
            } else {
                $run->markAsFailed($validated['error'] ?? 'Unknown error');
            }

            return response()->json([
                'success' => true,
                'message' => 'Callback received and processed',
                'data' => [
                    'run_id' => $run->id,
                    'status' => $run->status,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Agent run not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Agent callback error: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
