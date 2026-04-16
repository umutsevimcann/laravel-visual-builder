<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Umutsevimcann\VisualBuilder\Contracts\AuthorizationInterface;

/**
 * Validation for the bulk save endpoint (POST /builder/{target}/save).
 *
 * Intentionally permissive at the top level — nested content/style shapes
 * are validated by each field's own validationRules() inside the Actions.
 * This request only ensures the top-level payload has the expected
 * scaffolding:
 *
 *   {
 *     "ordered_ids": [3, 1, 2],
 *     "sections": {
 *       "1": { "content": {...}, "style": {...}, "is_published": true },
 *       "2": { "content": {...} }
 *     }
 *   }
 *
 * Historically, nested wildcard rules (`sections.*.content.*`) with
 * integer section IDs as parent keys caused Laravel's validator to drop
 * entries — we dropped that approach in favor of delegating nested
 * validation to the Action layer where field definitions live.
 *
 * Authorization is delegated to AuthorizationInterface via authorize().
 */
final class BuilderSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AuthorizationInterface $authz */
        $authz = $this->container->make(AuthorizationInterface::class);

        return $authz->check('update');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ordered_ids' => ['nullable', 'array'],
            'ordered_ids.*' => ['integer'],

            'sections' => ['nullable', 'array'],
        ];
    }

    /**
     * Custom cross-field rule: at least one of ordered_ids or sections must
     * be present. Empty save requests are rejected as a fail-fast guard.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if (empty($this->input('ordered_ids')) && empty($this->input('sections'))) {
                $v->errors()->add(
                    'payload',
                    'At least one of ordered_ids or sections must be provided.',
                );
            }
        });
    }
}
