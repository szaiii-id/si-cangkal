<!--
    Widget bintang 1–5.

    Dipakai dua mode:
    - interaktif (form review): tombol, bisa diklik, ada aria-pressed.
    - readonly (katalog & kartu review): hanya tampilan, aria-label ringkas.

    Warna memakai `text-sun-text` (bukan `text-sun-dark`) agar lolos WCAG AA
    di atas latar putih / bg-sun/10.
-->
<script lang="ts">
    import { ratingLabel } from '@/utils/review';

    type Props = {
        value?: number;
        readonly?: boolean;
        onChange?: (rating: number) => void;
        size?: 'xs' | 'sm' | 'lg';
    };

    let {
        value = $bindable(0),
        readonly = false,
        onChange = () => {},
        size = 'lg',
    }: Props = $props();

    function pilih(rating: number): void {
        value = rating;
        onChange(rating);
    }

    const bintang = [1, 2, 3, 4, 5];

    let teksLabel = $derived(ratingLabel(value));
    let kelasBintang = $derived(
        size === 'lg' ? 'text-3xl' : size === 'sm' ? 'text-base' : 'text-xs',
    );
</script>

<div>
    {#if readonly}
        <span
            role="img"
            aria-label={value >= 1 && value <= 5
                ? `Rating ${value} dari 5 bintang`
                : 'Belum ada rating'}
            class="inline-flex items-center gap-0.5 {kelasBintang} leading-none"
        >
            {#each bintang as b}
                <span aria-hidden="true" class={b <= value ? 'text-sun-text' : 'text-gray-300'}>★</span>
            {/each}
        </span>
    {:else}
        <div role="group" aria-label="Pilih rating bintang" class="inline-flex items-center gap-1">
            {#each bintang as b}
                <button
                    type="button"
                    aria-label={`${b} bintang`}
                    aria-pressed={value === b}
                    onclick={() => pilih(b)}
                    class="{kelasBintang} leading-none px-1 py-0.5 rounded transition-transform hover:scale-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-dark {b <= value
                        ? 'text-sun-text'
                        : 'text-gray-300'}"
                >
                    <span aria-hidden="true">{b <= value ? '★' : '☆'}</span>
                </button>
            {/each}
        </div>

        {#if teksLabel}
            <p class="mt-2 text-sm font-bold text-sun-text">{teksLabel}</p>
        {/if}
    {/if}
</div>
