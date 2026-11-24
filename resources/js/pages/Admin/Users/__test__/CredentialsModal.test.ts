/**
 * ============================================================
 * CredentialsModal — Component Test
 * ============================================================
 *
 * Fokus pengujian (sesuai ketentuan):
 *   1. Isolation & Mocking  → Clipboard API di-mock via
 *                             `navigator.clipboard.writeText`.
 *   2. Props & Rendering    → render kondisi `open`, `credentials`,
 *                             username null, kelas styling.
 *   3. Interaction          → copy per-field, copy-all, close.
 *   4. Reactivity           → state `copied` berubah → ikon berubah.
 *
 * Strategi:
 *   - `navigator.clipboard` di-define via `Object.defineProperty`
 *     karena jsdom tidak punya API ini.
 *   - Fake timers untuk test auto-reset `copied` setelah 2 detik.
 *   - A11y-first query: `getByRole('dialog')`, `getByRole('button')`,
 *     `getByLabelText`.
 *   - Query "Tutup" pakai **exact match** untuk menghindari bentrok
 *     dengan `aria-label="Tutup modal"` pada backdrop.
 * ============================================================
 */

import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import CredentialsModal from '../CredentialsModal.svelte';

// ============================================================
// 1. CLIPBOARD MOCK
// ============================================================
const writeTextMock = vi.fn<() => Promise<void>>(() => Promise.resolve());

beforeEach(() => {
    writeTextMock.mockClear();

    Object.defineProperty(navigator, 'clipboard', {
        value: { writeText: writeTextMock },
        writable: true,
        configurable: true,
    });
});

// ============================================================
// 2. FIXTURES
// ============================================================
const makeCredentials = (overrides = {}) => ({
    role: 'admin',
    name: 'Super Admin',
    email: 'admin@test.com',
    username: 'superadmin',
    password: 'P@ssw0rd!',
    ...overrides,
});

