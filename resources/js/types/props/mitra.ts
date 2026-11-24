/**
 * Props halaman MITRA.
 *
 * Semua mirror dari `App\Http\Controllers\Mitra\*` di backend.
 */

import type { PaginationMeta } from '../pagination';
import type {
    BankAccount,
    BookingEloquent,
    BookingResource,
    MitraBankAccount,
    PackageEloquent,
    PackageFacility,
    PackageDay,
    Profile,
    RefundEloquent,
    Review,
    TripDay,
    TripEloquent,
    User,
} from '../models';
import type { CommonListFilters } from './index';

// ==========================================
// DASHBOARD
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
// BOOKINGS
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
 *   Breaking change dari versi lama:
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
    /** Review client untuk booking ini, atau null. */
    review?: Review | null;
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
// PACKAGES
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
                mitra: Pick<User, 'id' | 'name' | 'email'> | null;
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
// TRIPS
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
                    | (Pick<BookingEloquent, 'id' | 'kode' | 'user_id'> & {
                          client: Pick<User, 'id' | 'name' | 'email'> | null;
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
                  client: Pick<User, 'id' | 'name' | 'email'> | null;
              })
            | null;
        mitra: Pick<User, 'id' | 'name' | 'email' | 'username'> | null;
        days: TripDay[];
    };
};

// ==========================================
// REFUNDS
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
                          client: Pick<User, 'id' | 'name' | 'email'> | null;
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
// ONBOARDING
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
// PROFILE
// ==========================================

/**
 * `Mitra\ProfileController@edit`.
 *
 * Backend kirim `$user->profile` (Eloquent Profile atau null).
 */
export type MitraProfileProps = {
    profile: Profile | null;
    bankAccounts?: BankAccount[];
};
