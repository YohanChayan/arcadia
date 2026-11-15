<?php

return [
    'title' => 'Vocabulary',
    'description' => 'What English guides choose from: the terms they are grouped by and the phrases their readers see. Each language has its own. A guide\'s goals are written on the guide itself.',
    'tabs' => ['categories' => 'Categories', 'topics' => 'Topics', 'phrases' => 'Phrases'],
    'hints' => [
        'categories' => 'Where a guide sits: Office, Business, Crypto. One per guide.',
        'topics' => 'What a guide covers: formulas, pivot tables. Several per guide.',
    ],
    'fields' => ['name' => 'Name'],
    'add' => 'Add',
    'save' => 'Save',
    'used_by' => '{0} unused|{1} in 1 guide|[2,*] in :count guides',
    'empty' => 'None yet.',
    'delete' => [
        'title' => 'Delete this term?',
        'body' => 'Guides that use it lose it. The guides themselves are not deleted.',
        'confirm' => 'Delete',
    ],
    'errors' => [
        'name' => 'Write a name.',
        'taken' => 'A term with that name already exists.',
    ],
    'flash' => ['saved' => 'Saved.', 'deleted' => 'Deleted.'],
    // The categorizer: one category's guides, and a search over all of them to file more.
    'categorizer' => [
        'open' => 'Guides',
        'description' => 'The guides in this category, and a search to add several at once.',
        'inside' => 'In this category',
        'inside_empty' => 'No guides yet.',
        'remove' => 'Remove from the category',
        'search' => 'Search guides',
        'search_placeholder' => 'Guide title…',
        'results_empty' => 'No guide matches.',
        'uncategorised' => 'Uncategorised',
        'statuses' => ['draft' => 'Draft', 'published' => 'Published'],
        'selected' => '{0} Nothing selected|{1} 1 selected|[2,*] :count selected',
        'clear' => 'Clear',
        'add' => 'Add to the category',
        'move' => [
            'title' => 'Change their category?',
            'body' => 'Already in a category: :guides. They will move to “:category”.',
            'confirm' => 'Change',
        ],
        'errors' => [
            'none' => 'Select at least one guide.',
            'filed_elsewhere' => '{1} One of those guides is already in another category.|[2,*] :count of those guides are already in another category.',
        ],
        'flash' => ['filed' => 'Guides added to the category.', 'unfiled' => 'Guide removed from the category.'],
    ],
];
