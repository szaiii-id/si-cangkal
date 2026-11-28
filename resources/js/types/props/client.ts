/**
 * Props halaman CLIENT.
 *
 * Semua mirror dari `App\Http\Controllers\Client\*` di backend.
 */

import type { PaginationMeta } from '../pagination';
import type {
    BookingEloquent,
    BookingResource,
    ETicketInfo,
    PackageEloquent,
    PackageFacility,
    PackageDay,
    Profile,
    Review,
    ReviewPaginated,
    ReviewStats,
    User,
} from '../models';
import type { CommonListFilters } from './index';

// ==========================================
// BOOKINGS
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
    /** Satu sumber aturan dengan `ReviewService::canReview()`. */
    canReview?: boolean;
    /** Review milik booking ini, atau null kalau belum ada. */
    review?: Review | null;
};

/**
 * `Client\ReviewController@create`.
 *
 * Prop booking sengaja diperkecil: hanya id, kode, dan nama paket
 * yang dibutuhkan form review.
 */
export type ClientReviewCreateProps = {
    booking: {
        id: string;
        kode: string;
        paket: string | null;
    };
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
// CATALOG
// ==========================================

/**
 * `Client\CatalogController@index`.
 *
 * Backend kirim paginated Package Eloquent dengan eager-load:
 * mitra:id,name,email.
 * (dari `PackageService::paginate()`)
 *
 *   `days` dan `facilities` TIDAK di-eager-load di index.
 */
export type ClientCatalogIndexProps = {
    packages: PaginationMeta & {
        data: Array<
            PackageEloquent & {
                mitra: Pick<User, 'id' | 'name' | 'email'> | null;
            }
        >;
    };
    ratings: Record<string, ReviewStats>;
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
        mitra:
            | (Pick<User, 'id' | 'name' | 'email'> & {
                  profile?: Profile | null;
              })
            | null;
        days: PackageDay[];
        facilities: PackageFacility[];
    };
    reviewStats?: ReviewStats;
    reviews?: ReviewPaginated;
};

// ==========================================
// PROFILE
// ==========================================

/**
 * `Client\ProfileController@edit`.
 *
 * Custom shape dari `ClientProfileService::getProfileData()`.
 */
export type ClientProfileProps = {
    user: {
        id: string;
        name: string;
        username: string | null;
        email: string;
        role: string;
    };
};
