<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Umutsevimcann\VisualBuilder\Contracts\AuthorizationInterface;

/**
 * Validation for the image upload endpoint.
 *
 * MIME whitelist + max size are read from config so host apps can loosen
 * or tighten the rules without forking the package.
 */
final class UploadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AuthorizationInterface $authz */
        $authz = $this->container->make(AuthorizationInterface::class);

        return $authz->check('upload-media');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $allowedMimes = (array) config('visual-builder.media.allowed_mimes', [
            'jpg', 'jpeg', 'png', 'webp', 'svg', 'gif',
        ]);
        $maxKb = (int) config('visual-builder.media.max_size_kb', 8192);

        return [
            'file' => [
                'required',
                'file',
                'mimes:'.implode(',', $allowedMimes),
                "max:{$maxKb}",
            ],
            'directory' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_\/-]*$/i'],
        ];
    }
}
