import type {
    PackageFacilityTipe,
    PackageKategori,
    PackageStatus,
} from '../enum';
import type { UserMini, UserWithEmail } from './user';

export type PackageEloquent = {
    id: number;
    mitra_id: number;
    kode: string;
    nama: string;
    slug: string;
    deskripsi: string | null;
    kategori: PackageKategori;
    durasi_hari: number;
    durasi_malam: number;
    harga_per_orang: string;
    harga_per_rombongan: string | null;
    max_peserta: number | null;
    timezone: string;
    cover_image: string | null;
    cover_image_url?: string | null;
    status: PackageStatus;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    mitra?: UserMini | null;
    days?: PackageDay[];
    facilities?: PackageFacility[];
};

export type PackageResource = {
    id: number;
    mitra_id: number;
    kode: string;
    nama: string;
    slug: string;
    deskripsi: string | null;
    kategori: PackageKategori;
    durasi_hari: number;
    durasi_malam: number;
    harga_per_orang: string;
    harga_per_rombongan: string | null;
    max_peserta: number | null;
    isRombongan: boolean;
    timezone: string;
    cover_image: string | null;
    cover_image_url?: string | null;
    status: PackageStatus;
    created_at: string;
    mitra?: UserWithEmail | null;
    days?: PackageDay[];
    facilities?: PackageFacility[];
};

export type PackageDay = {
    id: number;
    package_id: number;
    hari_ke: number;
    judul: string;
    deskripsi: string | null;
    jam_mulai: string | null;
    jam_selesai: string | null;
    lokasi: string | null;
};

export type PackageFacility = {
    id: number;
    package_id: number;
    tipe: PackageFacilityTipe;
    nama: string;
    urutan: number;
};

export type PackageInfoFormData = {
    nama: string;
    deskripsi: string;
    kategori: PackageKategori;
    durasi_hari: number;
    durasi_malam: number;
    harga_per_orang: number;
    harga_per_rombongan: number;
    max_peserta: number;
    timezone: string;
};

// ==========================================
// TAMBAHAN UNTUK EDITOR (EDIT & CREATE)
// ==========================================

export type EditableDay = {
    id: number;
    hari_ke: number;
    judul: string;
    deskripsi: string | null;
    jam_mulai: string | null;
    jam_selesai: string | null;
    lokasi: string | null;
};

export type EditableFacility = {
    id: number;
    tipe: PackageFacilityTipe;
    nama: string;
};
