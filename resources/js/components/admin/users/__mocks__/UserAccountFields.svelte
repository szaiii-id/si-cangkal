<script lang="ts">
    let { form }: any = $props();
</script>

<div data-testid="user-account-fields">
    Mock Fields: {form?.name ?? 'undefined'}
</div>