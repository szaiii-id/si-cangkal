/**
 * ============================================================
 * Client Catalog — Show Page (Detail Paket) — Component Test
 * ============================================================
 *
 * Fokus pengujian:
 *   1. Isolation & Mocking  → Inertia, enums, format helpers, timezone,
 *                             & child components (ClientLayout) di-mock.
 *   2. Props & Rendering    → hero, info, itinerary, fasilitas,
 *                             mitra, harga (conditional), booking link.
 *   3. Interaction          → WhatsApp link, booking link.
 *   4. Helper logic         → formatTime, toWhatsAppNumber (conversion).
 * ============================================================
 */

import { render, screen, within } from '@testing-library/svelte';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import Show from '../Show.svelte';

// ============================================================
// 1. MOCK — INERTIA
// ============================================================
vi.mock('@inertiajs/svelte', () => ({
    inertia: vi.fn(),
}));

// ============================================================
// 2. MOCK — ENUMS
// ============================================================
vi.mock('@/utils/enums', () => ({
    packageKategoriLabel: vi.fn((k: string) => {
        const map: Record<string, string> = {
            wisata_alam: 'Alam',
            budaya: 'Budaya',
            kuliner: 'Kuliner',
            petualangan: 'Petualangan',
            lainnya: 'Lainnya',
        };
        return map[k] ?? k;
    }),
}));

// ============================================================
// 3. MOCK — FORMAT HELPERS
// ============================================================
vi.mock('@/utils/formatCurrency', () => ({
    formatCurrency: vi.fn(
        (v: string | number) => `Rp ${Number(v).toLocaleString('id-ID')}`,
    ),
}));

vi.mock('@/utils/duration', () => ({
    formatDurasiLong: vi.fn(
        (hari: number, malam: number) => `${hari} Hari ${malam} Malam`,
    ),
}));

vi.mock('@/utils/timezone', () => ({
    timezoneLabelLong: vi.fn((tz: string) => `Waktu ${tz}`),
}));

// ============================================================
// 4. MOCK — CLIENT LAYOUT
// ============================================================
vi.mock('@/components/layout/ClientLayout.svelte', () => ({
    default: (anchor: Node, props: { children?: (anchor: Node) => void }) => {
        props.children?.(anchor);
        return {};
    },
}));

// ============================================================
// 5. STATIC IMPORT
// ============================================================
import { formatCurrency } from '@/utils/formatCurrency';
import { formatDurasiLong } from '@/utils/duration';
import { timezoneLabelLong } from '@/utils/timezone';

// ============================================================
// 6. FIXTURES
// ============================================================
const makePackage = (overrides: Record<string, unknown> = {}) =>
    ({
        id: 1,
        mitra_id: '1',
        kode: 'PKT-001',
        nama: 'Paket Bali',
        slug: 'paket-bali',
        kategori: 'wisata_alam',
        status: 'aktif',
        cover_image: 'packages/bali.jpg',
        deskripsi: 'Liburan seru di Bali',
        durasi_hari: 3,
        durasi_malam: 2,
        harga_per_orang: '750000',
        harga_per_rombongan: null as string | null,
        max_peserta: 20,
        timezone: 'Asia/Jakarta',
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
        mitra: {
            id: 1,
            name: 'PT Mitra Jaya',
            profile: {
                nama_penyedia_usaha: 'Mitra Jaya Travel',
                telepon: '081234567890',
                kota: 'Denpasar',
                provinsi: 'Bali',
            },
        },
        days: [
            {
                id: 1,
                hari_ke: 1,
                judul: 'Kedatangan',
                deskripsi: 'Arrive',
                jam_mulai: '08:00:00',
                jam_selesai: '12:00:00',
                lokasi: 'Bandara',
            },
            {
                id: 2,
                hari_ke: 2,
                judul: 'Explore',
                deskripsi: 'Tour',
                jam_mulai: null,
                jam_selesai: null,
                lokasi: null,
            },
        ],
        facilities: [
            { id: 1, tipe: 'include', nama: 'Hotel' },
            { id: 2, tipe: 'include', nama: 'Makan' },
            { id: 3, tipe: 'exclude', nama: 'Tiket Pesawat' },
        ],
        ...overrides,
    }) as any;

const renderPage = (pkg = makePackage()) =>
    render(Show, { props: { package: pkg } });

