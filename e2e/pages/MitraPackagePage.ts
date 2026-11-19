import { expect, type Locator, type Page } from '@playwright/test';

export interface CreatePackageStep1Input {
    nama: string;
    deskripsi?: string;
    kategori?: 'wisata_alam' | 'budaya' | 'kuliner' | 'petualangan' | 'lainnya';
    timezone?: 'Asia/Jakarta' | 'Asia/Makassar' | 'Asia/Jayapura';
}

export interface CreatePackageStep2Input {
    durasiHari: number;
    durasiMalam: number;
    useRombongan?: boolean;
    hargaRaw: string;
    expectedFormattedHarga: string;
    maxPeserta?: number;
}

export interface ItineraryDayInput {
    judul: string;
    jamMulai?: string;
    jamSelesai?: string;
    lokasi?: string;
    deskripsi?: string;
}

export interface FacilityInput {
    tipe: 'include' | 'exclude';
    nama: string;
}

export class MitraPackagePage {
    readonly page: Page;

    // ==========================================
    // NOTIFICATION PERMISSION MODAL
    // ==========================================
    readonly notificationDialog: Locator;
    readonly dismissNotificationButton: Locator;

    // ==========================================
    // CREATE WIZARD LOCATORS (Create.svelte)
    // ==========================================
    readonly namaInput: Locator;
    readonly deskripsiInput: Locator;
    readonly kategoriSelect: Locator;
    readonly timezoneSelect: Locator;

    readonly durasiHariInput: Locator;
    readonly durasiMalamInput: Locator;
    readonly useRombonganCheckbox: Locator;
    readonly hargaPerOrangInput: Locator;
    readonly hargaRombonganInput: Locator;
    readonly maxPesertaInput: Locator;

    readonly nextStepButton: Locator;
    readonly prevStepButton: Locator;
    readonly submitCreateButton: Locator;

    // ==========================================
    // DAYS EDITOR LOCATORS (DaysEditor.svelte)
    // ==========================================
    readonly dayJudulInput: Locator;
    readonly dayJamMulaiInput: Locator;
    readonly dayJamSelesaiInput: Locator;
    readonly dayLokasiInput: Locator;
    readonly dayDeskripsiInput: Locator;
    readonly addDayButton: Locator;

    // ==========================================
    // FACILITIES EDITOR LOCATORS (FacilitiesEditor.svelte)
    // ==========================================
    readonly facilityTipeSelect: Locator;
    readonly facilityNamaInput: Locator;
    readonly addFacilityButton: Locator;

    // ==========================================
    // PACKAGE ACTIONS LOCATORS (PackageActions.svelte)
    // ==========================================
    readonly editPackageLink: Locator;
    readonly manualBookingLink: Locator;
    readonly continueDraftLink: Locator;
    readonly archivePackageButton: Locator;
    readonly restorePackageButton: Locator;
    readonly deletePackageButton: Locator;

    // ==========================================
    // CONFIRM MODAL LOCATORS (ConfirmModal.svelte)
    // ==========================================
    readonly confirmDialog: Locator;
    readonly confirmDialogTitle: Locator;
    readonly confirmReasonTextarea: Locator;
    readonly confirmCancelButton: Locator;

