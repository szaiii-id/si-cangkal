<script lang="ts">
    import { inertia } from '@inertiajs/svelte';

    // ==========================================
    // DATA MENU (DRY)
    // ==========================================
    const navLinks = [
        { label: 'Beranda', href: '#beranda' },
        { label: 'Paket Wisata', href: '#trip' },
        { label: 'Visi & Misi', href: '#visimisi' },
        { label: 'Kontak', href: '#contact' },
    ];

    const legalLinks = [
        { label: 'Kebijakan Privasi', href: '/legal/privacy' },
        { label: 'Syarat & Ketentuan', href: '/legal/terms' },
        { label: 'Kebijakan Refund', href: '/legal/refund-policy' },
        { label: 'Kebijakan Cookie', href: '/legal/cookie-policy' },
        { label: 'Aturan Mitra', href: '/legal/mitra-rules' },
        { label: 'Penggunaan Wajar', href: '/legal/acceptable-use' },
    ];
</script>

<footer class="bg-dark text-white mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            
            <!-- Brand & Deskripsi -->
            <div class="md:col-span-2">
                <a href="/" use:inertia class="text-3xl font-extrabold tracking-tight inline-block" aria-label="SI-CANGKAL - Beranda">
                    SI-CANGKAL<span class="text-pink">.</span>
                </a>
                <p class="mt-4 text-sm text-gray-400 leading-relaxed max-w-md">
                    Sistem Implementasi Capaian Akselerasi Networking Global Kerjasama dan Lokal Kemitraan Pariwisata. Wadah digital resmi bagi pengelola pariwisata Banua.
                </p>
            </div>

            <!-- Navigasi Cepat -->
            <nav aria-labelledby="footer-nav-heading">
                <h3 id="footer-nav-heading" class="text-sm font-bold uppercase tracking-wider text-gray-300 mb-4">
                    Navigasi
                </h3>
                <ul class="space-y-2 text-sm">
                    {#each navLinks as link}
                        <li>
                            <a href={link.href} class="text-gray-400 hover:text-white transition-colors">
                                {link.label}
                            </a>
                        </li>
                    {/each}
                </ul>
            </nav>

            <!-- Legal & Kebijakan -->
            <nav aria-labelledby="footer-legal-heading">
                <h3 id="footer-legal-heading" class="text-sm font-bold uppercase tracking-wider text-gray-300 mb-4">
                    Legal
                </h3>
                <ul class="space-y-2 text-sm">
                    {#each legalLinks as link}
                        <li>
                            <a href={link.href} use:inertia class="text-gray-400 hover:text-white transition-colors">
                                {link.label}
                            </a>
                        </li>
                    {/each}
                </ul>
            </nav>
        </div>

        <!-- Divider -->
        <div class="border-t border-gray-800 mt-10 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-xs text-gray-400">
                &copy; 2026 SI-CANGKAL. Hak Cipta Dilindungi Undang-Undang.
            </p>
            <div class="flex gap-4 text-xs text-gray-400">
                <span>Dinas Pariwisata Provinsi Kalimantan Selatan</span>
            </div>
        </div>
    </div>
</footer>