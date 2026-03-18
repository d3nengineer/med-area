<?php

declare(strict_types=1);

namespace Application\User\Services;

use Application\User\DTO\TokenResponse;
use Domain\User\DTO\UserDTO;
use Application\User\Services\Contracts\AuthServiceContract;
use Domain\User\Enums\TokenType;
use Shared\Exceptions\ServerErrorException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tymon\JWTAuth\JWTGuard;

class AuthService implements AuthServiceContract
{
    public function __construct(
        protected JWTGuard $guard,
    ) {}

    /**
     * Attemt to login user by credentials
     *
     * @param UserDTO $credentials
     * @return TokenResponse
     *
     * @throws BadRequestHttpException
     */
    public function login(UserDTO $credentials): TokenResponse
    {
        if (! $accessToken = $this->guard->attempt($credentials->toArray())) {
            throw new BadRequestHttpException('Nickname or password incorrect.');
        }
        /** @var string $accessToken */

        return $this->tokenResponse($accessToken);
    }

    /**
     * Refresh token for authenticated user
     *
     * @param boolean $forceForever
     * @param boolean $resetClaims
     * @return TokenResponse
     *
     * @throws ServerErrorException
     */
    public function refreshToken(bool $forceForever = false, bool $resetClaims = false): TokenResponse
    {
        if (! $accessToken = $this->guard->refresh($forceForever, resetClaims: true)) {
            throw new ServerErrorException();
        }

        return $this->tokenResponse($accessToken);
    }

    /**
     * Log the user out (Invalidate the token)
     *
     * @return void
     */
    public function logout(): void
    {
        $this->guard->logout();
    }


    /**
     * Construct response with tokens
     *
     * @param string $accessToken
     * @return TokenResponse
     */
    protected function tokenResponse(string $accessToken): TokenResponse
    {
        return TokenResponse::from([
            'access_token' => $accessToken,
            'token_type' => TokenType::BEARER,
            'expires_in' => $this->guard->factory()->getTTL() * 60,
        ]);
    }
}
