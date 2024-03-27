<?php

namespace _PhpScoper99e9e79e8301\TheNetworg\OAuth2\Client\Grant;

class JwtBearer extends \_PhpScoper99e9e79e8301\League\OAuth2\Client\Grant\AbstractGrant
{
    protected function getName()
    {
        return 'urn:ietf:params:oauth:grant-type:jwt-bearer';
    }
    protected function getRequiredRequestParameters()
    {
        return ['requested_token_use', 'assertion'];
    }
}
