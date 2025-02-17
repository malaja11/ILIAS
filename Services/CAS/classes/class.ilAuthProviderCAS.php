<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

/**
 * CAS authentication provider
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilAuthProviderCAS extends ilAuthProvider
{
    private ilCASSettings $settings;

    public function __construct(ilAuthCredentials $credentials)
    {
        parent::__construct($credentials);
        $this->settings = ilCASSettings::getInstance();
    }

    protected function getSettings(): ilCASSettings
    {
        return $this->settings;
    }

    public function doAuthentication(ilAuthStatus $status): bool
    {
        $this->getLogger()->debug('Starting cas authentication attempt... ');

        try {
            // Uncomment the following line to get trace-level loggin by CAS
            //phpCAS::setLogger($this->getLogger());
            // Caution: If you set this to "true", there might be output
            // and the redirect won't work and you get an ILIAS Whoopsy
            // Though, you may need to for debugging other issues.
            phpCAS::setVerbose(false);
            $this->getLogger()->debug('Create client... ');
            phpCAS::client(
                CAS_VERSION_2_0,
                $this->getSettings()->getServer(),
                $this->getSettings()->getPort(),
                $this->getSettings()->getUri(),
                ilUtil::_getHttpPath()
            );

            phpCAS::setNoCasServerValidation();
            $this->getLogger()->debug('Fore CAS auth... ');
            phpCAS::forceAuthentication();
            $this->getLogger()->debug('Fore CAS auth done.');
        } catch (Exception $e) {
            $this->getLogger()->error('Cas authentication failed with message: ' . $e->getMessage());
            $this->handleAuthenticationFail($status, 'err_wrong_login');
            return false;
        }

        if (phpCAS::getUser() === '') {
            $this->getLogger()->debug('CAS user is empty.');
            return $this->handleAuthenticationFail($status, 'err_wrong_login');
        }
        $this->getCredentials()->setUsername(phpCAS::getUser());
        $this->getLogger()->debug('user name set to CAS user.');

        // check and handle ldap data sources
        if (ilLDAPServer::isDataSourceActive(ilAuthUtils::AUTH_CAS)) {
            return $this->handleLDAPDataSource($status);
        }

        // Check account available
        $local_user = ilObjUser::_checkExternalAuthAccount("cas", $this->getCredentials()->getUsername());
        if ($local_user !== '' && $local_user !== null) {
            $this->getLogger()->debug('CAS authentication successful.');
            $status->setStatus(ilAuthStatus::STATUS_AUTHENTICATED);
            $status->setAuthenticatedUserId(ilObjUser::_lookupId($local_user));
            return true;
        }

        if (!$this->getSettings()->isUserCreationEnabled()) {
            $this->getLogger()->debug('User creation disabled. No valid local account found');
            $this->handleAuthenticationFail($status, 'err_auth_cas_no_ilias_user');
            return false;
        }

        $importer = new ilCASAttributeToUser($this->getSettings());
        $new_name = $importer->create($this->getCredentials()->getUsername());

        if ($new_name === '') {
            $this->getLogger()->debug('User creation failed.');
            $this->handleAuthenticationFail($status, 'err_auth_cas_no_ilias_user');
            return false;
        }

        $status->setStatus(ilAuthStatus::STATUS_AUTHENTICATED);
        $status->setAuthenticatedUserId(ilObjUser::_lookupId($new_name));
        return true;
    }

    protected function handleLDAPDataSource(ilAuthStatus $status): bool
    {
        $server = ilLDAPServer::getInstanceByServerId(
            ilLDAPServer::getDataSource(ilAuthUtils::AUTH_CAS)
        );

        $this->getLogger()->debug('Using ldap data source for user: ' . $this->getCredentials()->getUsername());

        $sync = new ilLDAPUserSynchronisation('cas', $server->getServerId());
        $sync->setExternalAccount($this->getCredentials()->getUsername());
        $sync->setUserData(array());
        $sync->forceCreation(true);

        try {
            $internal_account = $sync->sync();
        } catch (UnexpectedValueException $e) {
            $this->getLogger()->warning('Authentication failed with message: ' . $e->getMessage());
            $this->handleAuthenticationFail($status, 'err_wrong_login');
            return false;
        } catch (ilLDAPSynchronisationFailedException $e) {
            $this->handleAuthenticationFail($status, 'err_auth_ldap_failed');
            return false;
        } catch (ilLDAPSynchronisationForbiddenException|ilLDAPAccountMigrationRequiredException $e) {
            // No syncronisation allowed => create Error
            $this->getLogger()->warning('User creation disabled. No valid local account found');
            $this->handleAuthenticationFail($status, 'err_auth_cas_no_ilias_user');
            return false;
        }
        $status->setStatus(ilAuthStatus::STATUS_AUTHENTICATED);
        $status->setAuthenticatedUserId(ilObjUser::_lookupId($internal_account));
        return true;
    }
}
