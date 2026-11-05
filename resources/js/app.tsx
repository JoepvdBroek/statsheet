import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { focusHeadingOnPageChange } from '@/lib/page-heading-focus';
import { trackPageVisits } from '@/lib/page-visits';
import { flushPrefetchesAfterMutations } from '@/lib/prefetch';
import { preloadPagesWhenIdle } from '@/lib/preload-pages';
import { workoutPageShell } from '@/lib/workout-page';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name, page) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name === 'workouts/show':
                return [AppLayout, workoutPageShell(page)];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: false,
});

if (typeof window !== 'undefined') {
    trackPageVisits();
    focusHeadingOnPageChange();
    flushPrefetchesAfterMutations();
    preloadPagesWhenIdle();
}
