<?php

namespace Database\Seeders;

use App\Enums\PromptSectionKind;
use App\Enums\PromptVariableType;
use App\Models\PromptTemplate;
use Illuminate\Database\Seeder;

/**
 * The quick-check prompt: what the owner gives ChatGPT or Gemini to write one to three questions
 * for each video of a guide — the ones a reader answers right to tick it (`ActivityFamily`). Its
 * answer is imported with "Importar actividades" (`ActivityImport`), and it is written to the
 * shape that import checks: the family, then each video by its `key`, exactly as it was given.
 *
 * The videos come in `{{guide_content}}`, put in by the server from the guide the prompt is opened
 * on (`ActivityPromptContent`): each one's key, title, stage, the brief's summary and the topics it
 * covers. The AI cannot watch a video, so **every question rests on the brief alone**, and quotes
 * the words it rests on (`evidence`) for the owner to check. Questions are for someone who is not
 * used to studying: what the video is for and what to do, never a trick or a detail to memorise.
 *
 * Optional, and not run by `DatabaseSeeder`: content for the owner to change. Idempotent by name:
 * run where the template exists, it writes nothing.
 */
class QuickCheckPromptTemplateSeeder extends Seeder
{
    public const NAME = PromptTemplate::QUICK_CHECK;

    private const DESCRIPTION = 'Preguntas rápidas para cada video de una guía, para ChatGPT o Gemini: de 1 a 3 por video, basadas solo en su resumen. Se importan con «Importar actividades».';

    public function run(): void
    {
        if (PromptTemplate::query()->where('name', self::NAME)->exists()) {
            return;
        }

        $template = PromptTemplate::create(['name' => self::NAME, 'description' => self::DESCRIPTION]);
        $version = $template->latestVersion();
        $version->update(['description' => 'Primera versión: basadas solo en el resumen de cada video.']);

        foreach ($this->variables() as $position => [$name, $label, $type, $required, $default]) {
            $version->variables()->create([
                'name' => $name,
                'label' => $label,
                'type' => $type,
                'required' => $required,
                'default_value' => $default,
                'position' => $position + 1,
            ]);
        }

        foreach ($this->sections() as $position => [$kind, $title, $body]) {
            $version->sections()->create(['kind' => $kind, 'title' => $title, 'body' => $body, 'position' => $position + 1]);
        }
    }

    /** @return list<array{string, string, PromptVariableType, bool, ?string}> */
    private function variables(): array
    {
        return [
            ['title', 'Título de la guía', PromptVariableType::TEXT, true, null],
            ['level', 'Nivel', PromptVariableType::LEVEL, true, null],
            ['language', 'Idioma', PromptVariableType::LANGUAGE, true, null],
            ['questions', 'Preguntas por video', PromptVariableType::TEXT, true, '1 o 2'],
        ];
    }

    /** @return list<array{PromptSectionKind, string, string}> */
    private function sections(): array
    {
        return [
            [PromptSectionKind::PERSONA, 'Tu rol', <<<'TXT'
Eres un profesor paciente que escribe para Arcadia, un sitio de guías de aprendizaje por etapas, con los mejores videos gratuitos, para seguir en el celular. Quien sigue la guía «{{title}}» (nivel {{level}}) no está acostumbrado a estudiar: aprende para usarlo en su trabajo o en su vida, no para un examen.

Después de cada video le haces una pregunta rápida. Si la responde bien, el video queda marcado como visto y avanza. Su propósito no es atraparlo ni medirlo: es que confirme lo importante del video y se lleve una idea clara, con la satisfacción de acertar.
TXT],
            [PromptSectionKind::BRIEF, 'Lo que vas a hacer', <<<'TXT'
Escribe {{questions}} preguntas para cada video de la lista de abajo. Cada pregunta tiene:

- un enunciado corto, en segunda persona («¿Qué haces primero…?»);
- de 2 a 4 opciones, solo una correcta, todas creíbles y del mismo largo aproximado;
- una explicación de una o dos frases, que se muestra si responde mal: dice por qué la correcta lo es, sin regañar;
- la evidencia: las palabras exactas del resumen o de los temas del video en las que se basa la pregunta.
TXT],
            [PromptSectionKind::VETTING, 'Lo que no puedes hacer', <<<'TXT'
No puedes ver los videos. Solo sabes lo que dicen su resumen («summary») y sus temas («covers»). Por eso:

- Cada pregunta se basa **solo** en ese resumen y esos temas. Si un detalle no está ahí, no lo preguntes, aunque creas que el video lo dice.
- Nada de fechas, cifras, nombres ni pasos que no aparezcan en el resumen.
- Si de un video no sale una buena pregunta, escribe menos, pero nunca inventes.
- Nada de trampas, dobles negaciones, «todas las anteriores» ni «ninguna de las anteriores».
- La respuesta correcta no siempre va en la misma posición.
TXT],
            [PromptSectionKind::COPY, 'Cómo se escribe', <<<'TXT'
Escribe en {{language}}, con palabras sencillas, como se habla. Frases cortas. Pregunta por lo que se hace o para qué sirve, no por definiciones de memoria.
TXT],
            [PromptSectionKind::CONTRACT, 'Cómo me lo devuelves', <<<'TXT'
Responde solo con un objeto JSON, sin texto antes ni después, con esta forma exacta:

```json
{
  "family": "quick_check",
  "activities": [
    {
      "key": "la clave del video, copiada tal cual",
      "questions": [
        {
          "prompt": "el enunciado",
          "options": ["opción", "opción", "opción"],
          "correct": 0,
          "explanation": "por qué la correcta lo es",
          "evidence": "las palabras del resumen o de los temas en que se basa"
        }
      ]
    }
  ]
}
```

- `family` es siempre `quick_check`.
- `key` es la clave de cada video tal como aparece abajo: letra por letra, sin cambiarla. Un video, una entrada.
- `correct` es la posición de la opción correcta, contando desde 0 (la primera es 0).
- No agregues otros campos.

## Los videos

{{guide_content}}
TXT],
        ];
    }
}
