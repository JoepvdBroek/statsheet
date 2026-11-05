// Components
import { Form, Head } from '@inertiajs/react';
import { LockedButton } from '@/components/locked-button';
import TextLink from '@/components/text-link';
import { nameVisit } from '@/lib/failed-visits';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Email verification" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-normal text-green-600">
                    A new verification link has been sent to the email address
                    you provided during registration.
                </div>
            )}

            <Form
                {...send.form()}
                className="space-y-6 text-center"
                onBefore={(visit) => nameVisit(visit, 'sendVerificationEmail')}
            >
                {({ processing }) => (
                    <>
                        <LockedButton locked={processing} variant="secondary">
                            Resend verification email
                        </LockedButton>

                        <TextLink
                            href={logout()}
                            onBefore={(visit) => nameVisit(visit, 'logOut')}
                            className="mx-auto block text-sm"
                        >
                            Log out
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Email verification',
    description:
        'Please verify your email address by clicking on the link we just emailed to you.',
};
