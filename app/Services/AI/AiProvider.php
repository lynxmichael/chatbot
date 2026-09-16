<?php

namespace App\Services\AI;

interface AiProvider
{
   public function generateResponse(
    string $message,
    array $context = []
): array;

}
