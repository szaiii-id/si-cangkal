<script lang="ts">
    import DashboardLayout from '@/components/layout/DashboardLayout.svelte';
    import PageHeader from '@/global/PageHeader.svelte';
    import ReportTabs from '@/components/reports/ReportTabs.svelte';
    import ReportFilter from '@/components/reports/ReportFilter.svelte';
    import ExportButtons from '@/components/reports/ExportButtons.svelte';
    import RevenueSummary from '@/components/admin/reports/RevenueSummary.svelte';
    import RevenueChart from '@/components/admin/reports/RevenueChart.svelte';
    import RevenueTable from '@/components/admin/reports/RevenueTable.svelte';
    
    import { getRevenueReport } from '@/api/admin';
    import type { AdminReportRevenueProps } from '@/types/props';
    import { adminMenu } from '@/utils/menus';

    let { data, monthly_revenue, filters }: AdminReportRevenueProps = $props();

    let startDate = $state(filters.start_date);
    let endDate = $state(filters.end_date);

    function applyFilter() {
        getRevenueReport(
            { start_date: startDate, end_date: endDate },
            { preserveState: true }
        );
    }
</script>

<svelte:head>
    <title>Laporan Pendapatan | SI-CANGKAL</title>
</svelte:head>

<DashboardLayout title="Laporan Pendapatan" menuItems={adminMenu}>
    <PageHeader
        title="Laporan Pendapatan"
        description="Pantau performa keuangan dan tren bisnis Anda."
    >
        {#snippet actions()}
            <ExportButtons
                pdfUrl={`/admin/reports/export-pdf?start_date=${startDate}&end_date=${endDate}`}
                excelUrl={`/admin/reports/export-excel?start_date=${startDate}&end_date=${endDate}`}
            />
        {/snippet}
    </PageHeader>

    <ReportTabs active="pendapatan" />

    <ReportFilter bind:startDate bind:endDate onApply={applyFilter} />

    <RevenueSummary totalRevenue={data.total_revenue} />

    <RevenueChart {monthly_revenue} />

    <RevenueTable transactions={data.transactions} />
</DashboardLayout>