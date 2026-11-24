import type { BookingStatus, PaymentMetode } from '../enum';
import type { UserMini, UserWithUsername, UserWithEmail } from './user';
import type { BankAccount, MitraBankAccount } from './bank-account';
import type { PackageEloquent } from './package';
import type { RefundEloquent, RefundResource } from './refund';

export type BookingEloquent = {
    id: number;
    kode: string;
    package_id: number;
    user_id: number;
    mitra_id: number;
    tanggal_keberangkatan: string;
    jumlah_peserta: number;
    total_harga: string;
    status: BookingStatus;
    payment_proof_path: string | null;
    payment_verified_at: string | null;
    payment_reject_reason: string | null;
    expired_at: string | null;
    catatan: string | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    package?: PackageEloquent | null;
    client?: UserMini | null;
    mitra?: (UserMini & { bankAccounts?: MitraBankAccount[] }) | null;
    participants?: BookingParticipant[];
    payments?: PaymentEloquent[];

    refunds?: RefundEloquent[];
};

export type BookingResource = {
    id: number;
    kode: string;
    status: BookingStatus;
    statusLabel: string;
    tanggal_keberangkatan: string;
    jumlah_peserta: number;
    total_harga: string;
    has_payment_proof: boolean;
    payment_reject_reason: string | null;
    expired_at: string | null;
    catatan: string | null;
    created_at: string;
    package?: {
        id: number;
        nama: string;
        kode: string;
        slug: string;
        timezone: string;
        harga_per_orang: string;
        harga_per_rombongan: string | null;
        durasi_hari: number;
        durasi_malam: number;
        cover_image: string | null;
    } | null;
    client?: UserWithUsername | null;
    mitra?: (UserMini & { bankAccounts: BankAccount[] }) | null;
    participants?: BookingParticipantResource[];
    payments?: PaymentResource[];
    refunds?: RefundResource[];
};

export type BookingParticipant = {
    id: number;
    booking_id: number;
    nama: string;
    umur: number | null;
    no_hp: string | null;
    catatan: string | null;
};

export type BookingParticipantResource = {
    id: number;
    nama: string;
    umur: number | null;
    umurLabel: string;
    no_hp: string | null;
    catatan: string | null;
    has_catatan: boolean;
};

export type PaymentEloquent = {
    id: number;
    booking_id: number;
    jumlah: string;
    metode: PaymentMetode;
    channel_code: string | null;
    verified_by: number | null;
    verified_at: string | null;
    catatan: string | null;
    created_at: string;
    updated_at: string;
    verifier?: UserMini | null;
};

export type PaymentResource = {
    id: number;
    jumlah: string;
    jumlahRupiah: string;
    metode: PaymentMetode;
    metodeLabel: string;
    isVerified: boolean;
    verified_at: string | null;
    catatan: string | null;
    verifier?: UserWithEmail | null;
};

export type ManualParticipant = {
    nama: string;
    umur: string | number;
    no_hp: string;
    catatan: string;
};
