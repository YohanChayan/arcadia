<?php

namespace App\Http\Requests;

use App\Enums\PromptVariableType;
use App\Models\PromptTemplate;
use App\Services\PromptRenderer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One declared variable of a template's latest version: the name its placeholders use —
 * lowercase, unique within the version — how the form labels and asks for it, whether the
 * prompt can go without it, and what its field starts filled with.
 *
 * **Names are English, whatever the host's language**: one template serves both, and a request's
 * values arrive by name (`topic`, `learner_name`, `language` — `RequestPromptDialog`), as a
 * guide started from it reads them (`StartGuideFromInquiry`). The label is what is translated.
 * Nothing here can tell English from Spanish; the form says so.
 */
class PromptVariableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var PromptTemplate $template */
        $template = $this->route('template');

        return [
            'name' => [
                'required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/',
                // Every form carries the extra instructions under this name already.
                Rule::notIn([PromptRenderer::EXTRA]),
                Rule::unique('guide_prompt_variables', 'name')
                    ->where('version_id', $template->latestVersion()->id)
                    ->ignore($this->route('variable')),
            ],
            'label' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::enum(PromptVariableType::class)],
            'required' => ['boolean'],
            'default_value' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['name.regex' => __('arcadia.editor.prompts.variables.name_rule')];
    }
}
