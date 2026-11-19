export type MitraBankAccount = {
    id: number;
    user_id: number;
    tipe: 'bank' | 'ewallet';
    nama_penyedia: string;
    nomor_rekening: string;
    nama_pemilik: string;
    is_primary: boolean;
    created_at: string;
    updated_at: string;
};

export type BankAccount = {
    id: number;
    tipe: 'bank' | 'ewallet';
    nama_penyedia: string;
    nomor_rekening: string;
    nama_pemilik: string;
    is_primary: boolean;
};