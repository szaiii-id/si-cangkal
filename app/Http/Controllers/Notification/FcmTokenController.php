<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Controller untuk menerima FCM Token dari browser user.
 *
 * Endpoint: POST /notifications/fcm
 * 
 * Bertanggung jawab menyimpan token perangkat user ke database
 * agar backend bisa mengirim push notification ke HP mereka.
 */
final class FcmTokenController extends Controller
{
    /**
     * Simpan / perbarui FCM Token milik user yang sedang login.
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'token' => ['required', 'string', 'max:255'],
            ]);

            $user = $request->user();

            if ($user === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            // Hanya update jika token berubah (mencegah query UPDATE yang tidak perlu)
            if ($user->fcm_token !== $validated['token']) {
                $user->update(['fcm_token' => $validated['token']]);

                Log::info('FCM token diperbarui', [
                    'user_id' => $user->id,
                    'token_prefix' => substr($validated['token'], 0, 10) . '...',
                ]);
            }

            return response()->json(['success' => true]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan FCM token', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan token.',
            ], 500);
        }
    }
}