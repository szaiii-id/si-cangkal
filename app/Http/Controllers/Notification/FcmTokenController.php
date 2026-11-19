<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class FcmTokenController extends Controller
{
    /**
     * Simpan / perbarui FCM Token milik user yang sedang login.
     */
    public function update(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'token' => ['required', 'string', 'max:255'],
            ]);

            $user = $request->user();

            if ($user === null) {
                return back()->withErrors(['error' => 'Sesi Anda telah berakhir. Silakan login ulang.']);
            }

            // Hanya update jika token berubah (mencegah query UPDATE yang tidak perlu)
            if ($user->fcm_token !== $validated['token']) {
                $user->update(['fcm_token' => $validated['token']]);

                Log::info('FCM token diperbarui', [
                    'user_id' => $user->id,
                    'token_prefix' => substr($validated['token'], 0, 10).'...',
                ]);
            }

            return back()->with('status', 'Notifikasi berhasil diaktifkan!');

        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan FCM token', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Gagal menyimpan token notifikasi.']);
        }
    }
}
