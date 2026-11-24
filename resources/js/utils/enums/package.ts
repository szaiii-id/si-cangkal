import type {
    PackageFacilityTipe,
    PackageKategori,
    PackageStatus,
} from '@/types';

const STATUS_LABEL: Record<PackageStatus, string> = {
    draft: 'Draft',
    aktif: 'Aktif',
    arsip: 'Arsip',
};

const STATUS_BADGE: Record<PackageStatus, string> = {
    draft: 'bg-gray-100 text-gray-700 border border-gray-200',
    aktif: 'bg-green/10 text-green-text border border-green/20',
    arsip: 'bg-sun/10 text-sun-text border border-sun/20',
};

const STATUS_SHORT: Record<PackageStatus, string> = {
    draft: 'Draft',
    aktif: 'Aktif',
    arsip: 'Arsip',
};

const KATEGORI_LABEL: Record<PackageKategori, string> = {
    wisata_alam: 'Wisata Alam',
    budaya: 'Budaya',
    kuliner: 'Kuliner',
    petualangan: 'Petualangan',
    lainnya: 'Lainnya',
};

const KATEGORI_BADGE: Record<PackageKategori, string> = {
    wisata_alam: 'bg-green/10 text-green-text border border-green/20',
    budaya: 'bg-purple/10 text-purple-text border border-purple/20',
    kuliner: 'bg-sun/10 text-sun-text border border-sun/20',
    petualangan: 'bg-blue/10 text-blue-text border border-blue/20',
    lainnya: 'bg-gray-100 text-gray-700 border border-gray-200',
};

const FACILITY_LABEL: Record<PackageFacilityTipe, string> = {
    include: 'Termasuk',
    exclude: 'Tidak Termasuk',
};

const FACILITY_BADGE: Record<PackageFacilityTipe, string> = {
    include: 'bg-green/10 text-green-text border border-green/20',
    exclude: 'bg-red-50 text-red-700 border border-red-200',
};

export function packageStatusLabel(status: string): string {
    return STATUS_LABEL[status as PackageStatus] ?? status;
}

export function packageStatusBadge(status: string): string {
    return (
        STATUS_BADGE[status as PackageStatus] ??
        'bg-gray-100 text-gray-700 border border-gray-200'
    );
}

export function packageStatusShort(status: string): string {
    return STATUS_SHORT[status as PackageStatus] ?? status;
}

export function packageKategoriLabel(kategori: string): string {
    return KATEGORI_LABEL[kategori as PackageKategori] ?? kategori;
}

export function packageKategoriBadge(kategori: string): string {
    return (
        KATEGORI_BADGE[kategori as PackageKategori] ??
        'bg-gray-100 text-gray-700 border border-gray-200'
    );
}

export function packageFacilityTipeLabel(tipe: string): string {
    return FACILITY_LABEL[tipe as PackageFacilityTipe] ?? tipe;
}

export function packageFacilityTipeBadge(tipe: string): string {
    return (
        FACILITY_BADGE[tipe as PackageFacilityTipe] ??
        'bg-gray-100 text-gray-700 border border-gray-200'
    );
}
