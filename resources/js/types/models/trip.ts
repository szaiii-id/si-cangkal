import type { TripStatus, BookingStatus } from '../enum';
import type { UserMini, UserWithEmail } from './user';
import type { PackageEloquent, PackageResource } from './package';
import type { BookingEloquent } from './booking';

export type TripEloquent = {
    id: number;
    kode: string;
    booking_id: string;
    package_id: number;
    mitra_id: string;
    snapshot_package: Record<string, unknown>;
    tanggal_mulai: string;
    tanggal_selesai: string;
    jumlah_peserta: number;
    timezone: string;
    status: TripStatus;
    created_at: string;
    updated_at: string;
    package?: PackageEloquent | null;
    booking?: BookingEloquent | null;
    mitra?: UserMini | null;
    days?: TripDay[];
};

export type TripResource = {
    id: number;
    kode: string;
    booking_id: string;
    package_id: number;
    mitra_id: string | null;
    tanggal_mulai: string;
    tanggal_selesai: string;
    jumlah_peserta: number;
    timezone: string;
    status: TripStatus;
    statusLabel: string;
    snapshot_nama: string;
    snapshot_durasi_hari: number;
    created_at: string;
    package?: Pick<
        PackageResource,
        'id' | 'nama' | 'kode' | 'slug' | 'timezone' | 'cover_image'
    > | null;
    booking?: {
        id: number;
        kode: string;
        jumlah_peserta: number;
        tanggal_keberangkatan: string;
        status: BookingStatus;
        client: UserWithEmail | null;
    } | null;
    mitra?: UserWithEmail | null;
    days?: TripDay[];
};

export type TripDay = {
    id: number;
    trip_id: number;
    hari_ke: number;
    tanggal: string;
    judul: string;
    deskripsi: string | null;
    status: 'pending' | 'in_progress' | 'completed';
    created_at: string;
    updated_at: string;
};
