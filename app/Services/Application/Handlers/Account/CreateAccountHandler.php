<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Account;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\EmailAlreadyExists;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\AccountConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\DTO\CreateAccountDTO;
use HiEvents\Services\Application\Handlers\Account\Exceptions\AccountConfigurationDoesNotExist;
use HiEvents\Services\Application\Handlers\Account\Exceptions\AccountRegistrationDisabledException;
use HiEvents\Services\Domain\Account\AccountUserAssociationService;
use HiEvents\Services\Domain\User\EmailConfirmationService;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Hashing\HashManager;
use NumberFormatter;
use Psr\Log\LoggerInterface;
use Throwable;

// ⬇️ ADD THIS
use HiEvents\Support\TxnProbe;

class CreateAccountHandler
{
    public function __construct(
        private readonly UserRepositoryInterface                 $userRepository,
        private readonly AccountRepositoryInterface              $accountRepository,
        private readonly HashManager                             $hashManager,
        private readonly DatabaseManager                         $databaseManager,
        private readonly Repository                              $config,
        private readonly EmailConfirmationService                $emailConfirmationService,
        private readonly AccountUserAssociationService           $accountUserAssociationService,
        private readonly AccountUserRepositoryInterface          $accountUserRepository,
        private readonly AccountConfigurationRepositoryInterface $accountConfigurationRepository,
        private readonly LoggerInterface                         $logger,
    ) {}

    /**
     * @throws Throwable
     */
    // public function handle(CreateAccountDTO $accountData): AccountDomainObject
    // {
    //     if ($this->config->get('app.disable_registration')) {
    //         throw new AccountRegistrationDisabledException();
    //     }

    //     $isSaasMode   = $this->config->get('app.saas_mode_enabled');
    //     $passwordHash = $this->hashManager->make($accountData->password);

    //     return $this->databaseManager->transaction(function () use ($isSaasMode, $passwordHash, $accountData) {

    //         // 🔎 This is a frequent FK cause on new DBs: ensure the default config exists
    //         $accountConfigurationId = TxnProbe::step('account_config.resolve_default', function () use ($accountData) {
    //             return $this->getAccountConfigurationId($accountData);
    //         });

    //         // accounts.insert
    //         $account = TxnProbe::step('accounts.insert', function () use ($isSaasMode, $accountData, $accountConfigurationId) {
    //             return $this->accountRepository->create([
    //                 'timezone'                 => $this->getTimezone($accountData),
    //                 'currency_code'            => $this->getCurrencyCode($accountData),
    //                 'name'                     => $accountData->first_name . ($accountData->last_name ? ' ' . $accountData->last_name : ''),
    //                 'email'                    => strtolower($accountData->email),
    //                 'short_id'                 => IdHelper::shortId(IdHelper::ACCOUNT_PREFIX),
    //                 'account_verified_at'      => $isSaasMode ? null : now()->toDateTimeString(),
    //                 'account_configuration_id' => $accountConfigurationId,
    //             ]);
    //         });

    //         // users.find_existing OR users.create
    //         $user = $this->getExistingUser($accountData);
    //         if (!$user) {
    //             $user = TxnProbe::step('users.create', function () use ($passwordHash, $accountData, $isSaasMode) {
    //                 return $this->userRepository->create([
    //                     'password'         => $passwordHash,
    //                     'email'            => strtolower($accountData->email),
    //                     'first_name'       => $accountData->first_name,
    //                     'last_name'        => $accountData->last_name,
    //                     'timezone'         => $this->getTimezone($accountData),
    //                     'email_verified_at'=> $isSaasMode ? null : now()->toDateTimeString(),
    //                     'locale'           => $accountData->locale,
    //                 ]);
    //             });
    //         }

    //         // accounts_users.associate
    //         TxnProbe::step('accounts_users.associate', function () use ($user, $account) {
    //             $this->accountUserAssociationService->associate(
    //                 user: $user,
    //                 account: $account,
    //                 role: Role::ADMIN,
    //                 status: UserStatus::ACTIVE,
    //                 isAccountOwner: true
    //             );
    //         });

    //         // (email isn’t SQL, but if it throws you’ll see the step)
    //         TxnProbe::step('email.send_confirmation', function () use ($user, $account) {
    //             $this->emailConfirmationService->sendConfirmation($user, $account->getId());
    //             return true;
    //         });

