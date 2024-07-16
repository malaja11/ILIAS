<?php

namespace ILIAS\TermsOfService;

interface PublicApiInterface
{
    public function areTermsOfServiceActive(): bool;

    public function userHasToAcceptTermsOfService(): bool;
}
