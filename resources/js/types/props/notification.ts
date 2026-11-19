/**
 * Props halaman NOTIFICATIONS.
 *
 * Mirror dari `App\Http\Controllers\NotificationController`.
 */

import type { PaginationMeta } from '../pagination';
import type { AppNotification } from '../models';

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