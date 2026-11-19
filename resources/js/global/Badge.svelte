<script lang="ts">
    /**
     * Badge — label status/kategori dengan warna dari design system.
     */
    import {
        bookingStatusLabel,
        bookingStatusBadge,
        tripStatusLabel,
        tripStatusBadge,
        packageStatusLabel,
        packageStatusBadge,
        packageKategoriLabel,
        packageKategoriBadge,
        packageFacilityTipeLabel,
        packageFacilityTipeBadge,
    } from '@/utils/enums';
    
    // Jika Anda punya utilitas 'cn', gunakan ini. 
    // Jika tidak, kita akan pakai filter Boolean di template.
    // import { cn } from '@/utils/utils';

    // ==========================================
    // TYPES
    // ==========================================
    type BadgeVariant =
        | 'booking_status'
        | 'trip_status'
        | 'package_status'
        | 'package_kategori'
        | 'package_facility_tipe';

    type BadgeSize = 'sm' | 'md';
    type BadgeIcon = 'check' | 'clock' | 'x' | 'warning' | null;

    // ==========================================
    // PROPS
    // ==========================================
    let {
        label = '',
        class: className = '',
        variant,
        value,
        size = 'md',
        icon = null,
    }: {
        label?: string;
        class?: string;
        variant?: BadgeVariant;
        value?: string;
        size?: BadgeSize;
        icon?: BadgeIcon;
    } = $props();

    // ==========================================
    // RESOLVE — variant + value → label + class
    // ==========================================
    function resolveFromVariant(
        v: BadgeVariant,
        val: string,
    ): { label: string; className: string } {
        switch (v) {
            case 'booking_status':
                return { label: bookingStatusLabel(val), className: bookingStatusBadge(val) };
            case 'trip_status':
                return { label: tripStatusLabel(val), className: tripStatusBadge(val) };
            case 'package_status':
                return { label: packageStatusLabel(val), className: packageStatusBadge(val) };
            case 'package_kategori':
                return { label: packageKategoriLabel(val), className: packageKategoriBadge(val) };
            case 'package_facility_tipe':
                return { label: packageFacilityTipeLabel(val), className: packageFacilityTipeBadge(val) };
            default:
                // PERBAIKAN: Fallback jika variant tidak dikenal
                return { label: val, className: 'bg-gray-100 text-gray-700' };
        }
    }

    // ==========================================
    // DERIVED
    // ==========================================
    let resolved = $derived.by(() => {
        if (variant && value) {
            const r = resolveFromVariant(variant, value);
            return { label: r.label, className: r.className };
        }
        return { label, className };
    });

    let sizeClass = $derived(
        size === 'sm' ? 'px-2 py-0.5 text-[10px]' : 'px-3 py-1.5 text-[11px]'
    );

    let iconPath = $derived.by(() => {
        if (icon === 'check') return 'M5 13l4 4L19 7';
        if (icon === 'clock') return 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z';
        if (icon === 'x') return 'M6 18L18 6M6 6l12 12';
        if (icon === 'warning') return 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z';
        return null;
    });

    // Menggabungkan class dengan aman (membersihkan spasi kosong)
    let combinedClass = $derived(
        [
            'inline-flex items-center gap-1 rounded-full font-extrabold whitespace-nowrap',
            sizeClass,
            resolved.className,
            className, // Izinkan custom class dari parent
        ]
            .filter(Boolean)
            .join(' ')
    );
</script>

<span class={combinedClass}>
    {#if iconPath}
        <svg
            class="w-3 h-3 flex-shrink-0"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2.5"
            aria-hidden="true"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d={iconPath}
            />
        </svg>
    {/if}
    {resolved.label}
</span>