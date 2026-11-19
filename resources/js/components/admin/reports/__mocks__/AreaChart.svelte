<script lang="ts">
    let { series }: { series: any } = $props();
</script>

<div data-testid="area-chart" data-series={JSON.stringify(series)}></div>