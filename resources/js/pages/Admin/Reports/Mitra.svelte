<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import PageHeader from '@/global/PageHeader.svelte';
    import ReportTabs from '@/components/reports/ReportTabs.svelte';
    import ReportFilter from '@/components/reports/ReportFilter.svelte';
    import ExportButtons from '@/components/reports/ExportButtons.svelte';
    import MitraSummary from '@/components/admin/reports/MitraSummary.svelte';
    import MitraTable from '@/components/admin/reports/MitraTable.svelte';
    
    import { getMitraReport } from '@/api/admin';
    import type { AdminReportMitraProps } from '@/types/props';
    import { adminMenu } from '@/utils/menus';

    let { data, filters }: AdminReportMitraProps = $props();

    let startDate = $state(filters.start_date);
    let endDate = $state(filters.end_date);

    function applyFilter() {
        getMitraReport(
            { start_date: startDate, end_date: endDate },
            { preserveState: true }
        );
    }
</script>

<svelte:head>
    <title>Kinerja Mitra | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Laporan Kinerja Mitra" menuItems={adminMenu}>
    <PageHeader
        title="Kinerja Mitra"
        description="Evaluasi performa pemandu wisata Anda berdasarkan trip yang diselesaikan."
    >
        {#snippet actions()}
            <ExportButtons
                pdfUrl={`/admin/reports/mitra/export-pdf?start_date=${startDate}&end_date=${endDate}`}
                excelUrl={`/admin/reports/mitra/export-excel?start_date=${startDate}&end_date=${endDate}`}
            />
        {/snippet}
    </PageHeader>

    <ReportTabs active="mitra" />

    <ReportFilter bind:startDate bind:endDate onApply={applyFilter} />

    <MitraSummary totalMitra={data.total_mitra} totalTrip={data.total_trip_completed} />

    <MitraTable mitras={data.mitras} />
</DashboardLayout>