import { render, screen } from '@testing-library/svelte';
import { describe, it, expect } from 'vitest';
import PasswordChecklist from '../PasswordChecklist.svelte';

describe('PasswordChecklist Component', () => {
    // ==========================================
    // 1. RENDERING AWAL (Password Kosong)
    // ==========================================
    describe('Empty Password', () => {
        it('TIDAK boleh menampilkan label jika password kosong', () => {
            render(PasswordChecklist, { props: { password: '' } });
            
            expect(screen.queryByText(/Lemah/i)).not.toBeInTheDocument();
            expect(screen.queryByText(/Kuat/i)).not.toBeInTheDocument();
        });

        it('semua kriteria harus "gagal" (ikon silang, warna abu-abu)', () => {
            const { container } = render(PasswordChecklist, { props: { password: '' } });
            
            const listItems = container.querySelectorAll('li');
            listItems.forEach((li) => {
                expect(li).toHaveClass('text-gray-400');
                const path = li.querySelector('path');
                expect(path).toHaveAttribute('d', 'M6 18L18 6M6 6l12 12');
            });
        });
    });

    // ==========================================
    // 2. PASSWORD LEMAH
    // ==========================================
    describe('Weak Password', () => {
        it('harus menampilkan label "Lemah" dan warna merah untuk password pendek', () => {
            render(PasswordChecklist, { props: { password: 'abc' } });
            
            expect(screen.getByText('Lemah')).toBeInTheDocument();
        });

        it('kriteria "Minimal 8 karakter" harus gagal', () => {
            render(PasswordChecklist, { props: { password: 'abc' } });
            
            const criteriaItem = screen.getByText(/Minimal 8 karakter/i);
            expect(criteriaItem.closest('li')).toHaveClass('text-gray-400');
        });
    });

    // ==========================================
    // 3. PASSWORD KUAT
    // ==========================================
    describe('Strong Password', () => {
        it('harus menampilkan label "Kuat" atau "Sangat Kuat" untuk password kompleks', () => {
            render(PasswordChecklist, { props: { password: 'Abcdefgh123!' } });
            
            const label = screen.getByText(/Kuat/i);
            expect(label).toBeInTheDocument();
        });

        it('semua kriteria harus lulus (ikon centang, warna hijau)', () => {
            render(PasswordChecklist, { props: { password: 'Abcdefgh123!' } });
            
            const listItems = screen.getAllByRole('listitem');
            listItems.forEach((li) => {
                expect(li).toHaveClass('text-green-text');
                const path = li.querySelector('path');
                expect(path).toHaveAttribute('d', 'M5 13l4 4L19 7');
            });
        });
    });

    // ==========================================
    // 4. AKSESIBILITAS (A11Y)
    // ==========================================
    describe('Accessibility', () => {
        it('harus memiliki role="progressbar" pada bar', () => {
            const { container } = render(PasswordChecklist, { props: { password: 'abc' } });
            
            const progressBars = container.querySelectorAll('[role="progressbar"]');
            expect(progressBars.length).toBe(4);
            expect(progressBars[0]).toHaveAttribute('aria-valuenow', '1'); // Untuk "abc"
        });

        it('list harus memiliki aria-label "Syarat password"', () => {
            render(PasswordChecklist, { props: { password: 'abc' } });
            
            const list = screen.getByRole('list', { name: /Syarat password/i });
            expect(list).toBeInTheDocument();
        });

        it('ikon SVG harus disembunyikan dari screen reader', () => {
            const { container } = render(PasswordChecklist, { props: { password: 'abc' } });
            
            const svgs = container.querySelectorAll('svg');
            svgs.forEach((svg) => {
                expect(svg).toHaveAttribute('aria-hidden', 'true');
            });
        });
    });
});