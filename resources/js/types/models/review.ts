/**
 * Types untuk Review — mirror dari backend:
 * - `app/Models/Review`
 * - `app/Http/Resources/ReviewResource`
 * - `ReviewRepositoryInterface::statistikForPackage()`
 */

import type { PaginationMeta } from '../pagination';

/**
 * Satu ulasan dari ReviewResource.
 *
 * `client.nama` sudah dipotong jadi nama depan di server (AC-10).
 */
export type Review = {
    id: number;
    rating: number; // 1..5
    komentar: string;
    created_at: string; // ISO 8601 (UTC)
    client?: { nama: string } | null;
};

export type ReviewStats = {
    rata_rata: number; // 0..5, 0 kalau belum ada review
    jumlah: number;
};

/** Payload `ReviewResource::collection(...)->response()->getData(true)`. */
export type ReviewPaginated = PaginationMeta & {
    data: Review[];
};
