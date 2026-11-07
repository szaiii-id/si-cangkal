/**
 * Props per halaman Inertia.
 *
 * Mirror dari controller Inertia::render() di backend v3.0.
 *
 * ⚠️ KONTRAK AKTUAL BACKEND (bukan idealisme):
 *
 * Endpoint yang mengirim Resource (mix camelCase & snake_case):
 * - Client\BookingController@show  → BookingResource
 * - Admin\BookingController@show   → BookingResource
 * - Mitra\BookingController@show   → BookingResource
 *
 * Endpoint yang mengirim Eloquent mentah (snake_case semua):
 * - Client\BookingController@index → Booking Eloquent
 * - Admin\BookingController@index  → Booking Eloquent
 * - Mitra\BookingController@index  → Booking Eloquent
 * - Admin\TripMonitoringController → Trip Eloquent
 * - Mitra\TripController           → Trip Eloquent
 * - Admin\RefundController         → Refund Eloquent
 * - Mitra\RefundController         → Refund Eloquent
 * - Admin\PackageController        → Package Eloquent
 * - Client\CatalogController       → Package Eloquent
 * - Admin\UserController           → User Eloquent
 * - Admin\ClientController         → User Eloquent
 * - Admin\MitraMonitoringController → User Eloquent
 *
 * Endpoint custom (bukan Resource/Eloquent):
 * - Admin\DashboardController      → custom shape
 * - Mitra\DashboardController      → custom shape
 * - Admin\ReportController         → custom shape
 * - Client\ProfileController       → custom shape (pick subset)
 * - NotificationController         → AppNotification Eloquent
 * - Auth\ResetPasswordController   → custom
 * - Auth\ChangePasswordController  → custom
 * - Mitra\OnboardingController     → MitraBankAccount Eloquent
 *
 * Konvensi tipe:
 * - `NonNullable<X['field']>` → relasi yang WAJIB ada di halaman Show
 *   (controller selalu eager-load relasi ini).
 * - Props yang belum dikirim backend (mis. reasonOptions) TIDAK
 *   dideklarasikan. FE harus pakai konstanta lokal dari utils/enums.
 */

import type { PaginationMeta } from './pagination';
import type {
    AppNotification,
    BookingEloquent,
    BookingParticipant,
    BookingResource,
    ETicketInfo,
    MitraBankAccount,
    PackageEloquent,
    PackageFacility,
    PackageDay,
    PaymentEloquent,
    Profile,
    RefundEloquent,
    TripDay,
    TripEloquent,
    User,
    UserMini,
    BankAccount,
} from './models';

// ==========================================
// SHARED HELPERS
// ==========================================

/**
 * Filter standar untuk halaman list.
 * Field yang dipakai bersama di banyak endpoint.
 */
type CommonListFilters = {
    search: string | null;
    status: string | null;
    sort: string;
    direction: 'asc' | 'desc';
};

// ==========================================
// ADMIN — DASHBOARD
// ==========================================

/**
 * `Admin\DashboardController@index`.
 *
 * Custom shape — bukan Resource/Eloquent.
 * `stats` dihitung via agregasi di controller.
 */
export type AdminDashboardProps = {
    stats: {
        users: {
            admin: number;
            mitra: number;
            client: number;
        };
        packages: {
            total: number;
            aktif: number;
        };
        trips: {
            total: number;
            aktif: number;
        };
        bookings: {
            total: number;
        };
        total_revenue: number;
    };
    recent_bookings: Array<{
        id: number;
        kode: string;
        client: string;
        mitra: string;
        paket: string;
        total: number;
        status: string;
        tanggal: string;
    }>;
    monthly_revenue: Array<{
        bulan: string;
        total: number;
    }>;
};

// ==========================================
// ADMIN — BOOKINGS
// ==========================================

/**
 * `Admin\BookingController@index`.
 *
 * Backend kirim paginated Booking Eloquent dengan eager-load:
 * package:id,nama,kode,timezone | client:id,name,email | mitra:id,name.
 *
 * ⚠️ BUKAN BookingResource — karena index pakai
 * `BookingQueryService::paginate()` yang return Eloquent.
 */
