/**
 * Props per halaman Inertia — barrel export.
 *
 * Mirror dari controller Inertia::render() di backend v3.0.
 *
 *   KONTRAK AKTUAL BACKEND (bukan idealisme):
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

// ==========================================
// SHARED HELPERS
// ==========================================

/**
 * Filter standar untuk halaman list.
 * Field yang dipakai bersama di banyak endpoint.
 */
export type CommonListFilters = {
    search: string | null;
    status: string | null;
    sort: string;
    direction: 'asc' | 'desc';
};

// ==========================================
// RE-EXPORT PER DOMAIN
// ==========================================

export type {
    AdminDashboardProps,
    AdminBookingIndexProps,
    AdminBookingShowProps,
    AdminClientIndexProps,
    AdminMitraIndexProps,
    AdminRefundIndexProps,
    AdminReportRevenueProps,
    AdminReportMitraProps,
    AdminTripIndexProps,
    AdminTripShowProps,
    AdminUserIndexProps,
    AdminUserEditProps,
} from './admin';

export type {
    MitraDashboardProps,
    MitraBookingIndexProps,
    MitraBookingShowProps,
    MitraBookingCreateManualProps,
    MitraPackageIndexProps,
    MitraPackageShowProps,
    MitraPackageEditProps,
    MitraTripIndexProps,
    MitraTripShowProps,
    MitraRefundIndexProps,
    MitraOnboardingProps,
    MitraProfileProps,
} from './mitra';

export type {
    ClientBookingIndexProps,
    ClientBookingShowProps,
    ClientBookingCreateProps,
    ClientCatalogIndexProps,
    ClientCatalogShowProps,
    ClientProfileProps,
    ClientReviewCreateProps,
} from './client';

export type {
    AuthResetPasswordProps,
    AuthChangePasswordProps,
} from './auth';

export type { NotificationsIndexProps } from './notification';

// ==========================================
// PAGE PROPS MAP
// ==========================================

import type {
    AdminDashboardProps,
    AdminBookingIndexProps,
    AdminBookingShowProps,
    AdminClientIndexProps,
    AdminMitraIndexProps,
    AdminRefundIndexProps,
    AdminReportRevenueProps,
    AdminReportMitraProps,
    AdminTripIndexProps,
    AdminTripShowProps,
    AdminUserIndexProps,
    AdminUserEditProps,
} from './admin';

import type {
    MitraDashboardProps,
    MitraBookingIndexProps,
    MitraBookingShowProps,
    MitraBookingCreateManualProps,
    MitraPackageIndexProps,
    MitraPackageShowProps,
    MitraPackageEditProps,
    MitraTripIndexProps,
    MitraTripShowProps,
    MitraRefundIndexProps,
    MitraOnboardingProps,
    MitraProfileProps,
} from './mitra';

import type {
    ClientBookingIndexProps,
    ClientBookingShowProps,
    ClientBookingCreateProps,
    ClientCatalogIndexProps,
    ClientCatalogShowProps,
    ClientProfileProps,
    ClientReviewCreateProps,
} from './client';

import type {
    AuthResetPasswordProps,
    AuthChangePasswordProps,
} from './auth';

import type { NotificationsIndexProps } from './notification';

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
    ClientReviewCreate: ClientReviewCreateProps;
    AuthResetPassword: AuthResetPasswordProps;
    AuthChangePassword: AuthChangePasswordProps;
    NotificationsIndex: NotificationsIndexProps;
};