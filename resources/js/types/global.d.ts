import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            /** The owner's Workout in progress, or null when none is running. */
            workoutInProgressId: number | null;
            [key: string]: unknown;
        };
    }
}