export type AdminBookingIndexProps = {
    bookings: PaginationMeta & {
        data: Array<
            BookingEloquent & {
                package: Pick<
                    PackageEloquent,
                    'id' | 'nama' | 'kode' | 'timezone'
                > | null;
                client: Pick<User, 'id' | 'name' | 'email'> | null;
                mitra: Pick<User, 'id' | 'name'> | null;
            }
        >;
    };
    filters: CommonListFilters & {
        package_id: string | null;
        tanggal_dari: string | null;
        tanggal_sampai: string | null;
    };
    statusCounts: Record<string, number>;
};

/**
 * `Admin\BookingController@show`.
 *
 * Backend kirim `BookingResource` — mix camelCase & snake_case.
 * Eager-load: package (partial), client, mitra.bankAccounts,
 * participants, payments.verifier, refunds.
 */
export type AdminBookingShowProps = {
    booking: BookingResource & {
        package: NonNullable<BookingResource['package']>;
        client: NonNullable<BookingResource['client']>;
        participants: NonNullable<BookingResource['participants']>;
        payments: NonNullable<BookingResource['payments']>;
    };
};

// ==========================================
// ADMIN — CLIENTS & MITRAS
// ==========================================

/**
 * `Admin\ClientController@index`.
 *
 * Backend kirim paginated User Eloquent dengan eager-load: profile.
 * (dari `UserRepository::paginateClients()`)
 */
export type AdminClientIndexProps = {
    clients: PaginationMeta & {
        data: Array<
            Pick<
                User,
                'id' | 'name' | 'username' | 'email' | 'created_at'
            > & {
                profile?: Pick<
                    Profile,
                    'nama_penyedia_usaha' | 'logo' | 'kota' | 'provinsi'
                > | null;
            }
        >;
    };
    filters: {
        search: string | null;
        per_page: number;
    };
};

/**
 * `Admin\MitraMonitoringController@index`.
 *
 * Backend kirim paginated User Eloquent dengan eager-load: profile.
 * (dari `UserRepository::paginateMitras()`)
 */
export type AdminMitraIndexProps = {
    mitras: PaginationMeta & {
        data: Array<
            Pick<User, 'id' | 'name' | 'email' | 'created_at'> & {
                profile?: Pick<
                    Profile,
                    'nama_penyedia_usaha' | 'logo' | 'kota' | 'provinsi'
                > | null;
            }
        >;
    };
    filters: {
        search: string | null;
        per_page: number;
    };
};

// ==========================================
// ADMIN — REFUNDS
// ==========================================

/**
 * `Admin\RefundController@index`.
 *
 * Backend kirim paginated Refund Eloquent dengan eager-load:
 * booking:id,kode,user_id,total_harga,status |
 * booking.client:id,name,email |
 * mitra:id,name,email | mitraInitiator:id,name,email.
 *
 * ⚠️ BUKAN RefundResource — index pakai
 * `RefundQueryService::paginateForAdmin()` yang return Eloquent.
 *
 * ⚠️ `reasonOptions` dan `statusOptions` TIDAK dikirim backend.
 * FE pakai konstanta lokal dari `utils/enums.ts`.
 */
export type AdminRefundIndexProps = {
    refunds: PaginationMeta & {
        data: Array<
            RefundEloquent & {
                booking:
                    | (Pick<
                          BookingEloquent,
                          'id' | 'kode' | 'total_harga' | 'status'
                      > & {
                          client: Pick<
                              User,
                              'id' | 'name' | 'email'
                          > | null;
                      })
                    | null;
                mitra: Pick<User, 'id' | 'name' | 'email'> | null;
                mitraInitiator: Pick<
                    User,
                    'id' | 'name' | 'email'
                > | null;
            }
        >;
    };
    filters: {
        search: string | null;
        status: string | null;
    };
    statusCounts: Record<string, number>;
};

// ==========================================
// ADMIN — REPORTS
// ==========================================

