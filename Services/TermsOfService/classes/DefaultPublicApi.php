<?php

namespace ILIAS\TermsOfService;

class DefaultPublicApi implements PublicApiInterface
{
    public function areTermsOfServiceActive(): bool
    {
        return false;
    }

    public function userHasToAcceptTermsOfService(): bool
    {
        return false;

    }
}
