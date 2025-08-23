<?php

declare(strict_types=1);

namespace Misakstvanu\LaravelFortifyPasskeys\Controllers;

use Misakstvanu\LaravelFortifyPasskeys\Security\Guesser\UserEntityGuesser;
use Misakstvanu\LaravelFortifyPasskeys\Security\Handler\CreationOptionsHandler;
use Misakstvanu\LaravelFortifyPasskeys\Security\Handler\FailureHandler;
use Misakstvanu\LaravelFortifyPasskeys\Security\Storage\Item;
use Misakstvanu\LaravelFortifyPasskeys\Security\Storage\OptionsStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Throwable;
use Webauthn\Bundle\CredentialOptionsBuilder\PublicKeyCredentialCreationOptionsBuilder;

final readonly class AttestationRequestController
{
    public function __construct(
        private PublicKeyCredentialCreationOptionsBuilder $extractor,
        private UserEntityGuesser $userEntityGuesser,
        private OptionsStorage $optionsStorage,
        private CreationOptionsHandler $creationOptionsHandler,
        private FailureHandler|AuthenticationFailureHandlerInterface $failureHandler,
        private bool $hideExistingExcludedCredentials = false,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        try {
            $userEntity = $this->userEntityGuesser->findUserEntity($request);
            $publicKeyCredentialCreationOptions = $this->extractor->getFromRequest(
                $request,
                $userEntity,
                $this->hideExistingExcludedCredentials
            );

            $response = $this->creationOptionsHandler->onCreationOptions(
                $publicKeyCredentialCreationOptions,
                $userEntity
            );
            $this->optionsStorage->store(Item::create($publicKeyCredentialCreationOptions, $userEntity));

            return $response;
        } catch (Throwable $throwable) {
            if ($this->failureHandler instanceof AuthenticationFailureHandlerInterface) {
                return $this->failureHandler->onAuthenticationFailure(
                    $request,
                    new AuthenticationException($throwable->getMessage(), $throwable->getCode(), $throwable)
                );
            }

            return $this->failureHandler->onFailure($request, $throwable);
        }
    }
}
