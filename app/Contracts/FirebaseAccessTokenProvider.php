<?php

namespace App\Contracts;

interface FirebaseAccessTokenProvider
{
    public function token(): string;
}
