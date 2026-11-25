/**
 * Audit dependency dengan pengecualian yang terdokumentasi dan berkedaluwarsa.
 *
 * `bun audit` belum punya flag allowlist. Script ini menjalankan audit JSON,
 * membuang advisory yang sudah disetujui tim, lalu gagal jika masih ada
 * temuan lain di atas ambang `high`.
 *
 * Pengecualian saat ini: GHSA-m9gg-hp2v-232j / GHSA-f596-whhp-79r4
 * (@grpc/grpc-js < 1.13.6). Paket ini ditarik transitif oleh
 * @firebase/firestore@4.x yang memaku `~1.9.0`; tidak bisa dinaikkan tanpa
 * upgrade Firebase major. Tinjau lagi sebelum 2026-12-31.
 */
const EXPIRES_AT = '2026-12-31';
const ALLOWED_IDS = new Set([1240623, 1240625]);

type Advisory = {
    id: number;
    url: string;
    title: string;
    severity: string;
};

const result = Bun.spawn(['bun', 'audit', '--json'], {
    stdout: 'pipe',
    stderr: 'pipe',
});

const raw = await new Response(result.stdout).text();

let audit: Record<string, Advisory[]>;

try {
    audit = JSON.parse(raw) as Record<string, Advisory[]>;
} catch {
    console.error('Gagal mem-parsing hasil `bun audit --json`.');
    console.error(raw);
    process.exit(1);
}

const entries = Object.entries(audit).flatMap(([packageName, advisories]) =>
    (advisories ?? []).map((advisory) => ({ packageName, advisory })),
);

const allowed = entries.filter(({ advisory }) => ALLOWED_IDS.has(advisory.id));
const blocking = entries.filter(
    ({ advisory }) =>
        !ALLOWED_IDS.has(advisory.id) &&
        ['high', 'critical'].includes(advisory.severity.toLowerCase()),
);

if (blocking.length > 0) {
    for (const { packageName, advisory } of blocking) {
        console.error(
            `${advisory.severity.toUpperCase()} ${packageName}: ${advisory.title} (${advisory.url})`,
        );
    }

    process.exit(1);
}

for (const { packageName, advisory } of allowed) {
    console.log(
        `Ditunda ${advisory.id} (${packageName}): ${advisory.title} — tinjau sebelum ${EXPIRES_AT}`,
    );
}

console.log(
    `bun audit: ${entries.length} advisory, ${blocking.length} blocking.`,
);
