import { router } from '@inertiajs/svelte';

export type ContactPayload = {
    nama: string;
    email: string;
    pesan: string;
};

export const submitContactForm = (
    data: ContactPayload,
    options?: Parameters<typeof router.post>[2],
) => router.post('/contact', data, options);
