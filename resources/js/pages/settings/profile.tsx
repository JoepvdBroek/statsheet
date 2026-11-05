import { Form, Head, usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PageContent } from '@/components/page-content';
import { SkeletonBlock } from '@/components/page-skeleton';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { parentScreens } from '@/lib/parent-screens';
import { send } from '@/routes/verification';
import type { Auth, PageShell } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
    timezones,
}: {
    /** Undefined, like timezones, while an instant visit loads the page; the fields come from the shared user. */
    mustVerifyEmail?: boolean;
    status?: string;
    timezones?: string[];
}) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Profile settings" />

            <PageContent
                loading={timezones === undefined}
                className="space-y-6"
            >
                <Heading
                    variant="small"
                    title="Profile"
                    description="Update your name, email address, Bodyweight and timezone"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="Email address"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            Your email address is unverified.{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                Click here to re-send the
                                                verification email.
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-normal text-green-600">
                                                A new verification link has been
                                                sent to your email address.
                                            </div>
                                        )}
                                    </div>
                                )}

                            <div className="grid gap-2">
                                <Label htmlFor="bodyweight">
                                    Bodyweight (kg)
                                </Label>

                                <Input
                                    id="bodyweight"
                                    type="text"
                                    inputMode="decimal"
                                    autoComplete="off"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.bodyweight ?? ''}
                                    name="bodyweight"
                                    placeholder="e.g. 80,5"
                                />

                                <p className="text-sm text-muted-foreground">
                                    Optional. Copied into each Workout when it
                                    starts, so Bodyweight Exercises count
                                    towards Volume.
                                </p>

                                <InputError
                                    className="mt-2"
                                    message={errors.bodyweight}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="timezone">Timezone</Label>

                                {timezones === undefined ? (
                                    // The select takes the owner's timezone once, when it mounts, so it waits for its options.
                                    <SkeletonBlock className="mt-1 h-9" />
                                ) : (
                                    <select
                                        id="timezone"
                                        className="mt-1 flex h-9 w-full min-w-0 rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm"
                                        defaultValue={auth.user.timezone}
                                        name="timezone"
                                        required
                                        aria-invalid={
                                            errors.timezone ? true : undefined
                                        }
                                    >
                                        {timezones.map((timezone) => (
                                            <option
                                                key={timezone}
                                                value={timezone}
                                            >
                                                {timezone}
                                            </option>
                                        ))}
                                    </select>
                                )}

                                <p className="text-sm text-muted-foreground">
                                    Weeks run Monday to Sunday in this timezone.
                                </p>

                                <InputError
                                    className="mt-2"
                                    message={errors.timezone}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </PageContent>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    tab: null,
    title: 'Settings',
    parent: parentScreens.routines,
} satisfies PageShell;