    constructor(page: Page) {
        this.page = page;

        // NotificationPermissionModal.svelte
        this.notificationDialog = page
            .getByRole('dialog')
            .filter({ has: page.locator('#notif-title') });
        this.dismissNotificationButton = this.notificationDialog.getByRole('button', {
            name: 'Nanti Saja',
        });

        // Step 1
        this.namaInput = page.locator('#nama');
        this.deskripsiInput = page.locator('#deskripsi');
        this.kategoriSelect = page.locator('#kategori');
        this.timezoneSelect = page.locator('#timezone');

        // Step 2 (gunakan role 'textbox' agar tidak bentrok dengan checkbox Harga Rombongan)
        this.durasiHariInput = page.locator('#durasi_hari');
        this.durasiMalamInput = page.locator('#durasi_malam');
        this.useRombonganCheckbox = page.getByRole('checkbox', { name: /Gunakan Harga Rombongan/i });
        this.hargaPerOrangInput = page.getByRole('textbox', { name: /Harga per Orang/i });
        this.hargaRombonganInput = page.getByRole('textbox', { name: 'Harga Rombongan' });
        this.maxPesertaInput = page.locator('#max_peserta');

        // Wizard Buttons
        this.nextStepButton = page.getByRole('button', { name: 'Lanjut', exact: true });
        this.prevStepButton = page.getByRole('button', { name: 'Kembali', exact: true });
        this.submitCreateButton = page.getByRole('button', { name: /Simpan & Lanjut Isi Kegiatan/i });

        // DaysEditor.svelte
        this.dayJudulInput = page.locator('#newDay-judul');
        this.dayJamMulaiInput = page.locator('#newDay-jam-mulai');
        this.dayJamSelesaiInput = page.locator('#newDay-jam-selesai');
        this.dayLokasiInput = page.locator('#newDay-lokasi');
        this.dayDeskripsiInput = page.locator('#newDay-deskripsi');
        this.addDayButton = page.getByRole('button', { name: '+ Tambahkan ke Daftar' });

        // FacilitiesEditor.svelte
        this.facilityTipeSelect = page.locator('#newFacility-tipe');
        this.facilityNamaInput = page.locator('#newFacility-nama');
        this.addFacilityButton = page.getByRole('button', { name: '+ Tambah', exact: true });

        // PackageActions.svelte
        this.editPackageLink = page.getByRole('link', { name: 'Edit paket' });
        this.manualBookingLink = page.getByRole('link', { name: 'Buat pesanan manual' });
        this.continueDraftLink = page.getByRole('link', { name: 'Lanjutkan pembuatan paket' });
        this.archivePackageButton = page.getByRole('button', { name: 'Arsipkan paket' });
        this.restorePackageButton = page.getByRole('button', { name: 'Aktifkan kembali paket' });
        this.deletePackageButton = page.getByRole('button', { name: 'Hapus paket' });

        // ConfirmModal.svelte (di-filter dengan #confirm-modal-title agar tidak bentrok dengan notif modal)
        this.confirmDialog = page
            .getByRole('dialog')
            .filter({ has: page.locator('#confirm-modal-title') });
        this.confirmDialogTitle = this.confirmDialog.locator('#confirm-modal-title');
        this.confirmReasonTextarea = this.confirmDialog.locator('#confirm-reason');
        this.confirmCancelButton = this.confirmDialog.getByRole('button', { name: 'Batal' });
    }

    // ==========================================
    // AUTH & GLOBAL OVERLAY HANDLER
    // ==========================================
    async registerNotificationModalHandler(): Promise<void> {
        await this.page.addLocatorHandler(this.notificationDialog, async () => {
            await this.dismissNotificationButton.click();
            await expect(this.notificationDialog).toBeHidden();
        });
    }

    async loginAsSeededMitra(): Promise<void> {
        await this.registerNotificationModalHandler();

        await this.page.goto('/login');
        const identifierInput = this.page
            .locator('form input:not([type="hidden"]):not([type="checkbox"]):not([type="password"])')
            .first();
        const passwordInput = this.page.locator('form input[type="password"]').first();

        await identifierInput.fill('mitra@test.com');
        await passwordInput.fill('password');
        await this.page.locator('form button[type="submit"]').click();
        await this.page.waitForURL((url) => !url.pathname.includes('/login'));
    }

    async gotoIndex(): Promise<void> {
        await this.page.goto('/mitra/packages');
    }

    async gotoCreate(): Promise<void> {
        await this.page.goto('/mitra/packages/create');
    }

