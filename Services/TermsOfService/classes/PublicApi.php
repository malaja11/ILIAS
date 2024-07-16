<?php

namespace ILIAS\TermsOfService;

use ILIAS\LegalDocuments\ConsumerToolbox\User;

class PublicApi implements PublicApiInterface
{
    public function __construct(private readonly User $user)
    {
    }

    public function userHasToAcceptTermsOfService(): bool
    {
        return (!$this->user->cannotAgree() && is_null($this->user->agreeDate()) && !$this->user->isRoot());

    }

    public function areTermsOfServiceActive(): bool
    {
        return true;
    }
}
