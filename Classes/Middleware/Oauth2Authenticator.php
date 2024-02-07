<?php

declare(strict_types=1);
namespace R3H6\Oauth2Server\Middleware;

use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use R3H6\Oauth2Server\Configuration\Configuration;
use R3H6\Oauth2Server\ExceptionHandlingTrait;
use R3H6\Oauth2Server\Http\RequestAttribute;
use R3H6\Oauth2Server\Routing\Route;
use TYPO3\CMS\Core\Authentication\LoginType;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/***
 *
 * This file is part of the "OAuth2 Server" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020
 *
 ***/

/**
 * Oauth2Authenticator
 */
class Oauth2Authenticator implements MiddlewareInterface
{
    use ExceptionHandlingTrait;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var Route|null $route */
        $route = $request->getAttribute(RequestAttribute::ROUTE);

        if ($route === null) {
            return $handler->handle($request);
        }

        /** @var Configuration $configuration */
        $configuration = $request->getAttribute(RequestAttribute::CONFIGURATION);

        // authorization is required, if the route says so, or
        // if the route does not require it, the header is present and the route it is not an endpoint of ours
        $checkAuth = ($route->getOptions()['authorization'] ?? true)
            || $request->hasHeader('authorization') && !isset($configuration->getEndpoints()[$route->getName()]);
        if (!$checkAuth) {
            return $handler->handle($request);
        }

        // $configuration = $request->getAttribute(RequestAttribute::CONFIGURATION);
        // $resourceServerFactory = GeneralUtility::makeInstance(ResourceServerFactory::class);
        // $resourceServer = $resourceServerFactory($configuration);

        $resourceServer = GeneralUtility::makeInstance(ResourceServer::class);

        try {
            $request = $resourceServer->validateAuthenticatedRequest($request);

            $parametersFromPost = $request->getParsedBody();
            $parametersFromPost['logintype'] = LoginType::LOGIN;
            $request = $request->withParsedBody($parametersFromPost);

            $GLOBALS['TYPO3_REQUEST'] = $request;
            //$GLOBALS['T3_SERVICES']['auth'][Oauth2AuthService::class]['available'] = true
            // we have to set $_POST directly, because
            // \TYPO3\CMS\Core\Authentication\AbstractUserAuthentication::getLoginFormData uses GeneralUtility::_GP
            $_POST = array_merge($_POST, [
                'logintype' => LoginType::LOGIN,
                // 'user' => 'dummy',
                // 'pass' => 'dummy',
                // 'pid' => $extSettings['userPid'],
            ]);
        } catch (\Exception $exception) {
            return $this->withErrorHandling(function () use ($exception) {
                throw $exception;
            });
        }

        return $handler->handle($request);
    }
}