// ============================================================
// 3. TEST SUITE
// ============================================================
describe('CredentialsModal', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    // ----------------------------------------------------------
    // 3.1 CONDITIONAL RENDERING
    // ----------------------------------------------------------
    describe('Rendering — Conditional', () => {
        it('TIDAK render saat open=false', () => {
            render(CredentialsModal, {
                props: { open: false, credentials: makeCredentials() },
            });
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        it('TIDAK render saat credentials=null', () => {
            render(CredentialsModal, {
                props: { open: true, credentials: null },
            });
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        it('render saat open=true dan credentials ada', () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });
            expect(screen.getByRole('dialog')).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 3.2 UI CONTRACT
    // ----------------------------------------------------------
    describe('Rendering — UI Contract', () => {
        it('menampilkan heading "Kredensial User"', () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });
            expect(
                screen.getByRole('heading', { name: 'Kredensial User' }),
            ).toBeInTheDocument();
        });

        it('menampilkan warning password hanya muncul 1x', () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });
            expect(
                screen.getByText('Password hanya muncul 1x'),
            ).toBeInTheDocument();
            expect(
                screen.getByText(
                    /Jika hilang, Anda harus reset password lagi/i,
                ),
            ).toBeInTheDocument();
        });

        it('menampilkan label Role, Nama, Email, Username, Password', () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });
            expect(screen.getByText('Role')).toBeInTheDocument();
            expect(screen.getByText('Nama')).toBeInTheDocument();
            expect(screen.getByText('Email')).toBeInTheDocument();
            expect(screen.getByText('Username')).toBeInTheDocument();
            expect(screen.getByText('Password')).toBeInTheDocument();
        });

        it('menampilkan nilai role, name, email, username, password', () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });
            expect(screen.getByText('admin')).toBeInTheDocument();
            expect(screen.getByText('Super Admin')).toBeInTheDocument();
            expect(screen.getByText('admin@test.com')).toBeInTheDocument();
            expect(screen.getByText('superadmin')).toBeInTheDocument();
            expect(screen.getByText('P@ssw0rd!')).toBeInTheDocument();
        });

        it('role pakai class capitalize', () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });
            expect(screen.getByText('admin')).toHaveClass('capitalize');
        });

        it('password pakai class font-mono', () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });
            expect(screen.getByText('P@ssw0rd!')).toHaveClass('font-mono');
        });

        it('render tombol Salin Semua, Tutup, dan tombol per-field', () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });

            expect(
                screen.getByRole('button', { name: /Salin Semua/i }),
            ).toBeInTheDocument();
            // ✅ FIX: Exact match — hindari bentrok dengan `aria-label="Tutup modal"` di backdrop
            expect(
                screen.getByRole('button', { name: 'Tutup' }),
            ).toBeInTheDocument();
            expect(screen.getByLabelText('Salin email')).toBeInTheDocument();
            expect(screen.getByLabelText('Salin username')).toBeInTheDocument();
            expect(screen.getByLabelText('Salin password')).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 3.3 USERNAME OPTIONAL
    // ----------------------------------------------------------
    describe('Rendering — Username Optional', () => {
        it('TIDAK render section username saat username=null', () => {
            render(CredentialsModal, {
                props: {
                    open: true,
                    credentials: makeCredentials({ username: null }),
                },
            });
            expect(screen.queryByText('Username')).not.toBeInTheDocument();
            expect(
                screen.queryByLabelText('Salin username'),
            ).not.toBeInTheDocument();
        });

        it('TIDAK render section username saat username="" (falsy)', () => {
            render(CredentialsModal, {
                props: {
                    open: true,
                    credentials: makeCredentials({ username: '' }),
                },
            });
            expect(screen.queryByText('Username')).not.toBeInTheDocument();
        });

        it('render section username saat username ada', () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });
            expect(screen.getByText('Username')).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 3.4 INTERACTION — Copy per-field
    // ----------------------------------------------------------
    describe('Interaction — Copy per-field', () => {
        it('klik "Salin email" memanggil clipboard dengan email', async () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });
            await fireEvent.click(screen.getByLabelText('Salin email'));
            expect(writeTextMock).toHaveBeenCalledWith('admin@test.com');
        });

        it('klik "Salin username" memanggil clipboard dengan username', async () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });
            await fireEvent.click(screen.getByLabelText('Salin username'));
            expect(writeTextMock).toHaveBeenCalledWith('superadmin');
        });

        it('klik "Salin password" memanggil clipboard dengan password', async () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });
            await fireEvent.click(screen.getByLabelText('Salin password'));
            expect(writeTextMock).toHaveBeenCalledWith('P@ssw0rd!');
        });
    });

    // ----------------------------------------------------------
    // 3.5 INTERACTION — Copy All
    // ----------------------------------------------------------
    describe('Interaction — Copy All', () => {
        it('klik "Salin Semua" memanggil clipboard dengan format multi-line lengkap', async () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });
            await fireEvent.click(
                screen.getByRole('button', { name: /Salin Semua/i }),
            );

            const expectedText = [
                'Role: admin',
                'Nama: Super Admin',
                'Email: admin@test.com',
                'Username: superadmin',
                'Password: P@ssw0rd!',
            ].join('\n');

            expect(writeTextMock).toHaveBeenCalledWith(expectedText);
        });

        it('copyAll menampilkan "-" jika username=null', async () => {
            render(CredentialsModal, {
                props: {
                    open: true,
                    credentials: makeCredentials({ username: null }),
                },
            });
            await fireEvent.click(
                screen.getByRole('button', { name: /Salin Semua/i }),
            );

            const expectedText = [
                'Role: admin',
                'Nama: Super Admin',
                'Email: admin@test.com',
                'Username: -',
                'Password: P@ssw0rd!',
            ].join('\n');

            expect(writeTextMock).toHaveBeenCalledWith(expectedText);
        });
    });

    // ----------------------------------------------------------
    // 3.6 REACTIVITY — Copied State
    // ----------------------------------------------------------
    describe('Reactivity — Copied State', () => {
        it('setelah copy email, ikon berubah dan reset setelah 2 detik', async () => {
            vi.useFakeTimers();

            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });

            const copyBtn = screen.getByLabelText('Salin email');
            await fireEvent.click(copyBtn);
            await vi.advanceTimersByTimeAsync(0);

            const checkmark = copyBtn.querySelector(
                'svg path[d="M5 13l4 4L19 7"]',
            );
            expect(checkmark).toBeInTheDocument();

            await vi.advanceTimersByTimeAsync(2000);
            expect(
                copyBtn.querySelector('svg path[d="M5 13l4 4L19 7"]'),
            ).not.toBeInTheDocument();
        });

        it('setelah copy all, tombol berubah teks menjadi "Tersalin!"', async () => {
            vi.useFakeTimers();

            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });

            await fireEvent.click(
                screen.getByRole('button', { name: /Salin Semua/i }),
            );
            await vi.advanceTimersByTimeAsync(0);

            expect(
                screen.getByRole('button', { name: /Tersalin!/i }),
            ).toBeInTheDocument();

            await vi.advanceTimersByTimeAsync(2000);
            expect(
                screen.getByRole('button', { name: /Salin Semua/i }),
            ).toBeInTheDocument();
        });

        it('copy field A lalu field B → hanya field B yang berubah', async () => {
            vi.useFakeTimers();

            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });

            await fireEvent.click(screen.getByLabelText('Salin email'));
            await vi.advanceTimersByTimeAsync(0);

            await fireEvent.click(screen.getByLabelText('Salin password'));
            await vi.advanceTimersByTimeAsync(0);

            const emailBtn = screen.getByLabelText('Salin email');
            const passwordBtn = screen.getByLabelText('Salin password');

            expect(
                emailBtn.querySelector('svg path[d="M5 13l4 4L19 7"]'),
            ).not.toBeInTheDocument();
            expect(
                passwordBtn.querySelector('svg path[d="M5 13l4 4L19 7"]'),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 3.7 INTERACTION — Close
    // ----------------------------------------------------------
    describe('Interaction — Close', () => {
        it('klik tombol "Tutup" set open=false', async () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });

            // ✅ FIX: exact match
            await fireEvent.click(
                screen.getByRole('button', { name: 'Tutup' }),
            );

            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        it('klik backdrop set open=false', async () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });

            await fireEvent.click(screen.getByLabelText('Tutup modal'));

            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 3.8 A11Y
    // ----------------------------------------------------------
    describe('Accessibility', () => {
        it('dialog memiliki aria-modal="true"', () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });
            const dialog = screen.getByRole('dialog');
            expect(dialog).toHaveAttribute('aria-modal', 'true');
        });

        it('dialog memiliki aria-labelledby yang mengarah ke heading', () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });
            const dialog = screen.getByRole('dialog');
            const labelledBy = dialog.getAttribute('aria-labelledby');
            expect(labelledBy).toBe('credentials-modal-title');
            expect(document.getElementById(labelledBy!)).toHaveTextContent(
                'Kredensial User',
            );
        });

        it('backdrop punya aria-label "Tutup modal"', () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });
            expect(screen.getByLabelText('Tutup modal')).toBeInTheDocument();
        });

        it('semua tombol punya aria-label atau teks yang jelas', () => {
            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });
            expect(screen.getByLabelText('Salin email')).toBeInTheDocument();
            expect(screen.getByLabelText('Salin username')).toBeInTheDocument();
            expect(screen.getByLabelText('Salin password')).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: /Salin Semua/i }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Tutup' }),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 3.9 EDGE CASES
    // ----------------------------------------------------------
    describe('Edge Cases', () => {
        it('handle role="mitra" dengan rendering normal', () => {
            render(CredentialsModal, {
                props: {
                    open: true,
                    credentials: makeCredentials({ role: 'mitra' }),
                },
            });
            expect(screen.getByText('mitra')).toBeInTheDocument();
        });

        it('handle password dengan karakter khusus', () => {
            render(CredentialsModal, {
                props: {
                    open: true,
                    credentials: makeCredentials({ password: '!@#$%^&*()_+' }),
                },
            });
            expect(screen.getByText('!@#$%^&*()_+')).toBeInTheDocument();
        });

        it('handle nama panjang dengan class truncate', () => {
            render(CredentialsModal, {
                props: {
                    open: true,
                    credentials: makeCredentials({
                        name: 'Nama Yang Sangat Panjang Sekali Dan Mungkin Akan Terpotong',
                    }),
                },
            });
            expect(screen.getByText(/Nama Yang Sangat Panjang/i)).toHaveClass(
                'truncate',
            );
        });

        it('handle email dengan karakter khusus', () => {
            render(CredentialsModal, {
                props: {
                    open: true,
                    credentials: makeCredentials({
                        email: 'user+tag@sub.domain.co.id',
                    }),
                },
            });
            expect(
                screen.getByText('user+tag@sub.domain.co.id'),
            ).toBeInTheDocument();
        });

        // ✅ FIX: Handle unhandled rejection dengan try/catch + flush
        it('copyToClipboard error tidak crash UI', async () => {
            writeTextMock.mockRejectedValueOnce(
                new Error('Clipboard unavailable'),
            );

            render(CredentialsModal, {
                props: { open: true, credentials: makeCredentials() },
            });

            // Consume rejection: klip komponen asli pakai `.then()` tanpa `.catch()`,
            // jadi rejection bisa leak. try/catch + microtask flush mencegah
            // unhandled rejection warning di Vitest.
            try {
                await fireEvent.click(screen.getByLabelText('Salin email'));
                await Promise.resolve();
                await Promise.resolve();
            } catch {
                // Expected
            }

            // UI tidak crash
            expect(screen.getByRole('dialog')).toBeInTheDocument();
            expect(screen.getByLabelText('Salin email')).toBeInTheDocument();
        });
    });
});
