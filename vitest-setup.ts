import '@testing-library/jest-dom/vitest';
import { vi } from 'vitest';

// ==========================================
// MOCK window.matchMedia
// ==========================================
// Mock ini harus ada di setup file agar dijalankan SEBELUM
// modul @faintshadow/flarecharts di-import oleh komponen.
Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation((query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
    })),
});