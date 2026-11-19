import { render, screen, fireEvent } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import StatusTabs from '../StatusTabs.svelte';

describe('StatusTabs Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==========================================
    // 1. RENDERING DASAR
    // ==========================================
    describe('Rendering', () => {
        it('TIDAK boleh merender apa pun jika tabs kosong', () => {
            const { container } = render(StatusTabs, { props: { tabs: [] } });
            expect(container.querySelector('[role="group"]')).not.toBeInTheDocument();
        });

        it('harus merender label dan count dari tab', () => {
            render(StatusTabs, {
                props: {
                    tabs: [
                        { value: 'pending', label: 'Pending', count: 5 },
                        { value: 'success', label: 'Sukses' },
                    ],
                },
            });

            // Karena ada 2 tampilan (mobile & desktop), kita gunakan getAllByText
            expect(screen.getAllByText('Pending').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Sukses').length).toBeGreaterThan(0);
            expect(screen.getAllByText('5').length).toBeGreaterThan(0);
        });

        it('harus menandai tab aktif dengan aria-pressed="true"', () => {
            render(StatusTabs, {
                props: {
                    tabs: [
                        { value: 'pending', label: 'Pending' },
                        { value: 'success', label: 'Sukses' },
                    ],
                    active: 'pending',
                },
            });

            const activeButtons = screen.getAllByRole('button', { name: /Pending/i });
            activeButtons.forEach((btn) => {
                expect(btn).toHaveAttribute('aria-pressed', 'true');
            });
        });
    });

    // ==========================================
    // 2. USER INTERACTION
    // ==========================================
    describe('User Interaction', () => {
        it('harus memanggil onchange saat tab diklik', async () => {
            const onchange = vi.fn();
            render(StatusTabs, {
                props: {
                    tabs: [
                        { value: 'pending', label: 'Pending' },
                        { value: 'success', label: 'Sukses' },
                    ],
                    active: 'pending',
                    onchange,
                },
            });

            // Ambil tombol "Sukses" dari tampilan desktop
            const buttons = screen.getAllByRole('button', { name: /Sukses/i });
            await fireEvent.click(buttons[0]);

            expect(onchange).toHaveBeenCalledWith('success');
        });
    });

    // ==========================================
    // 3. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('harus memiliki aria-label yang menyertakan jumlah item', () => {
            render(StatusTabs, {
                props: {
                    tabs: [{ value: 'pending', label: 'Pending', count: 5 }],
                },
            });

            const buttons = screen.getAllByRole('button');
            buttons.forEach((btn) => {
                expect(btn).toHaveAttribute('aria-label', 'Pending, 5 item');
            });
        });

        it('harus menyembunyikan badge count dari screen reader', () => {
            render(StatusTabs, {
                props: {
                    tabs: [{ value: 'pending', label: 'Pending', count: 5 }],
                },
            });

            // Badge angka tidak boleh dibaca terpisah (karena sudah ada di aria-label)
            const countBadges = screen.getAllByText('5');
            countBadges.forEach((badge) => {
                expect(badge).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});