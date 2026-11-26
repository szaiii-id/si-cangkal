import { render, screen, fireEvent, waitFor } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import NotificationPermissionBanner from '../NotificationPermissionBanner.svelte';

// ==========================================
// 1. MOCK DEPENDENCIES
// ==========================================
vi.mock('@/lib/firebase', () => ({
    requestFcmToken: vi.fn(),
}));

vi.mock('@/utils/toast.svelte', () => ({
    showToast: vi.fn(),
}));

vi.mock('@/api/notifications', () => ({
    updateFcmToken: vi.fn(),
}));

import { requestFcmToken } from '@/lib/firebase';
import { showToast } from '@/utils/toast.svelte';
import { updateFcmToken } from '@/api/notifications';

describe('NotificationPermissionBanner', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    // ==========================================
    // 1. RENDERING & A11Y
    // ==========================================
    describe('Rendering', () => {
        it('harus merender judul, deskripsi, dan tombol dengan benar', () => {
            render(NotificationPermissionBanner, {
                props: { onDismiss: vi.fn() },
            });

            expect(screen.getByRole('dialog')).toBeInTheDocument();

            // PERBAIKAN: Gunakan getByRole('heading') untuk judul (h2)
            expect(
                screen.getByRole('heading', { name: 'Aktifkan Notifikasi' }),
            ).toBeInTheDocument();

            // Teks deskripsi
            expect(
                screen.getByText(/Dapatkan pemberitahuan langsung/),
            ).toBeInTheDocument();

            // Tombol
            expect(
                screen.getByRole('button', { name: /Aktifkan Notifikasi/i }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: /Nanti Saja/i }),
            ).toBeInTheDocument();
        });

        it('harus memiliki atribut aksesibilitas (aria-modal, aria-labelledby)', () => {
            render(NotificationPermissionBanner, {
                props: { onDismiss: vi.fn() },
            });

            const dialog = screen.getByRole('dialog');
            expect(dialog).toHaveAttribute('aria-modal', 'true');
            expect(dialog).toHaveAttribute('aria-labelledby', 'notif-title');
            expect(dialog).toHaveAttribute('aria-describedby', 'notif-desc');
        });
    });

    // ==========================================
    // 2. INTERAKSI DISMISS
    // ==========================================
    describe('Dismiss Actions', () => {
        it('harus memanggil onDismiss saat tombol "Nanti Saja" diklik', async () => {
            const onDismiss = vi.fn();
            render(NotificationPermissionBanner, { props: { onDismiss } });

            await fireEvent.click(
                screen.getByRole('button', { name: /Nanti Saja/i }),
            );
            expect(onDismiss).toHaveBeenCalledTimes(1);
        });

        it('harus memanggil onDismiss saat backdrop diklik', async () => {
            const onDismiss = vi.fn();
            render(NotificationPermissionBanner, { props: { onDismiss } });

            const backdrop = document.querySelector(
                'button[aria-label="Tutup pengingat notifikasi"]',
            );
            await fireEvent.click(backdrop!);
            expect(onDismiss).toHaveBeenCalledTimes(1);
        });
    });

    // ==========================================
    // 3. HAPPY PATH (Allow Notification)
    // ==========================================
    describe('Allow Notification', () => {
        it('harus memanggil updateFcmToken dan onSuccess saat berhasil', async () => {
            (requestFcmToken as any).mockResolvedValue('fcm-token-123');

            (updateFcmToken as any).mockImplementation(
                (_data: any, options: any) => {
                    options.onSuccess();
                    options.onFinish();
                },
            );

            const onSuccess = vi.fn();
            render(NotificationPermissionBanner, {
                props: { onDismiss: vi.fn(), onSuccess },
            });

            await fireEvent.click(
                screen.getByRole('button', { name: /Aktifkan Notifikasi/i }),
            );

            await waitFor(() => {
                expect(updateFcmToken).toHaveBeenCalledWith(
                    { token: 'fcm-token-123' },
                    expect.any(Object),
                );
                expect(onSuccess).toHaveBeenCalled();
                expect(showToast).toHaveBeenCalledWith(
                    'Notifikasi berhasil diaktifkan!',
                    'success',
                );
            });
        });
    });

    // ==========================================
    // 4. NEGATIVE PATH (Token Null / Error)
    // ==========================================
    describe('Negative Path', () => {
        it('harus menampilkan toast warning jika token null (izin ditolak)', async () => {
            (requestFcmToken as any).mockResolvedValue(null);

            const onDismiss = vi.fn();
            render(NotificationPermissionBanner, { props: { onDismiss } });

            await fireEvent.click(
                screen.getByRole('button', { name: /Aktifkan Notifikasi/i }),
            );

            await waitFor(() => {
                expect(showToast).toHaveBeenCalledWith(
                    expect.stringContaining('Izin notifikasi tidak diberikan'),
                    'warning',
                );
                expect(onDismiss).toHaveBeenCalled();
            });
        });

        it('harus menampilkan toast error jika requestFcmToken melempar error', async () => {
            (requestFcmToken as any).mockRejectedValue(
                new Error('Network error'),
            );

            const onDismiss = vi.fn();
            render(NotificationPermissionBanner, { props: { onDismiss } });

            await fireEvent.click(
                screen.getByRole('button', { name: /Aktifkan Notifikasi/i }),
            );

            await waitFor(() => {
                expect(showToast).toHaveBeenCalledWith(
                    'Terjadi kesalahan saat mengaktifkan notifikasi.',
                    'error',
                );
                expect(onDismiss).toHaveBeenCalled();
            });
        });
    });

    // ==========================================
    // 5. LOADING STATE
    // ==========================================
    describe('Loading State', () => {
        it('harus menonaktifkan tombol dan menampilkan spinner saat isRequesting=true', async () => {
            (requestFcmToken as any).mockImplementation(
                () => new Promise(() => {}),
            );

            render(NotificationPermissionBanner, {
                props: { onDismiss: vi.fn() },
            });

            await fireEvent.click(
                screen.getByRole('button', { name: /Aktifkan Notifikasi/i }),
            );

            await waitFor(() => {
                expect(screen.getByText('Memproses…')).toBeInTheDocument();
                expect(
                    screen.getByRole('button', { name: /Nanti Saja/i }),
                ).toBeDisabled();
            });
        });
    });
});