    //         return $account;
    //     });
    // }
public function handle(CreateAccountDTO $accountData): AccountDomainObject
{
    if ($this->config->get('app.disable_registration')) {
        throw new AccountRegistrationDisabledException();
    }

    $isSaasMode   = $this->config->get('app.saas_mode_enabled');
    $passwordHash = $this->hashManager->make($accountData->password);

    // Run the original transaction body via a closure
    $runner = function () use ($isSaasMode, $passwordHash, $accountData) {

        $accountConfigurationId = TxnProbe::step('account_config.resolve_default', function () use ($accountData) {
            return $this->getAccountConfigurationId($accountData);
        });

        $account = TxnProbe::step('accounts.insert', function () use ($isSaasMode, $accountData, $accountConfigurationId) {
            return $this->accountRepository->create([
                'timezone'                 => $this->getTimezone($accountData),
                'currency_code'            => $this->getCurrencyCode($accountData),
                'name'                     => $accountData->first_name . ($accountData->last_name ? ' ' . $accountData->last_name : ''),
                'email'                    => strtolower($accountData->email),
                'short_id'                 => IdHelper::shortId(IdHelper::ACCOUNT_PREFIX),
                'account_verified_at'      => $isSaasMode ? null : now()->toDateTimeString(),
                'account_configuration_id' => $accountConfigurationId,
            ]);
        });

        $user = $this->getExistingUser($accountData);
        if (!$user) {
            $user = TxnProbe::step('users.create', function () use ($passwordHash, $accountData, $isSaasMode) {
                return $this->userRepository->create([
                    'password'          => $passwordHash,
                    'email'             => strtolower($accountData->email),
                    'first_name'        => $accountData->first_name,
                    'last_name'         => $accountData->last_name,
                    'timezone'          => $this->getTimezone($accountData),
                    'email_verified_at' => $isSaasMode ? null : now()->toDateTimeString(),
                    'locale'            => $accountData->locale,
                ]);
            });
        }

        TxnProbe::step('accounts_users.associate', function () use ($user, $account) {
            $this->accountUserAssociationService->associate(
                user: $user,
                account: $account,
                role: Role::ADMIN,
                status: UserStatus::ACTIVE,
                isAccountOwner: true
            );
        });

        TxnProbe::step('email.send_confirmation', function () use ($user, $account) {
            $this->emailConfirmationService->sendConfirmation($user, $account->getId());
            return true;
        });

        return $account;
    };

    // Toggle: disable the transaction to surface the FIRST failing SQL (avoid 25P02)
    // You can further guard this with config('app.debug') if you want.
    $noTxn = request()->headers->get('X-Debug-NoTxn') === '1';

    return $noTxn
        ? $runner()                                // run steps one-by-one (no txn) for debugging
        : $this->databaseManager->transaction($runner); // normal path
}
    private function getTimezone(CreateAccountDTO $accountData): ?string
    {
        return $accountData->timezone ?? $this->config->get('app.default_timezone');
    }

    private function getCurrencyCode(CreateAccountDTO $accountData): string
    {
        $defaultCurrency = $this->config->get('app.default_currency_code');

        if ($accountData->currency_code !== null) {
            return $accountData->currency_code;
        }

        if ($accountData->locale !== null) {
            $numberFormatter = new NumberFormatter($accountData->locale, NumberFormatter::CURRENCY);
            $guessedCode = $numberFormatter->getTextAttribute(NumberFormatter::CURRENCY_CODE);
            if ($guessedCode && $guessedCode !== 'XXX') return $guessedCode;
        }

        return $defaultCurrency;
    }

    /**
     * @throws EmailAlreadyExists
     */
    private function getExistingUser(CreateAccountDTO $accountData): ?UserDomainObject
    {
        // users.find_existing
        $existingUser = TxnProbe::step('users.find_existing', function () use ($accountData) {
            return $this->userRepository->findFirstWhere([
                'email' => strtolower($accountData->email),
            ]);
        });

        if ($existingUser === null) return null;

        // accounts_users.find_owner_by_user
        $existingOwner = TxnProbe::step('accounts_users.find_owner_by_user', function () use ($existingUser) {
            return $this->accountUserRepository->findFirstWhere([
                'user_id'          => $existingUser->getId(),
                'is_account_owner' => true,
            ]);
        });

        if ($existingOwner !== null) {
            throw new EmailAlreadyExists(
                __('There is already an account associated with this email. Please log in instead.')
            );
        }

        return $existingUser;
    }

    /**
     * @throws AccountConfigurationDoesNotExist
     */
    private function getAccountConfigurationId(CreateAccountDTO $accountData): int
    {
        if ($accountData->invite_token !== null) {
            $decryptedInviteToken = decrypt($accountData->invite_token);
            $accountConfigurationId = $decryptedInviteToken['account_configuration_id'];

            // account_config.find_by_id
            $accountConfiguration = TxnProbe::step('account_config.find_by_id', function () use ($accountConfigurationId) {
                return $this->accountConfigurationRepository->findFirstWhere(['id' => $accountConfigurationId]);
            });

            if ($accountConfiguration !== null) {
                return $accountConfiguration->getId();
            }

            $this->logger->error('Invalid account configuration ID in invite token', [
                'account_configuration_id' => $accountConfigurationId,
            ]);
        }

        // account_config.find_default
        $defaultConfiguration = TxnProbe::step('account_config.find_default', function () {
            return $this->accountConfigurationRepository->findFirstWhere(['is_system_default' => true]);
        });

        if ($defaultConfiguration === null) {
            $this->logger->error('No default account configuration found');
            throw new AccountConfigurationDoesNotExist(
                __('There is no default account configuration available')
            );
        }

        return $defaultConfiguration->getId();
    }
}