    // ==========================================
    // CREATE WIZARD ACTIONS
    // ==========================================
    async fillStep1(data: CreatePackageStep1Input): Promise<void> {
        await this.namaInput.fill(data.nama);
        if (data.deskripsi !== undefined) {
            await this.deskripsiInput.fill(data.deskripsi);
        }
        if (data.kategori) {
            await this.kategoriSelect.selectOption(data.kategori);
        }
        if (data.timezone) {
            await this.timezoneSelect.selectOption(data.timezone);
        }
    }

    async fillStep2(data: CreatePackageStep2Input): Promise<void> {
        await this.durasiHariInput.fill(String(data.durasiHari));
        await this.durasiMalamInput.fill(String(data.durasiMalam));

        if (data.useRombongan) {
            await this.useRombonganCheckbox.check();
            await this.hargaRombonganInput.fill(data.hargaRaw);
            await expect(this.hargaRombonganInput).toHaveValue(data.expectedFormattedHarga);

            if (data.maxPeserta !== undefined) {
                await this.maxPesertaInput.fill(String(data.maxPeserta));
            }
        } else {
            await this.hargaPerOrangInput.fill(data.hargaRaw);
            await expect(this.hargaPerOrangInput).toHaveValue(data.expectedFormattedHarga);
        }
    }

    // ==========================================
    // DAYS & FACILITIES EDITOR ACTIONS
    // ==========================================
    async addItineraryDay(day: ItineraryDayInput): Promise<void> {
        await this.dayJudulInput.fill(day.judul);
        if (day.jamMulai) await this.dayJamMulaiInput.fill(day.jamMulai);
        if (day.jamSelesai) await this.dayJamSelesaiInput.fill(day.jamSelesai);
        if (day.lokasi) await this.dayLokasiInput.fill(day.lokasi);
        if (day.deskripsi) await this.dayDeskripsiInput.fill(day.deskripsi);

        await this.addDayButton.click();

        await expect(this.dayJudulInput).toHaveValue('');
        await expect(this.page.getByText(day.judul)).toBeVisible();
    }

    async removeItineraryDay(hariKe: number): Promise<void> {
        await this.page.getByRole('button', { name: `Hapus hari ke-${hariKe}` }).click();
    }

    async addFacility(facility: FacilityInput): Promise<void> {
        await this.facilityTipeSelect.selectOption(facility.tipe);
        await this.facilityNamaInput.fill(facility.nama);
        await this.addFacilityButton.click();

        // Cukup pastikan item fasilitas berhasil masuk ke dalam daftar tampilan
        await expect(this.page.getByText(facility.nama)).toBeVisible();
    }

    async removeFacility(facilityName: string): Promise<void> {
        await this.page.getByRole('button', { name: `Hapus fasilitas ${facilityName}` }).click();
        await expect(this.page.getByText(facilityName)).toBeHidden();
    }

    // ==========================================
    // CONFIRM MODAL ACTION
    // ==========================================
    async confirmModal(options?: {
        expectedTitle?: string | RegExp;
        confirmButtonLabel?: string | RegExp;
        reason?: string;
    }): Promise<void> {
        await expect(this.confirmDialog).toBeVisible();
        await expect(this.confirmCancelButton).toBeFocused();

        if (options?.expectedTitle) {
            await expect(this.confirmDialogTitle).toHaveText(options.expectedTitle);
        }

        const confirmBtn = this.confirmDialog.getByRole('button', {
            name: options?.confirmButtonLabel ?? /Ya, Lanjutkan|Arsipkan|Aktifkan|Hapus/i,
        });

        if (options?.reason !== undefined) {
            await expect(this.confirmReasonTextarea).toBeVisible();
            await expect(confirmBtn).toBeDisabled();
            await this.confirmReasonTextarea.fill(options.reason);
            await expect(confirmBtn).toBeEnabled();
        }

        await confirmBtn.click();
        await expect(this.confirmDialog).toBeHidden();
    }
}