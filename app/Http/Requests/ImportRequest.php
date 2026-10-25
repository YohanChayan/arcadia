<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An AI's answer to import, as the `.json` file it was saved to — read here, never stored — and
 * the two choices the import asks for.
 */
class ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:json', 'max:500'],
            'replace' => ['boolean'],
            'apply_details' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => __('arcadia.editor.import.errors.no_file'),
            'file.file' => __('arcadia.editor.import.errors.no_file'),
            'file.extensions' => __('arcadia.editor.import.errors.not_json_file'),
            'file.max' => __('arcadia.editor.import.errors.too_large'),
        ];
    }

    /** The file's text, for `GuideImport::extract()` to find the answer in. */
    public function answer(): string
    {
        return (string) $this->file('file')->get();
    }
}
