<?php

namespace App\Enums;

/**
 * What one section of a research prompt does, in the order a prompt usually runs: who the AI is,
 * who the learner is, how to research, what to reject, how to shape the guide, how to word it,
 * what to practise — and last, the output contract, the shape the answer must come back in.
 */
enum PromptSectionKind: string
{
    case PERSONA = 'persona';
    case BRIEF = 'brief';
    case RESEARCH = 'research';
    case VETTING = 'vetting';
    case STRUCTURE = 'structure';
    case COPY = 'copy';
    case PRACTICE = 'practice';
    case CONTRACT = 'contract';
}
