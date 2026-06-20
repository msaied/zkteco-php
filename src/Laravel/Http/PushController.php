<?php

declare(strict_types=1);

namespace ZkTeco\Laravel\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\ADMS\Http\PushResponse;
use ZkTeco\ADMS\Http\PushRouter;

/**
 * The bridge's thin adapter between Laravel HTTP and the framework-neutral ADMS
 * core (see docs/adr/0008): translate the Illuminate request into a
 * {@see PushRequest}, hand it to the {@see PushRouter}, and translate the
 * {@see PushResponse} back into an Illuminate response.
 *
 * One method serves every `/iclock/*` route; the router decides what to do from
 * the path and method.
 */
final class PushController
{
    public function __construct(private PushRouter $router) {}

    public function handle(Request $request): Response
    {
        $response = $this->router->dispatch(new PushRequest(
            method: $request->getMethod(),
            path: $request->path(),
            query: $this->stringQuery($request),
            body: $request->getContent(),
        ));

        return new Response($response->body, $response->status, $response->headers);
    }

    /**
     * Query parameters as a flat string map. ADMS parameters are always flat
     * scalars; any array-valued parameter is dropped rather than guessed at.
     *
     * @return array<string, string>
     */
    private function stringQuery(Request $request): array
    {
        $query = [];

        foreach ($request->query() as $key => $value) {
            if (is_scalar($value)) {
                $query[$key] = (string) $value;
            }
        }

        return $query;
    }
}
