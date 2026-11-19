<script lang="ts">
    let { value = '', placeholder = '', oninput = undefined } = $props();
</script>

<input 
    data-testid="search-input" 
    {value} 
    {placeholder} 
    oninput={oninput}
/>