<?php

// An AI's answer to an activity prompt, attached to a draft (`ActivityImportController`).
return [
    'prompt_hint' => 'Filled from this guide, its content included. Copy it into ChatGPT or Gemini, save the answer as a .json file, and bring it back with «Import activities» on the draft.',
    'lead' => 'The answer to an activity prompt, as its .json file. It is attached to this draft\'s lines, stages or sections by their keys; nothing else changes.',
    'drop' => 'Drop the .json file here, or choose it.',
    'file' => 'Choose file',
    'change_file' => 'Change file',
    'check' => 'Check',
    'problems' => '{1} :count problem: fix it in the answer, or ask the AI again.|[2,*] :count problems: fix them in the answer, or ask the AI again.',
    'ready' => 'Ready to attach',
    'counts' => ':family: :entries entries on :nodes places.',
    'replaces' => '{0} No :family on this draft yet.|{1} Replaces the :family already on :count place.|[2,*] Replaces the :family already on :count places.',
    'confirm' => 'Attach',
    'places' => ['activities' => 'Activity :number', 'questions' => 'question :number', 'options' => 'option :number', 'pairs' => 'pair :number', 'steps' => 'step :number', 'cards' => 'card :number'],
    'errors' => [
        'no_json' => 'No JSON object was found in the file.',
        'no_file' => 'Choose the .json file with the answer.',
        'not_json_file' => 'The file has to be .json.',
        'too_large' => 'The file is too large (500 KB at most).',
        'family' => 'the answer must say its family: quick_check, stage_game, flip_cards or practice_extras.',
        'unknown_key' => 'this draft has no place with key :key for this kind of activity.',
        'repeated_key' => 'key :key is used twice.',
        'correct' => 'the correct option must be one of its options, counted from 0.',
        'blank' => 'a fill-in needs ___ where the word goes.',
        'not_valid' => 'The answer has problems: check it before attaching.',
        'check_failed' => 'The file could not be checked. Try again.',
        'import_failed' => 'The activities could not be attached. Try again.',
    ],
    'flash' => ['imported' => 'Activities attached to the draft.'],
];
