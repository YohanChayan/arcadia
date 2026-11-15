<?php

return [
    'action' => 'Presentation',
    'title' => 'Presentation',
    'lead' => 'How this guide is presented: on its readers\' pages, and to anyone who opens its link without being a reader. Upload each image as you generated it: the crops each place needs are cut from it, from the centre. Nothing here needs a new version.',
    'pitch' => [
        'label' => 'Pitch',
        'hint' => 'A few lines that sell the guide to someone who is not yet a reader, under its title on the link\'s page. Also the link preview\'s description. No price.',
        'save' => 'Save pitch',
    ],
    'preview' => 'Preview the link\'s page',
    'prompt' => [
        'title' => 'Cover prompt',
        'hint' => 'Filled from this guide. Add a scene or a style if you have one in mind, copy it into ChatGPT or Gemini, and upload the result under "Cover", below. No reference image is needed.',
        'version' => 'Prompt version',
        'missing' => 'The cover prompt template is not there yet: run its seeder (CoverPromptTemplateSeeder).',
    ],
    'flyer' => [
        'hint' => 'A social media image that sells this guide: its title, its goals, the price and how to ask for it. The prompt arrives filled from the guide; add your WhatsApp or a scene, copy it into ChatGPT or Gemini, and upload the result under "Flyer", below.',
        'open' => 'Flyer prompt',
        'missing' => 'The flyer prompt template is not there yet: run its seeder (GuideFlyerPromptTemplateSeeder).',
    ],
    'slots' => [
        'cover' => [
            'label' => 'Cover',
            'hint' => 'One landscape image for everything: the link preview, the top of the guide and the "My guides" card. Keep the subject in the centre and leave out any text: the edges are cut differently in each place.',
            'rule' => 'JPEG, PNG or WebP, up to :max MB. Landscape, at least :width × :height px, between 7:5 and 1.91:1 — 3:2 and 16:9 both fit.',
            'ratio' => 'The image must be landscape, between 7:5 and 1.91:1; this one is :ratio:1.',
        ],
        'flyer' => [
            'label' => 'Flyer',
            'hint' => 'The social media images that sell this guide, as its flyer prompt generated them — one per network or campaign, side by side. Each is kept whole: nothing is cut from it. Choose several at once, or drop them here.',
            'rule' => 'JPEG, PNG or WebP, up to :max MB. Square or portrait, at least :width × :height px — 1:1, 4:5 and 9:16 all fit.',
            'ratio' => 'The image must be square or portrait, between 9:16 and 1:1; this one is :ratio:1.',
        ],
    ],
    'upload' => 'Upload image',
    'replace' => 'Replace image',
    'uploading' => 'Uploading…',
    'uploading_many' => 'Uploading :done of :total…',
    'add' => 'Add images',
    'count' => ':count of :max',
    'drop' => 'Drop the images to upload them.',
    'empty' => 'No image yet.',
    'original' => 'Original · :width × :height px · :size',
    'download' => 'Open original',
    'crops' => [
        'share' => 'Link preview',
        'hero' => 'Top of the guide',
        'thumb' => '"My guides" card',
    ],
    'remove' => [
        'action' => 'Remove image',
        'title' => 'Remove this image?',
        'body' => 'The original and its crops are deleted. You can upload another at any time.',
        'confirm' => 'Remove',
    ],
    'errors' => [
        'required' => 'Choose an image to upload.',
        'type' => 'The image must be a JPEG, PNG or WebP.',
        'size' => 'The image must be :max MB or smaller.',
        'dimensions' => 'The image must be at least :width × :height px.',
        'full' => 'This holds :max images at most: remove one to add another.',
    ],
    'flash' => [
        'saved' => ':slot saved.',
        'pitch_saved' => 'Pitch saved.',
        'removed' => ':slot removed.',
    ],
];
