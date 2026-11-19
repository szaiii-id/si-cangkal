<script lang="ts">
    import { onMount } from 'svelte';
    import { inertia, page } from '@inertiajs/svelte';
    import { fly, fade } from 'svelte/transition';

    let isMobileMenuOpen = $state(false);
    let activeSection = $state('beranda');

    let user = $derived(page.props.auth?.user);

    let dashboardUrl = $derived(
        user?.role === 'admin'
            ? '/admin/dashboard'
            : user?.role === 'mitra'
              ? '/mitra/dashboard'
              : '/client/dashboard'
    );

    // PERBAIKAN: Definisikan menu sebagai array (DRY)
    const menuItems = [
        { id: 'beranda', label: 'Beranda' },
        { id: 'trip', label: 'Trip & Bundle' },
        { id: 'visimisi', label: 'Visi Misi' },
        { id: 'contact', label: 'Kontak' },
    ];

    function toggleMenu() {
        isMobileMenuOpen = !isMobileMenuOpen;
    }

    function closeMenu() {
        isMobileMenuOpen = false;
    }

    onMount(() => {
        const sections = ['beranda', 'trip', 'visimisi', 'contact'];
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    activeSection = entry.target.id;
                }
            });
        }, {
            rootMargin: '-20% 0px -60% 0px' 
        });

        sections.forEach(id => {
            const el = document.getElementById(id);
            if (el) observer.observe(el);
        });

        const handleResize = () => {
            if (window.innerWidth >= 1024) {
                isMobileMenuOpen = false;
            }
        };
        window.addEventListener('resize', handleResize);

        return () => {
            observer.disconnect();
            window.removeEventListener('resize', handleResize);
        };
    });
</script>

<!-- OVERLAY BACKDROP -->
{#if isMobileMenuOpen}
    <div 
        transition:fade={{ duration: 200 }}
        class="fixed inset-0 bg-black/60 z-40 lg:hidden backdrop-blur-sm"
        onclick={closeMenu}
        role="presentation"
    ></div>
{/if}

<!-- NAVBAR -->
<header class="fixed top-6 left-1/2 -translate-x-1/2 z-50 w-[94%] md:w-[92%] max-w-6xl">
    <div class="relative bg-dark/95 backdrop-blur-xl border border-gray-700/50 shadow-2xl shadow-dark/20 rounded-full px-4 md:px-8 py-2 md:py-4 flex items-center justify-between transition-all duration-300">
        
        <!-- KIRI -->
        <div class="flex-1 flex justify-start items-center">
            <button 
                onclick={toggleMenu} 
                aria-expanded={isMobileMenuOpen}
                class="lg:hidden text-gray-300 hover:text-sun focus:outline-none p-1 -ml-1" 
                aria-label="Buka menu navigasi"
            >
                <svg class="h-6 w-6 transition-transform duration-300 {isMobileMenuOpen ? 'rotate-90' : ''}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    {#if isMobileMenuOpen}
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    {:else}
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    {/if}
                </svg>
            </button>

            <a href="#beranda" class="hidden lg:flex text-3xl font-extrabold text-white tracking-tight items-center gap-1">
                SI-CANGKAL<span class="text-sun">.</span>
            </a>
        </div>

        <!-- TENGAH (Logo Mobile & Menu Desktop) -->
        <div class="absolute left-1/2 -translate-x-1/2 flex justify-center lg:hidden">
            <a href="#beranda" class="text-xl font-extrabold text-white tracking-tight flex items-center gap-1">
                SI-CANGKAL<span class="text-sun">.</span>
            </a>
        </div>

        <!-- PERBAIKAN: Gunakan loop untuk menu desktop -->
        <nav class="hidden lg:flex justify-center space-x-8" aria-label="Menu Utama">
            {#each menuItems as item}
                <a 
                    href="#{item.id}" 
                    class="text-base font-bold transition-colors {activeSection === item.id ? 'text-sun' : 'text-gray-300 hover:text-sun'}"
                    aria-current={activeSection === item.id ? 'location' : undefined}
                >
                    {item.label}
                </a>
            {/each}
        </nav>

        <!-- KANAN (Tombol Login/Register/Dashboard) -->
        <div class="flex-1 flex justify-end items-center gap-2 md:gap-4">
            {#if user}
                <a 
                    href={dashboardUrl} 
                    use:inertia 
                    class="inline-flex bg-green-dark text-white px-4 py-1.5 md:px-8 md:py-2.5 rounded-full text-sm md:text-base font-bold hover:bg-green transition-all shadow-md hover:shadow-lg transform hover:-translate-y-0.5"
                >
                    Dashboard
                </a>
            {:else}
                <a href="/register" use:inertia class="hidden lg:inline-flex bg-pink-dark text-white px-6 py-2.5 rounded-full text-base font-bold hover:bg-sun-dark transition-all shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                    Daftar
                </a>
                
                <a href="/login" use:inertia class="inline-flex bg-pink-dark text-white px-4 py-1.5 md:px-8 md:py-2.5 rounded-full text-sm md:text-base font-bold hover:bg-sun-dark transition-all shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                    Login
                </a>
            {/if}
        </div>
    </div>

    <!-- MENU DROPDOWN MOBILE -->
    {#if isMobileMenuOpen}
        <div 
            transition:fly={{ y: -20, duration: 250 }}
            class="lg:hidden mt-3 bg-dark/95 backdrop-blur-xl border border-gray-700/50 shadow-2xl rounded-3xl p-5 space-y-2 relative"
        >
            <!-- PERBAIKAN: Gunakan loop untuk menu mobile -->
            {#each menuItems as item}
                <a 
                    href="#{item.id}" 
                    onclick={closeMenu} 
                    class="block px-4 py-3 rounded-xl font-bold {activeSection === item.id ? 'text-sun bg-white/10' : 'text-gray-300 hover:bg-white/10'} transition-colors"
                    aria-current={activeSection === item.id ? 'location' : undefined}
                >
                    {item.label}
                </a>
            {/each}
            
            <div class="pt-4 mt-2 border-t border-gray-700/50 flex flex-col gap-3">
                {#if user}
                    <a href={dashboardUrl} use:inertia onclick={closeMenu} class="block text-center w-full bg-green-dark text-white py-3.5 rounded-xl font-bold shadow-md hover:bg-green transition-all">Ke Dashboard</a>
                {:else}
                    <a href="/register" use:inertia onclick={closeMenu} class="block text-center w-full bg-pink-dark text-white py-3.5 rounded-xl font-bold shadow-md hover:bg-sun-dark transition-all">Daftar Akun</a>
                {/if}
            </div>
        </div>
    {/if}
</header>