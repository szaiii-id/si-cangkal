<script lang="ts">
    import LegalLayout from '@/components/layout/LegalLayout.svelte';
</script>

<LegalLayout title="Kebijakan Cookie" lastUpdated="11 November 2026">
    <p class="lead">Kami menggunakan cookie dan teknologi pelacakan serupa untuk memastikan keamanan sistem dan meningkatkan pengalaman Anda di aplikasi SI-CANGKAL.</p>

    <h2 class="text-lg font-bold text-dark mt-6 mb-2">1. Cookie yang Kami Gunakan</h2>
    <ul class="list-disc pl-5 mt-2 space-y-2">
        <li><strong>XSRF-TOKEN:</strong> Cookie keamanan wajib (esensial) yang digunakan untuk mencegah serangan pemalsuan permintaan lintas situs (CSRF), memastikan formulir dan data yang Anda kirim ke server kami aman dari peretasan.</li>
        <li><strong>viewer_timezone:</strong> Menyimpan preferensi zona waktu Anda agar tanggal, waktu pesanan, dan jadwal keberangkatan ditampilkan secara akurat sesuai lokasi perangkat Anda.</li>
        <li><strong>session_id:</strong> Menjaga status otentikasi/login Anda sehingga Anda tidak perlu login berulang kali saat berpindah halaman di dalam aplikasi.</li>
    </ul>

    <h2 class="text-lg font-bold text-dark mt-6 mb-2">2. Kontrol Cookie</h2>
    <p>Anda dapat mengatur, menghapus, atau memblokir cookie melalui pengaturan browser web Anda. Namun, harap diperhatikan bahwa memblokir cookie esensial (seperti <i>session</i> dan <i>XSRF-TOKEN</i>) akan membuat Anda tidak bisa login atau menggunakan fitur pemesanan di aplikasi ini.</p>
</LegalLayout>