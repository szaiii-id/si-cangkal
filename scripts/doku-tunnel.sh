#!/usr/bin/env bash
set -euo pipefail

# ---------------------------------------------------------------------------
# Tunnel sandbox DOKU tanpa ngrok.
#
# DOKU melarang Notification URL berdomain ngrok dan tidak dapat menjangkau
# localhost. localhost.run adalah alternatif yang disarankan dokumentasi DOKU.
#
# Prasyarat:
#   - `php artisan serve` berjalan di port 8000.
#   - Salin URL https://<id>.lhr.life yang muncul ke DOKU_NOTIFICATION_URL
#     di .env dengan akhiran /payments/doku/webhook.
#
# Catatan:
#   - URL gratis bersifat sementara dan berubah setiap kali tunnel dibuka.
#   - Gunakan kredensial SANDBOX saja; jangan pernah pakai secret produksi.
# ---------------------------------------------------------------------------

ssh -R 80:localhost:8000 localhost.run
