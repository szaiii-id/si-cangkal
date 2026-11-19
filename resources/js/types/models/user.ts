export type UserRole = 'admin' | 'mitra' | 'client';

export type User = {
    id: number;
    name: string;
    username: string | null;
    email: string;
    role: UserRole;
    timezone: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    has_fcm_token: boolean;
    profile?: Profile | null;
    bank_accounts?: import('./bank-account').BankAccount[];
};

export type Profile = {
    id: number;
    user_id: number;
    tipe: string;
    nama_penyedia_usaha: string | null;
    nomor_identitas: string | null;
    alamat: string | null;
    telepon: string | null;
    provinsi: string | null;
    kota: string | null;
    kode_pos: string | null;
    logo: string | null;
    deskripsi: string | null;
    meta: Record<string, unknown> | null;
};

export type UserMini = {
    id: number;
    name: string;
    email: string;
    username: string | null;
};

export type UserWithEmail = Pick<User, 'id' | 'name' | 'email'>;

export type UserWithUsername = Pick<
    User,
    'id' | 'name' | 'email' | 'username'
>;


export type UserAccountFormData = {
    name: string;
    email: string;
    username: string;
};

export type UserProfileFormData = {
    profile: {
        nama_penyedia_usaha: string | null;
        nomor_identitas: string | null;
        telepon: string | null;
        alamat: string | null;
        provinsi: string | null;
        kota: string | null;
        kode_pos: string | null;
        deskripsi: string | null;
    };
};