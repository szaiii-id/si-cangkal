<script lang="ts">
    import { AreaChart } from '@faintshadow/flarecharts';
    import { inertia } from '@inertiajs/svelte';

    // ==========================================
    // TYPES
    // ==========================================
    type MonthlyRevenue = {
        bulan: string;
        total: number;
    };

    // ==========================================
    // PROPS
    // ==========================================
    let { monthly_revenue = [] }: { monthly_revenue?: MonthlyRevenue[] } = $props();

    // ==========================================
    // HELPERS
    // ==========================================
    function formatCompactNumber(val: number): string {
        if (val >= 1000000) return (val / 1000000) + 'jt';
        if (val >= 1000) return (val / 1000) + 'rb';
        return val.toString();
    }

    // ==========================================
    // DERIVED
    // ==========================================
    let revenueChartData = $derived([
        {
            name: 'Pendapatan',
            data: monthly_revenue.map((d) => ({
                x: d.bulan,
                y: d.total,
            })),
            color: '#F3278C', // Warna Pink
        },
    ]);
</script>

<div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-5 sm:p-8 mb-6 relative">
    <div class="flex items-center justify-between mb-6 sm:mb-8">
        <div>
            <h3 class="text-base font-extrabold text-dark">Tren Pendapatan</h3>
            <p class="text-xs text-gray-500 mt-1">Pergerakan pendapatan 6 bulan terakhir</p>
        </div>
        <a 
            href="/admin/reports" 
            use:inertia 
            class="text-xs font-bold text-pink-text hover:text-pink-dark transition-colors bg-pink/5 px-3 py-1.5 rounded-full"
            aria-label="Lihat detail laporan pendapatan"
        >
            Lihat Detail
        </a>
    </div>

    <div class="h-64 sm:h-72 w-full">
        <AreaChart 
            series={revenueChartData} 
            xAxis={{ 
                type: 'band'
            }} 
            yAxis={{ 
                type: 'linear',
                format: (val) => formatCompactNumber(Number(val))
            }}
        />
    </div>
</div>