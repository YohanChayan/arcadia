<?php

namespace Database\Seeders;

use App\Enums\PromptSectionKind;
use App\Enums\PromptVariableType;
use App\Models\PromptTemplate;
use Illuminate\Database\Seeder;

/**
 * The flip-card prompt: what the owner gives ChatGPT or Gemini to turn a guide's "must master"
 * list into a deck — a front to read, a back to check — that a reader goes through on the guide
 * page, saying whether they knew each card (`ActivityFamily::FLIP_CARDS`). Its answer comes back
 * through "Importar respuesta" → Actividades, in the shape that import checks, the list by its `key`.
 *
 * The list comes in `{{guide_content}}`, put in by the server (`ActivityPromptContent`), with each
 * stage's goal for context. **Three kinds of card on one front/back shape** (2026-10-27): a concept
 * (a term and what it means), a rule (what you always or never do), and a situation (what happens,
 * and what you do) — the last is what makes a deck work for guides about people and selling, where
 * there is little to define. **Never forced**: a list that is pure doing gets a short deck, and one
 * that yields fewer than three honest cards gets none.
 *
 * Optional, and not run by `DatabaseSeeder`: content for the owner to change. Idempotent by name:
 * run where the template exists, it writes nothing.
 */
class FlipCardsPromptTemplateSeeder extends Seeder
{
    public const NAME = PromptTemplate::FLIP_CARDS;

    private const DESCRIPTION = 'Tarjetas para repasar «Lo que debes dominar» de una guía, para ChatGPT o Gemini: concepto, regla o situación, de frente y dorso. Se importan con «Importar respuesta» → Actividades.';

    public function run(): void
    {
        if (PromptTemplate::query()->where('name', self::NAME)->exists()) {
            return;
        }

        $template = PromptTemplate::create(['name' => self::NAME, 'description' => self::DESCRIPTION]);
        $version = $template->latestVersion();
        $version->update(['description' => 'Primera versión: concepto, regla o situación; nunca forzadas.']);

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
            ['cards', 'Cuántas tarjetas, como máximo', PromptVariableType::TEXT, true, '12'],
        ];
    }

    /** @return list<array{PromptSectionKind, string, string}> */
    private function sections(): array
    {
        return [
            [PromptSectionKind::PERSONA, 'Tu rol', <<<'TXT'
Eres un profesor paciente que escribe para Arcadia, un sitio de guías de aprendizaje por etapas, para seguir en el celular. Quien sigue la guía «{{title}}» (nivel {{level}}) no está acostumbrado a estudiar: aprende para usarlo en su trabajo o en su vida.

Al final de la guía repasa con tarjetas: lee el frente, piensa la respuesta, voltea la tarjeta y dice si se la sabía. Las que no, le vuelven al día siguiente. Tiene que sentirse como un juego corto, no como un examen.
TXT],
            [PromptSectionKind::BRIEF, 'Lo que vas a hacer', <<<'TXT'
Convierte la lista «Lo que debes dominar» de abajo en tarjetas, como máximo {{cards}}. Cada tarjeta es de uno de estos tres tipos, el que mejor le quede a cada punto de la lista:

- **Concepto**: un término y lo que significa. Frente: «¿Qué es el signo $ en una fórmula?» — Dorso: «Fija la celda al copiar: =B2*$E$1».
- **Regla**: lo que siempre o nunca se hace. Frente: «¿Cuándo haces la prueba de alergia del tinte?» — Dorso: «48 horas antes, siempre».
- **Situación**: lo que pasa y lo que haces. Frente: «El cliente te dice "está caro"» — Dorso: «Valida primero; luego muestra lo que gana con el producto».

Mezcla los tipos según la guía: en una de fórmulas o reglas abundan los conceptos; en una de trato con personas o de ventas, las situaciones; en una de oficio con las manos, las reglas y las situaciones («Se te corta el hilo al coser: ¿qué revisas?»).
TXT],
            [PromptSectionKind::VETTING, 'Lo que no puedes hacer', <<<'TXT'
- Cada tarjeta sale de la lista. No agregues nada que la lista no diga.
- Nada forzado: si un punto es puro hacer y no da para una pregunta honesta, sáltalo. Mejor pocas tarjetas buenas que muchas de relleno.
- Si de toda la lista no salen al menos 3 tarjetas útiles, no inventes: responde solo «sin tarjetas».
- Ni trampas ni preguntas de memoria sin sentido («¿cuál es el punto 4?»).
TXT],
            [PromptSectionKind::COPY, 'Cómo se escribe', <<<'TXT'
Escribe en {{language}}, con palabras sencillas, como se habla. El frente, una pregunta o una situación corta. El dorso, la respuesta en una o dos frases, que se lea de un vistazo en el celular.
TXT],
            [PromptSectionKind::CONTRACT, 'Cómo me lo devuelves', <<<'TXT'
Responde solo con un objeto JSON, sin texto antes ni después, con esta forma exacta:

```json
{
  "family": "flip_cards",
  "activities": [
    {
      "key": "la clave de la lista, copiada tal cual",
      "cards": [
        { "front": "la pregunta o la situación", "back": "la respuesta" },
        { "front": "…", "back": "…" },
        { "front": "…", "back": "…" }
      ]
    }
  ]
}
```

- `family` es siempre `flip_cards`.
- `key` es la clave de la lista («must_master») tal como aparece abajo: letra por letra, sin cambiarla. Una sola entrada.
- No agregues otros campos.

## La lista, y las etapas de la guía

{{guide_content}}
TXT],
        ];
    }
}