/**
 * `Admin\ReportController@index`.
 *
 * Custom shape dari `ReportService::getRevenueData()` +
 * `getMonthlyRevenue()`.
 */
export type AdminReportRevenueProps = {
    data: {
        total_revenue: number;
        transactions: Array<{
            kode: string;
            tanggal: string;
            client: string;
            mitra: string;
            paket: string;
            total: number;
            status: string;
        }>;
    };
    monthly_revenue: Array<{
        bulan: string;
        total: number;
    }>;
    filters: {
        start_date: string;
        end_date: string;
    };
};

/**
 * `Admin\ReportController@mitraPerformance`.
 *
 * Custom shape dari `ReportService::getMitraPerformanceData()`.
 */
export type AdminReportMitraProps = {
    data: {
        total_mitra: number;
        total_trip_completed: number;
        mitras: Array<{
            id: number;
            name: string;
            email: string;
            total_trip: number;
            total_revenue: number;
        }>;
    };
    filters: {
        start_date: string;
        end_date: string;
    };
};

// ==========================================
// ADMIN — TRIPS
// ==========================================

/**
 * `Admin\TripMonitoringController@index`.
 *
 * Backend kirim paginated Trip Eloquent dengan eager-load:
 * package:id,nama,kode,timezone |
 * booking:id,kode,user_id |
 * booking.client:id,name,email |
 * mitra:id,name.
 *
 * ⚠️ BUKAN TripResource — index pakai `TripService::paginate()`
 * yang return Eloquent.
 */
export type AdminTripIndexProps = {
    trips: PaginationMeta & {
        data: Array<
            TripEloquent & {
                package: Pick<
                    PackageEloquent,
                    'id' | 'nama' | 'kode' | 'timezone'
                > | null;
                booking:
                    | (Pick<
                          BookingEloquent,
                          'id' | 'kode' | 'user_id'
                      > & {
                          client: Pick<
                              User,
                              'id' | 'name' | 'email'
                          > | null;
                      })
                    | null;
                mitra: Pick<User, 'id' | 'name'> | null;
            }
        >;
    };
    filters: CommonListFilters & {
        mitra_id: string | null;
        tanggal_dari: string | null;
        tanggal_sampai: string | null;
    };
    statusCounts: Record<string, number>;
};

/**
 * `Admin\TripMonitoringController@show`.
 *
 * Backend kirim Trip Eloquent dengan eager-load lengkap (via
 * `TripService::findDetail()` → `TripRepository::findById()`):
 * package:id,nama,kode,slug,timezone,cover_image |
 * booking:id,kode,user_id,tanggal_keberangkatan,jumlah_peserta,total_harga,status |
 * booking.client:id,name,email,username |
 * mitra:id,name,email,username,timezone |
 * days.trip.
 *
 * `snapshot_package` ADA (kolom DB, Eloquent).
 */
export type AdminTripShowProps = {
    trip: TripEloquent & {
        package: NonNullable<TripEloquent['package']>;
        booking:
            | (Pick<
                  BookingEloquent,
                  | 'id'
                  | 'kode'
                  | 'user_id'
                  | 'tanggal_keberangkatan'
                  | 'jumlah_peserta'
                  | 'total_harga'
                  | 'status'
              > & {
                  client: Pick<
                      User,
                      'id' | 'name' | 'email' | 'username'
                  > | null;
              })
            | null;
        mitra:
            | (Pick<
                  User,
                  'id' | 'name' | 'email' | 'username'
              > & {
                  timezone: string;
              })
            | null;
        days: TripDay[];
    };
};

// ==========================================
// ADMIN — USERS
// ==========================================

/**
 * `Admin\UserController@index`.
 *
 * Backend kirim paginated User Eloquent dengan eager-load: profile.
 * (dari `UserService::paginateByRoles(['admin', 'mitra'])`)
 */
export type AdminUserIndexProps = {
    users: PaginationMeta & {
        data: Array<
            Pick<
                User,
                | 'id'
                | 'name'
                | 'username'
                | 'email'
                | 'role'
                | 'created_at'
            > & {
                role: 'admin' | 'mitra';
                profile?: Pick<
                    Profile,
                    'nama_penyedia_usaha' | 'logo'
                > | null;
            }
        >;
    };
    filters: {
        search: string | null;
        per_page: number;
    };
};

