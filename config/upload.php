<?php

return [
    /*
     * Disk untuk file privat (bukti transfer & bukti refund).
     * Produksi memakai R2; testing/local bisa memakai disk lokal agar
     * tidak membutuhkan kredensial cloud.
     */
    'proof_disk' => env('UPLOAD_PROOF_DISK', 'r2_private'),

    /*
     * Disk untuk file publik (cover paket & logo mitra).
     */
    'public_disk' => env('UPLOAD_PUBLIC_DISK', 'r2_public'),
];
