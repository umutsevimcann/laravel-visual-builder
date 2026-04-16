<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Umutsevimcann\VisualBuilder\Contracts\AuthorizationInterface;

/**
 * Validation for the Site Settings save endpoint (global colors + fonts).
 *
 * The Action-level DesignTokenService::sanitize() applies an additional
 * strict regex-based whitelist. This request enforces basic shape +
 * max lengths to catch malformed payloads early.
 */
final class DesignTokensRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AuthorizationInterface $authz */
        $authz = $this->container->make(AuthorizationInterface::class);

        return $authz->check('manage-design-system');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'colors' => ['nullable', 'array'],
            'colors.*.id' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_-]+$/i'],
            'colors.*.label' => ['required', 'string', 'max:50'],
            'colors.*.value' => ['required', 'string', 'max:10', 'regex:/^#[0-9a-fA-F]{3,8}$/'],

            'fonts' => ['nullable', 'array'],
            'fonts.*.id' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_-]+$/i'],
            'fonts.*.label' => ['required', 'string', 'max:50'],
            'fonts.*.family' => ['required', 'string', 'max:150'],
        ];
    }
}