/**
 * `Admin\UserController@edit`.
 *
 * Backend kirim User Eloquent dengan eager-load: profile.
 * (dari `$user->load('profile')`)
 */
export type AdminUserEditProps = {
    user: Pick<
        User,
        'id' | 'name' | 'username' | 'email' | 'role'
    > & {
        role: 'admin' | 'mitra';
        profile?: Pick<
            Profile,
            | 'nama_penyedia_usaha'
            | 'nomor_identitas'
            | 'alamat'
            | 'telepon'
            | 'provinsi'
            | 'kota'
            | 'kode_pos'
            | 'deskripsi'
        > | null;
    };
};

// ==========================================
// MITRA — DASHBOARD
// ==========================================

/**
 * `Mitra\DashboardController@index`.
 *
 * Custom shape — dihitung via agregasi di controller.
 */
export type MitraDashboardProps = {
    stats: {
        packages: {
            draft: number;
            aktif: number;
            arsip: number;
            total: number;
        };
        trips: {
            scheduled: number;
            active: number;
            completed: number;
            total: number;
        };
        bookings: {
            total: number;
            pending: number;
            confirmed: number;
        };
        total_revenue: number;
    };
    recent_bookings: Array<{
        id: number;
        kode: string;
        client: string;
        paket: string;
        total: number;
        status: string;
        tanggal: string;
    }>;
};

// ==========================================
// MITRA — BOOKINGS
// ==========================================

/**
 * `Mitra\BookingController@index`.
 *
 * Backend kirim paginated Booking Eloquent dengan eager-load:
 * package:id,nama,kode,timezone | client:id,name,email | mitra:id,name.
 *
 * Filter `mitra_id` di-scope di backend (tidak dikirim ke FE).
 * `statusCounts` dikirim untuk badge count di tab status.
 */
export type MitraBookingIndexProps = {
    bookings: PaginationMeta & {
        data: Array<
            BookingEloquent & {
                package: Pick<
                    PackageEloquent,
                    'id' | 'nama' | 'kode' | 'timezone'
                > | null;
                client: Pick<User, 'id' | 'name' | 'email'> | null;
                mitra: Pick<User, 'id' | 'name'> | null;
            }
        >;
    };
    filters: {
        search: string | null;
        status: string | null;
    };
    statusCounts: Record<string, number>;
};

/**
 * `Mitra\BookingController@show`.
 *
 * Backend kirim `BookingResource` (mix camelCase & snake_case).
 * Eager-load: package (partial), client, mitra.bankAccounts,
 * participants, payments.verifier, refunds.
 *
 * ⚠️ Breaking change dari versi lama:
 * - `payment_proof_path` → `has_payment_proof` (boolean)
 * - `participants` → `BookingParticipantResource[]`
 * - `payments` → `PaymentResource[]`
 * - `refunds` → `RefundResource[]`
 */
export type MitraBookingShowProps = {
    booking: BookingResource & {
        package: NonNullable<BookingResource['package']>;
        client: NonNullable<BookingResource['client']>;
        participants: NonNullable<BookingResource['participants']>;
        payments: NonNullable<BookingResource['payments']>;
        mitra: NonNullable<BookingResource['mitra']>;
        refunds: NonNullable<BookingResource['refunds']>;
    };
};

/**
 * `Mitra\ManualBookingController@create`.
 *
 * Backend kirim Package Eloquent (dari route model binding).
 * (dari `$package` langsung, tanpa eager-load relasi)
 */
export type MitraBookingCreateManualProps = {
    package: Pick<
        PackageEloquent,
        | 'id'
        | 'kode'
        | 'nama'
        | 'slug'
        | 'deskripsi'
        | 'kategori'
        | 'durasi_hari'
        | 'durasi_malam'
        | 'harga_per_orang'
        | 'harga_per_rombongan'
        | 'max_peserta'
        | 'timezone'
    > & {
        facilities: PackageFacility[];
    };
};

