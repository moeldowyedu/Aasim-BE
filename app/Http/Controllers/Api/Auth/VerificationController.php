<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class VerificationController extends Controller
{
    /**
     * Verify email via signed URL
     */
    public function verify(Request $request, $id, $hash)
    {
        try {
            Log::info('Email verification attempt', [
                'user_id' => $id,
                'hash' => $hash,
                'url' => $request->fullUrl()
            ]);

            // Find user
            $user = User::findOrFail($id);

            // Check if hash matches
            if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
                Log::warning('Hash mismatch', ['user_id' => $id]);
                
                return response()->json([
                    'message' => 'Invalid verification link.',
                    'error' => 'hash_mismatch'
                ], 403);
            }

            // Check if already verified
            if ($user->hasVerifiedEmail()) {
                Log::info('Already verified', ['user_id' => $id]);
                
                return response()->json([
                    'message' => 'Email already verified.',
                    'already_verified' => true
                ], 200);
            }

            // Check expiration
            $expires = $request->get('expires');
            if ($expires && now()->timestamp > $expires) {
                return response()->json([
                    'message' => 'Verification link has expired.',
                    'error' => 'link_expired'
                ], 403);
            }

            // Custom signature validation (handles domain variations)
            $signature = $request->get('signature');
            if (!$signature || !$this->isValidSignature($request, $user)) {
                Log::warning('Invalid signature', ['user_id' => $id]);
                
                return response()->json([
                    'message' => 'Invalid verification link.',
                    'error' => 'invalid_signature'
                ], 403);
            }

            // Mark as verified
            $user->markEmailAsVerified();
            event(new Verified($user));

            Log::info('Email verified successfully', ['user_id' => $id]);

            return response()->json([
                'message' => 'Email verified successfully!',
                'verified' => true,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Verification error', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Verification failed.',
                'error' => 'verification_error'
            ], 500);
        }
    }

    /**
     * Validate signature with domain flexibility
     */
    protected function isValidSignature(Request $request, User $user)
    {
        $signature = $request->get('signature');
        
        // Build URLs to check (both domains)
        $urls = [
            $this->buildUrl($request, 'https://obsolio.com'),
            $this->buildUrl($request, 'https://api.obsolio.com'),
        ];

        // Check if signature matches either URL
        foreach ($urls as $url) {
            $expected = hash_hmac('sha256', $url, config('app.key'));
            
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build URL for signature checking
     */
    protected function buildUrl(Request $request, $baseUrl)
    {
        $path = $request->path();
        $query = $request->query();
        unset($query['signature']);
        
        $url = $baseUrl . '/' . $path;
        
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        
        return $url;
    }

    /**
     * Resend verification email
     */
    public function resend(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.'
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        Log::info('Verification email resent', ['user_id' => $user->id]);

        return response()->json([
            'message' => 'Verification email sent!'
        ], 200);
    }
}
