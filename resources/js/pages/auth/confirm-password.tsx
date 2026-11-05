import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { LockedButton } from '@/components/locked-button';
import PasswordInput from '@/components/password-input';
import { Label } from '@/components/ui/label';
import { nameVisit } from '@/lib/failed-visits';
import { store } from '@/routes/password/confirm';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/components/passkey-verify';

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirm password" />

            <PasskeyVerify
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label="Confirm with passkey"
                loadingLabel="Confirming..."
                separator="Or confirm with password"
            />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                onBefore={(visit) => nameVisit(visit, 'confirmPassword')}
            >
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">Password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder="Password"
                                autoComplete="current-password"
                                autoFocus
                            />

                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center">
                            <LockedButton
                                className="w-full"
                                locked={processing}
                                data-test="confirm-password-button"
                            >
                                Confirm password
                            </LockedButton>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

ConfirmPassword.layout = {
    title: 'Confirm password',
    description:
        'This is a secure area of the application. Please confirm your password before continuing.',
};