// ==========================================
// MITRA — PACKAGES
// ==========================================

/**
 * `Mitra\PackageController@index`.
 *
 * Backend kirim paginated Package Eloquent dengan eager-load:
 * mitra:id,name,email.
 * (dari `PackageService::paginate()` → `PackageRepository::paginate()`)
 */
export type MitraPackageIndexProps = {
    packages: PaginationMeta & {
        data: Array<
            PackageEloquent & {
                mitra: Pick<
                    User,
                    'id' | 'name' | 'email'
                > | null;
            }
        >;
    };
    filters: CommonListFilters & {
        mitra_id: number;
    };
    statusCounts: Record<string, number>;
};

/**
 * `Mitra\PackageController@show`.
 *
 * Backend kirim Package Eloquent dengan eager-load: days, facilities.
 * (dari `$package->load(['days', 'facilities'])`)
 */
export type MitraPackageShowProps = {
    package: PackageEloquent & {
        days: PackageDay[];
        facilities: PackageFacility[];
    };
};

/**
 * `Mitra\PackageController@edit`.
 *
 * Backend kirim Package Eloquent dengan eager-load: days, facilities.
 * (dari `$package->load(['days', 'facilities'])`)
 */
export type MitraPackageEditProps = {
    package: PackageEloquent & {
        days: PackageDay[];
        facilities: PackageFacility[];
    };
};

// ==========================================
// MITRA — TRIPS
// ==========================================

/**
 * `Mitra\TripController@index`.
 *
 * Backend kirim paginated Trip Eloquent dengan eager-load:
 * package:id,nama,kode,timezone |
 * booking:id,kode,user_id |
 * booking.client:id,name,email |
 * mitra:id,name.
 * (dari `TripService::paginateForMitra()`)
 */
export type MitraTripIndexProps = {
    trips: PaginationMeta & {
        data: Array<
            TripEloquent & {
                package: Pick<
                    PackageEloquent,
                    'id' | 'nama' | 'kode' | 'timezone'
                > | null;
                booking:
                    | (Pick<
                          BookingEloquent,
                          'id' | 'kode' | 'user_id'
                      > & {
                          client: Pick<
                              User,
                              'id' | 'name' | 'email'
                          > | null;
                      })
                    | null;
                mitra: Pick<User, 'id' | 'name'> | null;
            }
        >;
    };
    filters: CommonListFilters;
    statusCounts: Record<string, number>;
};

/**
 * `Mitra\TripController@show`.
 *
 * Backend kirim Trip Eloquent dengan eager-load:
 * package:id,nama,kode,slug,timezone,cover_image |
 * booking.client:id,name,email |
 * days.trip.
 * (dari `$trip->load([...])`)
 */
export type MitraTripShowProps = {
    trip: TripEloquent & {
        package: Pick<
            PackageEloquent,
            'id' | 'nama' | 'kode' | 'slug' | 'timezone' | 'cover_image'
        > | null;
        booking:
            | (Pick<BookingEloquent, 'id' | 'kode'> & {
                  client: Pick<
                      User,
                      'id' | 'name' | 'email'
                  > | null;
              })
            | null;
        mitra: Pick<
            User,
            'id' | 'name' | 'email' | 'username'
        > | null;
        days: TripDay[];
    };
};

// ==========================================
// MITRA — REFUNDS
// ==========================================

/**
 * `Mitra\RefundController@index`.
 *
 * Backend kirim paginated Refund Eloquent dengan eager-load:
 * booking:id,kode,total_harga,status |
 * booking.client:id,name,email.
 *
 * Filter: search, status, per_page (via `ListRefundRequest`).
 * Status counts: `RefundQueryService::countByStatusForMitra($mitra)`.
 */
export type MitraRefundIndexProps = {
    refunds: PaginationMeta & {
        data: Array<
            RefundEloquent & {
                booking:
                    | (Pick<
                          BookingEloquent,
                          'id' | 'kode' | 'total_harga' | 'status'
                      > & {
                          client: Pick<
                              User,
                              'id' | 'name' | 'email'
                          > | null;
                      })
                    | null;
            }
        >;
    };
    filters: {
        search: string | null;
        status: string | null;
    };
    statusCounts: Record<string, number>;
};

