import { expect, type Page } from '@playwright/test';
import { SEL } from '../fixtures/selectors';

export interface ParticipantInput {
    nama: string;
    umur?: number;
    no_hp?: string;
    catatan?: string;
}

export class ClientBookingPage {
    constructor(private readonly page: Page) {}

    // ==========================================
    // NAVIGATION
    // ==========================================

    async gotoIndex(): Promise<void> {
        await this.page.goto('/client/bookings');
        await expect(SEL.CLIENT.booking.heading(this.page)).toBeVisible();
    }

    async gotoCreate(packageId: string): Promise<void> {
        await this.page.goto(`/client/bookings/create/${packageId}`);
        await expect(
            this.page.getByRole('heading', { name: 'Info Pemesanan' }),
        ).toBeVisible();
    }

    async gotoShow(bookingId: string): Promise<void> {
        await this.page.goto(`/client/bookings/${bookingId}`);
        await expect(SEL.CLIENT.booking.backLink(this.page)).toBeVisible();
    }

    // ==========================================
    // CREATE WIZARD ACTIONS
    // ==========================================

    async fillStep1(data: {
        tanggal: string;
        jumlahPeserta: number;
        catatan?: string;
    }): Promise<void> {
        await SEL.CLIENT.booking
            .tanggalKeberangkatan(this.page)
            .fill(data.tanggal);
        await SEL.CLIENT.booking
            .jumlahPeserta(this.page)
            .fill(String(data.jumlahPeserta));

        if (data.catatan !== undefined) {
            await SEL.CLIENT.booking.catatan(this.page).fill(data.catatan);
        }
    }

    async proceedToStep2(): Promise<void> {
        await SEL.CLIENT.booking.nextStep(this.page).click();
    }

    async fillParticipants(participants: ParticipantInput[]): Promise<void> {
        for (let i = 0; i < participants.length; i++) {
            const p = participants[i];
            await SEL.CLIENT.booking.participantNama(this.page, i).fill(p.nama);

            if (p.umur !== undefined) {
                await SEL.CLIENT.booking
                    .participantUmur(this.page, i)
                    .fill(String(p.umur));
            }
            if (p.no_hp !== undefined) {
                await SEL.CLIENT.booking
                    .participantNoHp(this.page, i)
                    .fill(p.no_hp);
            }
            if (p.catatan !== undefined) {
                await SEL.CLIENT.booking
                    .participantCatatan(this.page, i)
                    .fill(p.catatan);
            }
        }
    }

    async submitBooking(packageId: string): Promise<void> {
        const responsePromise = this.page.waitForResponse(
            (res) =>
                res.url().includes(`/client/bookings/${packageId}`) &&
                res.request().method() === 'POST',
        );
        await SEL.CLIENT.booking.submit(this.page).click();
        await responsePromise;
    }

    // ==========================================
    // SHOW PAGE ACTIONS & ASSERTIONS
    // ==========================================

    async expectParticipantVisible(nama: string): Promise<void> {
        await expect(this.page.getByRole('main').getByText(nama)).toBeVisible();
    }

    async selectTransferMethod(): Promise<void> {
        const button = SEL.CLIENT.booking.selectTransferButton(this.page);
        if ((await button.count()) > 0) {
            await button.click();
        }
    }

    async uploadPaymentProof(
        bookingId: string,
        file: { name: string; mimeType: string; buffer: Buffer },
    ): Promise<void> {
        await this.selectTransferMethod();
        const input = SEL.CLIENT.booking.paymentProofInput(this.page);
        await input.setInputFiles(file);

        const responsePromise = this.page.waitForResponse(
            (res) =>
                res
                    .url()
                    .includes(`/client/bookings/${bookingId}/upload-proof`) &&
                res.request().method() === 'POST',
        );
        await SEL.CLIENT.booking.uploadProofButton(this.page).click();
        await responsePromise;
    }

    async cancelBooking(bookingId: string): Promise<void> {
        await SEL.CLIENT.booking.cancelButton(this.page).click();
        await expect(SEL.CLIENT.booking.confirmModal(this.page)).toBeVisible();

        const responsePromise = this.page.waitForResponse(
            (res) =>
                res.url().includes(`/client/bookings/${bookingId}/cancel`) &&
                res.request().method() === 'POST',
        );
        await SEL.CLIENT.booking
            .confirmCancelButton(this.page)
            .click({ force: true });
        await responsePromise;
    }
}
