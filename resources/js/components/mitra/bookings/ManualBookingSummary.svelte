<script lang="ts">
    import { formatCurrency } from '@/utils/formatCurrency';

    // ==========================================
    // PROPS
    // ==========================================
    let {
        hargaPerOrang,
        hargaPerRombongan,
        jumlahPeserta,
        totalHarga,
    }: {
        hargaPerOrang: string;
        hargaPerRombongan: string | null;
        jumlahPeserta: number;
        totalHarga: number;
    } = $props();

    // ==========================================
    // DERIVED
    // ==========================================
    let hargaLabel = $derived(
        hargaPerRombongan
            ? `${formatCurrency(hargaPerRombongan)} / rombongan`
            : `${formatCurrency(hargaPerOrang)} / orang`
    );
</script>

<div class="p-5 sm:p-6 space-y-3 text-sm">
    <!-- Rincian Jumlah Peserta -->
    <div class="flex justify-between items-center">
        <span class="text-gray-500">Jumlah Peserta</span>
        <span class="font-bold text-dark">{jumlahPeserta} orang</span>
    </div>

    <!-- Rincian Harga Satuan -->
    <div class="flex justify-between items-start sm:items-center gap-2">
        <span class="text-gray-500 shrink-0">Harga Paket</span>
        <span class="font-bold text-dark text-right">{hargaLabel}</span>
    </div>

    <!-- Total -->
    <div class="pt-4 border-t border-gray-100 mt-2">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-end gap-1 sm:gap-4">
            <span class="text-base font-extrabold text-dark">Total Tagihan</span>
            <span class="text-xl font-extrabold text-pink-text sm:text-right break-words">
                {formatCurrency(totalHarga)}
            </span>
        </div>
    </div>

    <!-- Info Pembayaran -->
    <div class="p-3 bg-blue/5 border border-blue/20 rounded-xl mt-3">
        <p class="text-[11px] text-blue-text leading-relaxed">
            <strong>Informasi:</strong> Pembayaran dilakukan langsung ke rekening Anda. Setelah pesanan dibuat, unggah bukti transfer di halaman detail pesanan untuk menerbitkan E-Ticket.
        </p>
    </div>
</div>