// ==========================================
// MITRA — ONBOARDING
// ==========================================

/**
 * `Mitra\OnboardingController@index`.
 *
 * Backend kirim Collection<MitraBankAccount> Eloquent.
 * (dari `$mitra->bankAccounts`)
 */
export type MitraOnboardingProps = {
    bankAccounts: MitraBankAccount[];
};

// ==========================================
// MITRA — PROFILE
// ==========================================

/**
 * `Mitra\ProfileController@edit`.
 *
 * Backend kirim `$user->profile` (Eloquent Profile atau null).
 */
// resources/js/types/props.ts
export type MitraProfileProps = {
    profile: Profile | null;
    bankAccounts?: BankAccount[]; 
};

// ==========================================
// CLIENT — BOOKINGS
// ==========================================

/**
 * `Client\BookingController@index`.
 *
 * Backend kirim paginated Booking Eloquent dengan eager-load:
 * package:id,nama,kode,timezone |
 * client:id,name,email |
 * mitra:id,name.
 * (dari `BookingQueryService::paginateForClient()`)
 */
export type ClientBookingIndexProps = {
    bookings: PaginationMeta & {
        data: Array<
            BookingEloquent & {
                package: Pick<
                    PackageEloquent,
                    'id' | 'nama' | 'kode' | 'timezone'
                > | null;
                client: Pick<User, 'id' | 'name' | 'email'> | null;
                mitra: Pick<User, 'id' | 'name'> | null;
            }
        >;
    };
    filters: CommonListFilters;
    statusCounts: Record<string, number>;
};

/**
 * `Client\BookingController@show`.
 *
 * Backend kirim `BookingResource` — mix camelCase & snake_case.
 * Eager-load: package (partial, ID 10 field), participants, payments,
 * mitra.bankAccounts, refunds (limit 1).
 *
 * Tambahan dari controller:
 * - `eticket` → ETicketInfo dengan `download_url`
 * - `refunds` → array terpisah (bukan dari BookingResource.refunds)
 */
export type ClientBookingShowProps = {
    booking: BookingResource & {
        package: NonNullable<BookingResource['package']>;
        participants: NonNullable<BookingResource['participants']>;
        payments: NonNullable<BookingResource['payments']>;
        mitra: NonNullable<BookingResource['mitra']>;
        refunds: NonNullable<BookingResource['refunds']>;
    };
    eticket: ETicketInfo | null;
    refunds: Array<{
        id: number;
        amount: string;
        status: string;
        created_at: string | null;
    }>;
};

/**
 * `Client\BookingController@create`.
 *
 * Backend kirim Package Eloquent dengan eager-load: facilities.
 * (dari `$package->load(['facilities'])`)
 */
export type ClientBookingCreateProps = {
    package: Pick<
        PackageEloquent,
        | 'id'
        | 'kode'
        | 'nama'
        | 'slug'
        | 'deskripsi'
        | 'kategori'
        | 'durasi_hari'
        | 'durasi_malam'
        | 'harga_per_orang'
        | 'harga_per_rombongan'
        | 'max_peserta'
        | 'timezone'
    > & {
        facilities: PackageFacility[];
    };
};

// ==========================================
// CLIENT — CATALOG
// ==========================================

/**
 * `Client\CatalogController@index`.
 *
 * Backend kirim paginated Package Eloquent dengan eager-load:
 * mitra:id,name,email.
 * (dari `PackageService::paginate()`)
 *
 * ⚠️ `days` dan `facilities` TIDAK di-eager-load di index.
 */
export type ClientCatalogIndexProps = {
    packages: PaginationMeta & {
        data: Array<
            PackageEloquent & {
                mitra: Pick<
                    User,
                    'id' | 'name' | 'email'
                > | null;
            }
        >;
    };
    filters: CommonListFilters & {
        kategori: string | null;
    };
};

