<?php

namespace App\Console\Commands;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-owner {--name= : The owner\'s name} {--email= : The owner\'s email address} {--password= : The owner\'s password}')]
#[Description('Create the owner account, since public registration is switched off')]
class CreateOwner extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CreateNewUser $createNewUser): int
    {
        $name = $this->option('name') ?? text('Name', required: true);
        $email = $this->option('email') ?? text('Email address', required: true);
        $password = $this->option('password') ?? password('Password', required: true);

        try {
            $owner = $createNewUser->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $owner->markEmailAsVerified();

        $this->components->info("Owner account created for {$owner->email}.");

        return self::SUCCESS;
    }
}
