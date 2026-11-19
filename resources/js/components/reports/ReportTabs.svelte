<script lang="ts">
    import { inertia } from '@inertiajs/svelte';

    type TabKey = 'pendapatan' | 'mitra';

    let { active }: { active: TabKey } = $props();

    const tabs: { key: TabKey; label: string; href: string }[] = [
        { key: 'pendapatan', label: 'Pendapatan', href: '/admin/reports' },
        { key: 'mitra', label: 'Kinerja Mitra', href: '/admin/reports/mitra' },
    ];
</script>

<nav class="flex flex-col sm:flex-row gap-2 mb-6" aria-label="Navigasi Laporan">
    {#each tabs as tab}
        <a 
            href={tab.href} 
            use:inertia
            aria-current={active === tab.key ? 'page' : undefined}
            class="flex-1 sm:flex-none text-center px-5 py-2.5 rounded-full font-bold text-sm transition-all
                {active === tab.key 
                    ? 'bg-pink-dark text-white shadow-md' 
                    : 'bg-white border border-gray-200 text-dark hover:bg-gray-50 shadow-sm'}"
        >
            {tab.label}
        </a>
    {/each}
</nav>