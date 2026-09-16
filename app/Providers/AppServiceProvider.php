<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Services\AI\AiProvider;
use App\Services\AI\AnthropicClient;
use App\Services\AI\AnthropicProvider;
use App\Services\AI\Contracts\LlmClient;
use App\Services\AI\OpenAIProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * Client LLM utilisé par la boucle d'agent.
         *
         * Seul Anthropic gère aujourd'hui les outils dans ce projet ;
         * un futur client OpenAI se branchera au même endroit.
         */
        $this->app->bind(
            LlmClient::class,
            function () {
                return match (config('ai.provider', config('services.ai.provider'))) {
                    'anthropic' => app(AnthropicClient::class),
                    default => throw new RuntimeException(
                        'Fournisseur IA non supporté par la boucle d\'agent : '
                        . config('ai.provider')
                    ),
                };
            }
        );

        /*
         * Ancien fournisseur « une question / une réponse ».
         * Conservé pour compatibilité.
         */
        $this->app->bind(
            AiProvider::class,
            function () {
                return match (config('ai.provider', config('services.ai.provider'))) {
                    'anthropic' => app(AnthropicProvider::class),
                    'openai' => app(OpenAIProvider::class),
                    default => throw new RuntimeException(
                        'Fournisseur IA inconnu : ' . config('ai.provider')
                    ),
                };
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}