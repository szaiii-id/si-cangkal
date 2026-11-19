<?php

namespace Database\Seeders;

use App\Enums\PackageKategori;
use App\Enums\PackageStatus;
use App\Models\Package;
use App\Models\PackageDay;
use App\Models\PackageFacility;
use App\Models\User;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $mitraABC = User::where('email', 'budi@ptabc.com')->first();
        $mitraAni = User::where('email', 'ani@gmail.com')->first();

        if (! $mitraABC || ! $mitraAni) {
            $this->command->warn('Mitra tidak ditemukan. Jalankan UserSeeder dulu.');

            return;
        }

        $this->seedPaket($mitraABC, [
            'kode' => 'PKG-'.now()->year.'-001',
            'nama' => 'Wisata Pasar Terapung 2D1N',
            'slug' => 'wisata-pasar-terapung-2d1n',
            'deskripsi' => 'Nikmati pengalaman unik menyusuri Sungai Martapura di pagi hari, melihat aktivitas jual beli di atas perahu tradisional khas Banjarmasin.',
            'kategori' => PackageKategori::BUDAYA,
            'durasi_hari' => 2,
            'durasi_malam' => 1,
            'harga_per_orang' => 850000,
            'harga_per_rombongan' => null,
            'max_peserta' => null,
            'days' => [
                ['hari_ke' => 1, 'judul' => 'Penjemputan & Pasar Terapung', 'deskripsi' => 'Dijemput di bandara, langsung menuju Lok Baintan. Menyusuri Sungai Martapura dengan perahu kelotok.', 'jam_mulai' => '06:00', 'jam_selesai' => '12:00', 'lokasi' => 'Lok Baintan'],
                ['hari_ke' => 2, 'judul' => 'Wisata Kuliner & Kepulangan', 'deskripsi' => 'Mencoba kuliner khas Banjar seperti Soto Banjar, Ketupat Kandangan. Setelah itu diantar ke bandara.', 'jam_mulai' => '08:00', 'jam_selesai' => '15:00', 'lokasi' => 'Pusat Kota Banjarmasin'],
            ],
            'facilities' => [
                ['tipe' => 'include', 'nama' => 'Transportasi PP Bandara'],
                ['tipe' => 'include', 'nama' => 'Pemandu Wisata'],
                ['tipe' => 'include', 'nama' => 'Homestay 1 Malam'],
                ['tipe' => 'include', 'nama' => 'Makan 3x'],
                ['tipe' => 'include', 'nama' => 'Tiket Perahu Kelotok'],
                ['tipe' => 'exclude', 'nama' => 'Tiket Pesawat'],
                ['tipe' => 'exclude', 'nama' => 'Pengeluaran Pribadi'],
                ['tipe' => 'exclude', 'nama' => 'Tipping Guide'],
            ],
        ]);

        $this->seedPaket($mitraABC, [
            'kode' => 'PKG-'.now()->year.'-002',
            'nama' => 'Petualangan Hutan Kalimantan 3D2N',
            'slug' => 'petualangan-hutan-kalimantan-3d2n',
            'deskripsi' => 'Jelajahi keindahan hutan tropis Kalimantan, temui orangutan di habitat aslinya, dan rasakan sensasi menginap di tengah hutan.',
            'kategori' => PackageKategori::PETUALANGAN,
            'durasi_hari' => 3,
            'durasi_malam' => 2,
            'harga_per_orang' => 1500000,
            'harga_per_rombongan' => null,
            'max_peserta' => 15,
            'days' => [
                ['hari_ke' => 1, 'judul' => 'Perjalanan ke Base Camp', 'deskripsi' => 'Perjalanan darat 4 jam menuju base camp. Sesi briefing dan persiapan trekking.', 'jam_mulai' => '08:00', 'jam_selesai' => '17:00', 'lokasi' => 'Taman Nasional Tanjung Puting'],
                ['hari_ke' => 2, 'judul' => 'Trekking & Wildlife Watching', 'deskripsi' => 'Trekking pagi untuk melihat orangutan, monyet proboscis, dan burung enggang. Malam: api unggun.', 'jam_mulai' => '05:00', 'jam_selesai' => '18:00', 'lokasi' => 'Heart of Borneo'],
                ['hari_ke' => 3, 'judul' => 'Kembali ke Kota', 'deskripsi' => 'Trekking ringan ke air terjun tersembunyi, lalu kembali ke Banjarmasin.', 'jam_mulai' => '07:00', 'jam_selesai' => '15:00', 'lokasi' => 'Banjarmasin'],
            ],
            'facilities' => [
                ['tipe' => 'include', 'nama' => 'Transportasi PP'],
                ['tipe' => 'include', 'nama' => 'Camping Equipment'],
                ['tipe' => 'include', 'nama' => 'Makan 3x Sehari'],
                ['tipe' => 'include', 'nama' => 'Guide Berpengalaman'],
                ['tipe' => 'include', 'nama' => 'Asuransi Perjalanan'],
                ['tipe' => 'exclude', 'nama' => 'Peralatan Pribadi'],
                ['tipe' => 'exclude', 'nama' => 'Alkohol'],
            ],
        ]);

        $this->seedPaket($mitraABC, [
            'kode' => 'PKG-'.now()->year.'-003',
            'nama' => 'Explore Banjarmasin City Tour 1D',
            'slug' => 'explore-banjarmasin-city-tour-1d',
            'deskripsi' => 'Menjelajahi ikon kota Banjarmasin: Masjid Sultan Suriansyah, Menara Pandang, dan kuliner legendaris.',
            'kategori' => PackageKategori::BUDAYA,
            'durasi_hari' => 1,
            'durasi_malam' => 0,
            'harga_per_orang' => 350000,
            'harga_per_rombongan' => null,
            'max_peserta' => 20,
            'days' => [
                ['hari_ke' => 1, 'judul' => 'City Tour Banjarmasin', 'deskripsi' => 'Mengunjungi Masjid Sultan Suriansyah (masjid tertua), Menara Pandang, dan Pasar Lama. Ditutup dengan makan malam kuliner khas.', 'jam_mulai' => '08:00', 'jam_selesai' => '20:00', 'lokasi' => 'Banjarmasin'],
            ],
            'facilities' => [
                ['tipe' => 'include', 'nama' => 'Transportasi AC'],
                ['tipe' => 'include', 'nama' => 'Tour Guide'],
                ['tipe' => 'include', 'nama' => 'Makan Siang & Malam'],
                ['tipe' => 'include', 'nama' => 'Tiket Masuk'],
                ['tipe' => 'exclude', 'nama' => 'Belanja Oleh-oleh'],
            ],
        ]);

        $this->seedPaket($mitraABC, [
            'kode' => 'PKG-'.now()->year.'-004',
            'nama' => 'Open Trip Bromo Midnight 2D1N',
            'slug' => 'open-trip-bromo-midnight-2d1n',
            'deskripsi' => 'Berangkat tengah malam dari Malang, mengejar sunrise di Penanjakan, lalu turun ke kawah Bromo dan Pasir Berbisik.',
            'kategori' => PackageKategori::WISATA_ALAM,
            'durasi_hari' => 2,
            'durasi_malam' => 1,
            'harga_per_orang' => 750000,
            'harga_per_rombongan' => null,
            'max_peserta' => 25,
            'days' => [
                ['hari_ke' => 1, 'judul' => 'Midnight Trip ke Penanjakan', 'deskripsi' => 'Jemput tengah malam (23:00), perjalanan ke Penanjakan 1 untuk menunggu sunrise.', 'jam_mulai' => '23:00', 'jam_selesai' => '05:00', 'lokasi' => 'Penanjakan Bromo'],
                ['hari_ke' => 2, 'judul' => 'Kawah Bromo & Pasir Berbisik', 'deskripsi' => 'Turun ke kawah Bromo, lanjut ke Pasir Berbisik dan Bukit Teletubbies. Kembali ke Malang.', 'jam_mulai' => '06:00', 'jam_selesai' => '14:00', 'lokasi' => 'Bromo Tengger Semeru'],
            ],
            'facilities' => [
                ['tipe' => 'include', 'nama' => 'Transportasi Jeep'],
                ['tipe' => 'include', 'nama' => 'Driver & BBM'],
                ['tipe' => 'include', 'nama' => 'Tiket Masuk Bromo'],
                ['tipe' => 'include', 'nama' => 'Makan 2x'],
                ['tipe' => 'include', 'nama' => 'Homestay 1 Malam'],
                ['tipe' => 'exclude', 'nama' => 'Sewa Jaket'],
                ['tipe' => 'exclude', 'nama' => 'Kuda Bromo'],
            ],
        ]);

        $this->seedPaket($mitraAni, [
            'kode' => 'PKG-'.now()->year.'-005',
            'nama' => 'Wisata Kuliner Banjarmasin 1D',
            'slug' => 'wisata-kuliner-banjarmasin-1d',
            'deskripsi' => 'Jelajahi kuliner legendaris Banjarmasin: Soto Banjar, Ketupat Kandangan, apam, dan banyak lagi.',
            'kategori' => PackageKategori::KULINER,
            'durasi_hari' => 1,
            'durasi_malam' => 0,
            'harga_per_orang' => 0,
            'harga_per_rombongan' => 2000000,
            'max_peserta' => 12,
            'days' => [
                ['hari_ke' => 1, 'judul' => 'Food Tour Kuliner Banjar', 'deskripsi' => 'Mengunjungi 6 warung kuliner legendaris, mencoba Soto Banjar, Ketupat Kandangan, Apam, dan lainnya.', 'jam_mulai' => '08:00', 'jam_selesai' => '17:00', 'lokasi' => 'Banjarmasin'],
            ],
            'facilities' => [
                ['tipe' => 'include', 'nama' => 'Transportasi Lokal'],
                ['tipe' => 'include', 'nama' => 'Food Guide'],
                ['tipe' => 'include', 'nama' => 'Makan 6x Sampel'],
                ['tipe' => 'exclude', 'nama' => 'Minuman Tambahan'],
            ],
        ]);

        $this->seedPaket($mitraAni, [
            'kode' => 'PKG-'.now()->year.'-006',
            'nama' => 'Bali Sunrise & Ubud Tour 3D2N',
            'slug' => 'bali-sunrise-ubud-tour-3d2n',
            'deskripsi' => 'Nikmati sunrise di Kintamani, jelajahi Ubud Monkey Forest, dan santai di Pantai Sanur.',
            'kategori' => PackageKategori::WISATA_ALAM,
            'durasi_hari' => 3,
            'durasi_malam' => 2,
            'harga_per_orang' => 2500000,
            'harga_per_rombongan' => null,
            'max_peserta' => 10,
            'days' => [
                ['hari_ke' => 1, 'judul' => 'Penjemputan & Sanur Beach', 'deskripsi' => 'Dijemput di Bandara Ngurah Rai, check-in hotel, lalu santai di Pantai Sanur.', 'jam_mulai' => '14:00', 'jam_selesai' => '20:00', 'lokasi' => 'Sanur'],
                ['hari_ke' => 2, 'judul' => 'Sunrise Kintamani & Ubud', 'deskripsi' => 'Berangkat subuh (03:00) ke Kintamani untuk sunrise Gunung Batur. Lanjut ke Ubud Monkey Forest dan Tegalalang.', 'jam_mulai' => '03:00', 'jam_selesai' => '18:00', 'lokasi' => 'Kintamani & Ubud'],
                ['hari_ke' => 3, 'judul' => 'Tanah Lot & Kepulangan', 'deskripsi' => 'Mengunjungi Pura Tanah Lot, lalu diantar ke bandara.', 'jam_mulai' => '08:00', 'jam_selesai' => '15:00', 'lokasi' => 'Tanah Lot'],
            ],
            'facilities' => [
                ['tipe' => 'include', 'nama' => 'Hotel 2 Malam'],
                ['tipe' => 'include', 'nama' => 'Transportasi AC'],
                ['tipe' => 'include', 'nama' => 'Driver & BBM'],
                ['tipe' => 'include', 'nama' => 'Makan 5x'],
                ['tipe' => 'include', 'nama' => 'Tiket Masuk Objek Wisata'],
                ['tipe' => 'include', 'nama' => 'Guide Lokal'],
                ['tipe' => 'exclude', 'nama' => 'Tiket Pesawat'],
                ['tipe' => 'exclude', 'nama' => 'Spa & Massage'],
            ],
        ]);

        $this->command->info('PackageSeeder selesai (6 paket lengkap).');
    }

    /**
     * Seed satu paket + days + facilities.
     *
     * @param  array{
     *     kode: string,
     *     nama: string,
     *     slug: string,
     *     deskripsi: string,
     *     kategori: PackageKategori,
     *     durasi_hari: int,
     *     durasi_malam: int,
     *     harga_per_orang: int,
     *     harga_per_rombongan: int|null,
     *     max_peserta: int|null,
     *     days: array<int, array{
     *         hari_ke: int,
     *         judul: string,
     *         deskripsi: string,
     *         jam_mulai: string,
     *         jam_selesai: string,
     *         lokasi: string
     *     }>,
     *     facilities: array<int, array{tipe: string, nama: string}>
     * }  $data
     */
    protected function seedPaket(User $mitra, array $data): void
    {
        $package = Package::updateOrCreate(
            ['kode' => $data['kode']],
            [
                'mitra_id' => $mitra->id,
                'nama' => $data['nama'],
                'slug' => $data['slug'],
                'deskripsi' => $data['deskripsi'],
                'kategori' => $data['kategori']->value,
                'durasi_hari' => $data['durasi_hari'],
                'durasi_malam' => $data['durasi_malam'],
                'harga_per_orang' => $data['harga_per_orang'],
                'harga_per_rombongan' => $data['harga_per_rombongan'],
                'max_peserta' => $data['max_peserta'],
                'timezone' => 'Asia/Makassar',
                'status' => PackageStatus::AKTIF->value,
            ]
        );

        PackageDay::where('package_id', $package->id)->delete();
        foreach ($data['days'] as $day) {
            PackageDay::create([
                'package_id' => $package->id,
                'hari_ke' => $day['hari_ke'],
                'judul' => $day['judul'],
                'deskripsi' => $day['deskripsi'],
                'jam_mulai' => $day['jam_mulai'],
                'jam_selesai' => $day['jam_selesai'],
                'lokasi' => $day['lokasi'],
            ]);
        }

        PackageFacility::where('package_id', $package->id)->delete();
        $urutan = ['include' => 0, 'exclude' => 0];
        foreach ($data['facilities'] as $facility) {
            $urutan[$facility['tipe']]++;
            PackageFacility::create([
                'package_id' => $package->id,
                'tipe' => $facility['tipe'],
                'nama' => $facility['nama'],
                'urutan' => $urutan[$facility['tipe']],
            ]);
        }
    }
}
