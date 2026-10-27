<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An AI's answer to an activity prompt, as the `.json` file it was saved to — read here, never
 * stored.
 */
class ActivityImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:json', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => __('arcadia.editor.activity_import.errors.no_file'),
            'file.file' => __('arcadia.editor.activity_import.errors.no_file'),
            'file.extensions' => __('arcadia.editor.activity_import.errors.not_json_file'),
            'file.max' => __('arcadia.editor.activity_import.errors.too_large'),
        ];
    }

    /** The file's text, for `ActivityImport::extract()` to find the answer in. */
    public function answer(): string
    {
        return (string) $this->file('file')->get();
    }
}
