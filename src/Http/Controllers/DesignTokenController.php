<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Umutsevimcann\VisualBuilder\Domain\Services\DesignTokenService;
use Umutsevimcann\VisualBuilder\Http\Requests\DesignTokensRequest;

/**
 * Save global design tokens (colors + fonts).
 *
 * Separate from BuilderController because design tokens are SITE-wide,
 * not target-specific — they don't take a target type + id in the URL.
 */
final class DesignTokenController extends BaseController
{
    public function __construct(
        private readonly DesignTokenService $service,
    ) {}

    public function update(DesignTokensRequest $request): JsonResponse
    {
        $this->service->save($request->validated());

        return response()->json([
            'success' => true,
            'tokens' => $this->service->all(),
        ]);
    }
}
