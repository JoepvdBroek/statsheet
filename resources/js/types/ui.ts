import type { ReactNode } from 'react';
import type { PageShell } from '@/types/navigation';

export type AppLayoutProps = Partial<PageShell> & {
    children: ReactNode;
};

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
