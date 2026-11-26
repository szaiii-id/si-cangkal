<script lang="ts">
    import { tripStatusBadge, tripStatusLabel } from '@/utils/enums';
    import { formatDate } from '@/utils/formatDate';
    import { timezoneLabelLong } from '@/utils/timezone';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        kode,
        status,
        namaPaket,
        tanggalMulai,
        tanggalSelesai,
        jumlahPeserta,
        timezone,
        manifestUrl,
        client,
    }: {
        kode: string;
        status: string;
        namaPaket: string;
        tanggalMulai: string;
        tanggalSelesai: string;
        jumlahPeserta: number;
        timezone: string;
        manifestUrl: string;
        client: { name: string; email: string } | null;
    } = $props();

    // ==========================================
    // STYLING (DRY)
    // ==========================================
    const labelClass = "text-[10px] font-extrabold text-gray-500 uppercase tracking-widest";
    const valueClass = "text-sm font-bold text-dark";
</script>

<div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5 sm:p-6 mb-6">
    <div class="flex flex-col gap-4">
        <!-- Status & Kode -->
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-extrabold {tripStatusBadge(status)}">
                {tripStatusLabel(status)}
            </span>
            <span class="text-xs font-mono text-gray-500">{kode}</span>
        </div>

        <!-- Nama Paket -->
        <h1 class="text-2xl font-extrabold text-dark">{namaPaket}</h1>

        <!-- Tombol Manifest -->
        <div>
            <a
                href={manifestUrl}
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 px-4 py-2 bg-pink-dark text-white font-bold text-sm rounded-lg hover:bg-sun-dark transition-[transform,box-shadow,background-color,border-color,color,opacity] shadow-md mt-2"
                aria-label="Cetak daftar peserta (buka di tab baru)"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Daftar Peserta
            </a>
        </div>

        <!-- Grid Info -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <p class="{labelClass}">Mulai</p>
                <p class="{valueClass}">{formatDate(tanggalMulai)}</p>
            </div>
            <div>
                <p class="{labelClass}">Selesai</p>
                <p class="{valueClass}">{formatDate(tanggalSelesai)}</p>
            </div>
            <div>
                <p class="{labelClass}">Peserta</p>
                <p class="{valueClass}">{jumlahPeserta} orang</p>
            </div>
            <div>
                <p class="{labelClass}">Timezone</p>
                <p class="{valueClass}">{timezoneLabelLong(timezone)}</p>
            </div>
        </div>

        <!-- Info Client -->
        {#if client}
            <div class="pt-4 border-t border-gray-100">
                <p class="{labelClass} mb-2">Pelanggan</p>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-pink-dark to-purple text-white flex items-center justify-center font-bold text-sm" aria-hidden="true">
                        {client.name.charAt(0).toUpperCase()}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-dark truncate">{client.name}</p>
                        <p class="text-xs text-gray-500 truncate">{client.email}</p>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</div>