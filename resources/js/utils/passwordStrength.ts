export type PasswordStrength = {
    level: number;
    label: string;
    barColor: string;
    textColor: string;
};

export function calculatePasswordStrength(password: string): PasswordStrength {
    if (!password) {
        return { level: 0, label: '', barColor: '', textColor: '' };
    }

    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[^a-zA-Z0-9]/.test(password)) score++;

    // Palet app.css + warna kontras untuk teks
    if (score <= 1) {
        return { level: 1, label: 'Lemah', barColor: 'bg-red-500', textColor: 'text-red-600' };
    }
    if (score === 2) {
        return { level: 2, label: 'Cukup', barColor: 'bg-sun', textColor: 'text-sun-text' };
    }
    if (score === 3) {
        return { level: 3, label: 'Kuat', barColor: 'bg-green', textColor: 'text-green-text' };
    }
    return { level: 4, label: 'Sangat Kuat', barColor: 'bg-green-dark', textColor: 'text-green-dark' };
}

export function isPasswordMismatch(password: string, confirmation: string): boolean {
    return confirmation.length > 0 && password !== confirmation;
}

export function checkPasswordCriteria(password: string): {
    minLength: boolean;
    hasLetter: boolean;
    hasUpperLowerCase: boolean;
    hasNumber: boolean;
    hasSymbol: boolean;
} {
    return {
        minLength: password.length >= 8,
        hasLetter: /[a-zA-Z]/.test(password),
        hasUpperLowerCase: /[a-z]/.test(password) && /[A-Z]/.test(password),
        hasNumber: /\d/.test(password),
        hasSymbol: /[^a-zA-Z0-9]/.test(password),
    };
}