/**
 * `Client\CatalogController@show`.
 *
 * Backend kirim Package Eloquent dengan eager-load:
 * mitra:id,name,email | days | facilities.
 * (dari `$package->load(['mitra:id,name,email', 'days', 'facilities'])`)
 */
export type ClientCatalogShowProps = {
    package: PackageEloquent & {
        mitra: (Pick<User, 'id' | 'name' | 'email'> & {
            profile?: Profile | null;
        }) | null;
        days: PackageDay[];
        facilities: PackageFacility[];
    };
};

// ==========================================
// CLIENT — PROFILE
// ==========================================

/**
 * `Client\ProfileController@edit`.
 *
 * Custom shape dari `ClientProfileService::getProfileData()`.
 */
export type ClientProfileProps = {
    user: {
        id: number;
        name: string;
        username: string | null;
        email: string;
        role: string;
    };
};

// ==========================================
// AUTH
// ==========================================

/**
 * `Auth\PasswordResetController@edit`.
 *
 * `email` bisa null kalau user akses direct tanpa query string.
 */
export type AuthResetPasswordProps = {
    token: string;
    email: string | null;
};

/**
 * `Auth\ChangePasswordController@edit`.
 */
export type AuthChangePasswordProps = {
    mustChangePassword: boolean;
};

// ==========================================
// NOTIFICATIONS
// ==========================================

/**
 * `NotificationController@index`.
 *
 * Backend kirim paginated AppNotification Eloquent.
 * `AppNotification` sudah di-type di models.ts.
 */
export type NotificationsIndexProps = {
    notifications: PaginationMeta & {
        data: Array<
            Pick<
                AppNotification,
                | 'id'
                | 'tipe'
                | 'judul'
                | 'pesan'
                | 'data'
                | 'read_at'
                | 'created_at'
            >
        >;
    };
    unreadCount: number;
};

// ==========================================
// BARREL (opsional, kalau mau import langsung)
// ==========================================

/**
 * Type map per halaman — untuk kemudahan di file page.
 *
 * @example
 *   import type { PageProps } from '@/types/props';
 *   let { booking, eticket, refunds }: PageProps['ClientBookingShow'] = $props();
 */
export type PageProps = {
    AdminDashboard: AdminDashboardProps;
    AdminBookingIndex: AdminBookingIndexProps;
    AdminBookingShow: AdminBookingShowProps;
    AdminClientIndex: AdminClientIndexProps;
    AdminMitraIndex: AdminMitraIndexProps;
    AdminRefundIndex: AdminRefundIndexProps;
    AdminReportRevenue: AdminReportRevenueProps;
    AdminReportMitra: AdminReportMitraProps;
    AdminTripIndex: AdminTripIndexProps;
    AdminTripShow: AdminTripShowProps;
    AdminUserIndex: AdminUserIndexProps;
    AdminUserEdit: AdminUserEditProps;
    MitraDashboard: MitraDashboardProps;
    MitraBookingIndex: MitraBookingIndexProps;
    MitraBookingShow: MitraBookingShowProps;
    MitraBookingCreateManual: MitraBookingCreateManualProps;
    MitraPackageIndex: MitraPackageIndexProps;
    MitraPackageShow: MitraPackageShowProps;
    MitraPackageEdit: MitraPackageEditProps;
    MitraTripIndex: MitraTripIndexProps;
    MitraTripShow: MitraTripShowProps;
    MitraRefundIndex: MitraRefundIndexProps;
    MitraOnboarding: MitraOnboardingProps;
    MitraProfile: MitraProfileProps;
    ClientBookingIndex: ClientBookingIndexProps;
    ClientBookingShow: ClientBookingShowProps;
    ClientBookingCreate: ClientBookingCreateProps;
    ClientCatalogIndex: ClientCatalogIndexProps;
    ClientCatalogShow: ClientCatalogShowProps;
    ClientProfile: ClientProfileProps;
    AuthResetPassword: AuthResetPasswordProps;
    AuthChangePassword: AuthChangePasswordProps;
    NotificationsIndex: NotificationsIndexProps;
};