// ============================================================
// 7. TEST SUITE
// ============================================================
describe('Client Catalog — Show Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ----------------------------------------------------------
    // 7.1 RENDERING — Hero & Header
    // ----------------------------------------------------------
    describe('Rendering — Hero', () => {
        it('menampilkan cover image dengan alt=nama', () => {
            renderPage();

            const img = screen.getByAltText('Paket Bali') as HTMLImageElement;
            expect(img).toHaveAttribute('src', '/storage/packages/bali.jpg');
        });

        it('menampilkan fallback image saat cover_image null', () => {
            renderPage(makePackage({ cover_image: null }));

            const img = screen.getByAltText('Fallback');
            expect(img.getAttribute('src')).toContain('unsplash.com');
        });

        it('menampilkan kategori badge', () => {
            renderPage();

            // Scope ke header info, bukan card lain
            const header = screen
                .getByRole('heading', { level: 1, name: 'Paket Bali' })
                .closest('div');
            expect(within(header!).getByText('Alam')).toBeInTheDocument();
        });

        it('menampilkan judul paket sebagai h1', () => {
            renderPage();
            expect(
                screen.getByRole('heading', { level: 1, name: 'Paket Bali' }),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 7.2 RENDERING — Info Singkat
    // ----------------------------------------------------------
    describe('Rendering — Info Singkat', () => {
        it('memanggil formatDurasiLong dengan durasi_hari & durasi_malam', () => {
            renderPage();
            expect(formatDurasiLong).toHaveBeenCalledWith(3, 2);
        });

        it('menampilkan max peserta', () => {
            renderPage();
            expect(screen.getByText(/Maks. 20 orang/)).toBeInTheDocument();
        });

        it('TIDAK menampilkan max peserta saat null', () => {
            renderPage(makePackage({ max_peserta: null }));
            expect(screen.queryByText(/Maks\./)).not.toBeInTheDocument();
        });

        it('memanggil timezoneLabelLong', () => {
            renderPage();
            expect(timezoneLabelLong).toHaveBeenCalledWith('Asia/Jakarta');
            expect(screen.getByText('Waktu Asia/Jakarta')).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 7.3 RENDERING — Deskripsi
    // ----------------------------------------------------------
    describe('Rendering — Deskripsi', () => {
        it('menampilkan deskripsi saat ada', () => {
            renderPage();
            expect(
                screen.getByText('Liburan seru di Bali'),
            ).toBeInTheDocument();
        });

        it('TIDAK merender section deskripsi saat null', () => {
            renderPage(makePackage({ deskripsi: null }));
            expect(screen.queryByText('Tentang Paket')).not.toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 7.4 RENDERING — Itinerary
    // ----------------------------------------------------------
    describe('Rendering — Itinerary', () => {
        it('merender 2 hari itinerary', () => {
            renderPage();
            expect(screen.getByText('Kedatangan')).toBeInTheDocument();
            expect(screen.getByText('Explore')).toBeInTheDocument();
        });

        it('menampilkan jam mulai & selesai (format HH:MM)', () => {
            renderPage();
            // 08:00:00 → "08:00", 12:00:00 → "12:00"
            expect(screen.getByText(/08:00/)).toBeInTheDocument();
            expect(screen.getByText(/12:00/)).toBeInTheDocument();
        });

        it('menampilkan lokasi day', () => {
            renderPage();
            expect(screen.getByText('Bandara')).toBeInTheDocument();
        });

        it('menampilkan placeholder saat itinerary kosong', () => {
            renderPage(makePackage({ days: [] }));
            expect(
                screen.getByText('Rencana perjalanan belum tersedia.'),
            ).toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 7.5 RENDERING — Facilities
    // ----------------------------------------------------------
    describe('Rendering — Facilities', () => {
        it('merender include facilities', () => {
            renderPage();
            expect(screen.getByText('Hotel')).toBeInTheDocument();
            expect(screen.getByText('Makan')).toBeInTheDocument();
        });

        it('merender exclude facilities', () => {
            renderPage();
            expect(screen.getByText('Tiket Pesawat')).toBeInTheDocument();
        });

        it('menampilkan placeholder jika include kosong', () => {
            renderPage(
                makePackage({
                    facilities: [{ id: 1, tipe: 'exclude', nama: 'Tiket' }],
                }),
            );
            // "Belum ada info" muncul di include
            expect(
                screen.getAllByText('Belum ada info.').length,
            ).toBeGreaterThanOrEqual(1);
        });
    });

    // ----------------------------------------------------------
    // 7.6 RENDERING — Mitra Card
    // ----------------------------------------------------------
    describe('Rendering — Mitra Card', () => {
        it('menampilkan nama penyedia usaha', () => {
            renderPage();
            expect(screen.getByText('Mitra Jaya Travel')).toBeInTheDocument();
        });

        it('menampilkan lokasi mitra', () => {
            renderPage();
            expect(screen.getByText('Denpasar, Bali')).toBeInTheDocument();
        });

        it('fallback ke nama mitra saat profile null', () => {
            renderPage(
                makePackage({
                    mitra: { id: '1', name: 'Mitra Name Only', profile: null },
                }),
            );
            expect(screen.getByText('Mitra Name Only')).toBeInTheDocument();
        });

        it('TIDAK render card mitra saat mitra null', () => {
            renderPage(makePackage({ mitra: null }));
            expect(
                screen.queryByText('Diselenggarakan oleh'),
            ).not.toBeInTheDocument();
        });
    });

    // ----------------------------------------------------------
    // 7.7 RENDERING — WhatsApp Link
    // ----------------------------------------------------------
    describe('Rendering — WhatsApp', () => {
        it('merender WA link saat telepon ada', () => {
            renderPage();

            const waLink = screen.getByRole('link', {
                name: /Hubungi via WA/i,
            });
            expect(waLink).toHaveAttribute(
                'href',
                expect.stringContaining('https://wa.me/6281234567890'),
            );
        });

        it('WhatsApp URL mengandung text prefilled', () => {
            renderPage();

            const waLink = screen.getByRole('link', {
                name: /Hubungi via WA/i,
            });
            expect(waLink.getAttribute('href')).toContain('text=');
            expect(decodeURIComponent(waLink.getAttribute('href')!)).toContain(
                'Paket Bali',
            );
        });

        it('TIDAK render WA link saat telepon null', () => {
            renderPage(
                makePackage({
                    mitra: {
                        id: 1,
                        name: 'X',
                        profile: {
                            nama_penyedia_usaha: 'Y',
                            telepon: null,
                            kota: 'Z',
                            provinsi: 'W',
                        },
                    },
                }),
            );
            expect(
                screen.queryByRole('link', { name: /Hubungi via WA/i }),
            ).not.toBeInTheDocument();
        });

        it('konversi 08xxx → 628xxx', () => {
            renderPage();
            const waLink = screen.getByRole('link', {
                name: /Hubungi via WA/i,
            });
            expect(waLink.getAttribute('href')).toContain(
                'wa.me/6281234567890',
            );
        });
    });

    // ----------------------------------------------------------
    // 7.8 RENDERING — Harga (conditional)
    // ----------------------------------------------------------
    describe('Rendering — Harga', () => {
        it('menampilkan harga per orang saat rombongan null', () => {
            renderPage();
            expect(screen.getAllByText('/ orang').length).toBeGreaterThan(0);
            expect(formatCurrency).toHaveBeenCalledWith('750000');
        });

        it('menampilkan harga rombongan saat ada', () => {
            renderPage(makePackage({ harga_per_rombongan: '5000000' }));
            expect(screen.getAllByText('/ rombongan').length).toBeGreaterThan(
                0,
            );
            expect(formatCurrency).toHaveBeenCalledWith('5000000');
        });
    });

    // ----------------------------------------------------------
    // 7.9 INTERACTION — Links
    // ----------------------------------------------------------
    describe('Interaction — Links', () => {
        it('link "Kembali ke Katalog" ke /client/catalog', () => {
            renderPage();
            const backLink = screen.getByRole('link', {
                name: /Kembali ke Katalog/i,
            });
            expect(backLink).toHaveAttribute('href', '/client/catalog');
        });

        it('link "Booking Sekarang" ke /client/bookings/create/:id', () => {
            renderPage();
            const bookingLinks = screen.getAllByRole('link', {
                name: /Booking Sekarang/i,
            });
            expect(bookingLinks.length).toBeGreaterThan(0);
            for (const link of bookingLinks) {
                expect(link).toHaveAttribute(
                    'href',
                    '/client/bookings/create/1',
                );
            }
        });
    });
});

describe('logo penyelenggara', () => {
    it('menampilkan logo mitra bila tersedia', () => {
        const pkg = makePackage({
            mitra: {
                id: 1,
                name: 'PT Mitra Jaya',
                profile: {
                    nama_penyedia_usaha: 'Mitra Jaya Travel',
                    telepon: '081234567890',
                    kota: 'Denpasar',
                    provinsi: 'Bali',
                    logo_url: 'https://cdn.test/logo.png',
                },
            },
        });

        renderPage(pkg);

        expect(
            screen.getByRole('img', { name: /logo mitra/i }),
        ).toHaveAttribute('src', 'https://cdn.test/logo.png');
    });

    it('memakai inisial bila logo tidak tersedia', () => {
        renderPage();

        expect(
            screen.queryByRole('img', { name: /logo mitra/i }),
        ).not.toBeInTheDocument();
        expect(screen.getByText('M')).toBeInTheDocument();
    });
});
