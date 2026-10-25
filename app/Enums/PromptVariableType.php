<?php

namespace App\Enums;

/**
 * What a prompt variable holds, which decides its field in the fill-in form: free text, a long
 * text, or a choice — a level, a language, or one of this language's categories. A goal is free text
 * since goals stopped being a vocabulary (ADR 0020). A price is text that starts at the price this
 * language's settings offer a guide at, so a flyer never quotes a stale one. A phone is text rendered
 * grouped with dashes (`0412-802-3555`), so an image shows it legibly without the model regrouping
 * — and possibly miscopying — its digits. A step length is text that starts at the minutes this
 * host's settings ask a step to take (`4 a 8`), so a guide is researched to the current rule unless
 * its prompt is filled with another.
 */
enum PromptVariableType: string
{
    case TEXT = 'text';
    case LONGTEXT = 'longtext';
    case LEVEL = 'level';
    case LANGUAGE = 'language';
    case CATEGORY = 'category';
    case PRICE = 'price';
    case PHONE = 'phone';
    case STEP_LENGTH = 'step_length';
}
