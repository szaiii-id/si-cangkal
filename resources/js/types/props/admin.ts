/**
 * Props halaman ADMIN.
 *
 * Semua mirror dari `App\Http\Controllers\Admin\*` di backend.
 */

import type { PaginationMeta } from '../pagination';
import type {
    BookingEloquent,
    BookingResource,
    PackageEloquent,
    Profile,
    RefundResource,
    TripDay,
    TripEloquent,
    User,
} from '../models';
import type { CommonListFilters } from './index';

// ==========================================
// DASHBOARD
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
// BOOKINGS
// ==========================================

/**
 * `Admin\BookingController@index`.
 *
 * Backend kirim paginated Booking Eloquent dengan eager-load:
 * package:id,nama,kode,timezone | client:id,name,email | mitra:id,name.
 *
 *   BUKAN BookingResource — karena index pakai
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
        is_today: boolean | string | number;
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
// CLIENTS & MITRAS
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
            Pick<User, 'id' | 'name' | 'username' | 'email' | 'created_at'> & {
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
// REFUNDS
// ==========================================

/**
 * `Admin\RefundController@index`.
 *
 * Backend kirim paginated RefundResource via `RefundResource::collection()`.
 * Resource ini menyertakan field turunan (amountRupiah, statusLabel,
 * reasonLabel, isFinal, refundFeeRupiah) yang dihitung di backend.
 */
export type AdminRefundIndexProps = {
    refunds: PaginationMeta & {
        data: Array<RefundResource>;
    };
    filters: {
        search: string | null;
        status: string | null;
    };
    statusCounts: Record<string, number>;
};

// ==========================================
// REPORTS
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
// TRIPS
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
 *   BUKAN TripResource — index pakai `TripService::paginate()`
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
                    | (Pick<BookingEloquent, 'id' | 'kode' | 'user_id'> & {
                          client: Pick<User, 'id' | 'name' | 'email'> | null;
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
        package_id: string | number | null;
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
            | (Pick<User, 'id' | 'name' | 'email' | 'username'> & {
                  timezone: string;
              })
            | null;
        days: TripDay[];
    };
};

// ==========================================
// USERS
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
                'id' | 'name' | 'username' | 'email' | 'role' | 'created_at'
            > & {
                role: 'admin' | 'mitra';
                profile?: Pick<Profile, 'nama_penyedia_usaha' | 'logo'> | null;
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
    user: Pick<User, 'id' | 'name' | 'username' | 'email' | 'role'> & {
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
