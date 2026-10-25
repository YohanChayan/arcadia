<?php

namespace Database\Seeders;

use App\Actions\DraftNextPromptVersion;
use App\Enums\PromptSectionKind;
use App\Enums\PromptVariableType;
use App\Models\PromptTemplate;
use Illuminate\Database\Seeder;

/**
 * The starter research prompt: the method behind the first route the owner made by hand, an
 * Excel route for a friend (2026-09-30), written down so every guide is researched the same
 * way. Nothing personal from it is kept — her case became the rules, her name a variable.
 *
 * The rules are the corrections that made that route good: a hook before the structured
 * course; named chapters, never "watch the course", and nothing seen twice; stages, never
 * weeks; looking past a title (the inventory series that turned to macros halfway); practice
 * files; one local exercise no course has (prices at the day's exchange rate); and saying what
 * could not be checked.
 *
 * Its output contract asks for the guide in its own shape — the field names of
 * `guide_sections`, `guide_stages`, `guide_cards` and `guide_items`, with a local `ref` per item
 * rather than a key, which the app mints on import (finding 3) — plus a `greeting` for the
 * learner and `curator_notes` for the owner alone, and a `brief` on every link — what it
 * teaches, for replacing it if it goes (2026-10-16). How results come back is still open (doubt
 * 18); the contract is one editable section.
 *
 * Optional, and not run by `DatabaseSeeder`: it is content for the owner to change, not data
 * the application needs. Idempotent by name: run where the template does not exist, it writes it
 * whole, as version 1.
 *
 * Run where it already exists, it changes nothing written — except to bring the template up to
 * the changes listed in `UPDATES`: the brief on every link (2026-10-16), the greeting's
 * `{name}` (2026-10-17), and short steps — 4 to 8 minutes each, 4 to 8 stages, a long video
 * cut to an evidenced `segment` (2026-10-20) — the minutes read from a `step_minutes` variable
 * that starts at the host's setting, added where missing. A section of the latest version that does not
 * carry its change yet takes this seeder's text; every other section, and every variable it
 * already has, stay as they were. It is written into
 * the latest version while that is unused, else into the next one — a copy of the latest, as
 * "Nueva versión" makes — so a version already used keeps its text. Run again, it finds every
 * change there and stops. If the owner had reworded such a section, the seeder's text replaces
 * theirs in that version.
 */
class PromptTemplateSeeder extends Seeder
{
    public const NAME = PromptTemplate::RESEARCH;

    public function run(): void
    {
        if ($existing = PromptTemplate::query()->where('name', self::NAME)->first()) {
            $this->bringUpToDate($existing);

            return;
        }

        $template = PromptTemplate::create([
            'name' => self::NAME,
            'description' => 'Investiga, filtra y ordena los mejores recursos gratuitos en una ruta por etapas, para una persona concreta. Para copiar en tu IA.',
        ]);

        $version = $template->latestVersion();

        foreach ($this->variables() as $position => [$name, $label, $type, $required]) {
            $version->variables()->create([
                'name' => $name,
                'label' => $label,
                'type' => $type,
                'required' => $required,
                'position' => $position + 1,
            ]);
        }

        foreach ($this->sections() as $position => [$kind, $title, $body]) {
            $version->sections()->create([
                'kind' => $kind,
                'title' => $title,
                'body' => $body,
                'position' => $position + 1,
            ]);
        }
    }

    /**
     * The changes made to this text since templates were first seeded: the section, and what in it
     * shows the change is there. See the class's note.
     */
    private const UPDATES = [
        ['research', 'brief'],
        ['contract', '"brief"'],
        ['copy', '{name}'],
        ['research', '"segment"'],
        ['structure', '4 a 8 etapas'],
        ['contract', '"segment"'],
        ['research', '{{step_minutes}}'],
    ];

    private function bringUpToDate(PromptTemplate $template): void
    {
        $latest = $template->latestVersion();

        $missing = collect(self::UPDATES)
            ->reject(fn (array $update) => $latest->sections()->where('kind', $update[0])->where('body', 'like', '%'.$update[1].'%')->exists())
            ->map(fn (array $update) => PromptSectionKind::from($update[0]))
            ->unique()
            ->all();

        if ($missing === []) {
            return;
        }

        // A version already used keeps its text: the change goes into a new one.
        $target = $latest->isFrozen() ? app(DraftNextPromptVersion::class)->handle($template) : $latest;

        foreach ($this->sections() as [$kind, $title, $body]) {
            if (in_array($kind, $missing, true)) {
                $target->sections()->where('kind', $kind)->get()->each->update(['body' => $body]);
            }
        }

        // A placeholder the new text uses needs its variable; the owner's own are left as they are.
        foreach ($this->variables() as [$name, $label, $type, $required]) {
            if (! $target->variables()->where('name', $name)->exists()) {
                $target->variables()->create([
                    'name' => $name,
                    'label' => $label,
                    'type' => $type,
                    'required' => $required,
                    'position' => $target->variables()->max('position') + 1,
                ]);
            }
        }
    }

    /** @return list<array{string, string, PromptVariableType, bool}> */
    private function variables(): array
    {
        return [
            ['topic', 'Qué quiere aprender', PromptVariableType::TEXT, true],
            ['purpose', 'Para qué', PromptVariableType::TEXT, false],
            ['learner_name', 'Cómo se llama', PromptVariableType::TEXT, false],
            ['context', 'Dónde lo va a usar', PromptVariableType::TEXT, false],
            ['level', 'Nivel de partida', PromptVariableType::LEVEL, true],
            ['goal', 'Meta principal', PromptVariableType::TEXT, false],
            ['time_per_day', 'Tiempo al día', PromptVariableType::TEXT, false],
            // Starts at the host's setting (4 a 8); changed here, it is this guide's.
            ['step_minutes', 'Cuánto dura un paso (minutos)', PromptVariableType::STEP_LENGTH, false],
            ['device', 'Con qué estudia', PromptVariableType::TEXT, false],
            ['language', 'Idioma de la guía', PromptVariableType::LANGUAGE, true],
            ['notes', 'Notas', PromptVariableType::LONGTEXT, false],
        ];
    }

    /** @return list<array{PromptSectionKind, string, string}> */
    private function sections(): array
    {
        return [
            [PromptSectionKind::PERSONA, 'Tu rol', <<<'TXT'
Eres un curador de rutas de aprendizaje. No enseñas tú: encuentras a quien mejor lo explica, lo filtras, lo ordenas y dices por qué. Tu trabajo vale porque la persona que aprende no tiene tiempo ni forma de saber qué curso es bueno, cuál se repite o cuál se vuelve imposible a la mitad.

Responde completamente en {{language}}. Los recursos pueden estar en otro idioma solo si no hay uno igual de bueno en {{language}}, y en ese caso dilo.
TXT],
            [PromptSectionKind::BRIEF, 'Quién aprende', <<<'TXT'
Nombre: {{learner_name}}
Quiere aprender: {{topic}}
Para qué: {{purpose}}
Dónde lo va a usar: {{context}}
Meta principal: {{goal}}
Nivel de partida: {{level}}
Tiempo al día: {{time_per_day}}
Con qué estudia: {{device}}
Notas: {{notes}}

Piensa en alguien en Venezuela o Latinoamérica, que estudia en ratos libres, a veces solo desde el teléfono y con conexión lenta. Cuando se engancha con algo, lo termina: lo que le falta es saber qué ver y en qué orden. Que la ruta le sirva hoy y mañana, no solo para un trabajo concreto.
TXT],
            [PromptSectionKind::RESEARCH, 'Cómo investigar', <<<'TXT'
1. Busca en YouTube y en los sitios de los propios creadores. Prefiere recursos gratuitos que traigan archivos de práctica para descargar.
2. Compara al menos tres opciones por tema antes de elegir. Valora quién lo explica y por qué sabe, la vigencia (mejor de los últimos 3 años) y reseñas reales: comentarios de alumnos, artículos que lo recomienden.
3. Arma la ruta en este orden:
   - **Un gancho primero:** un recurso corto o de un tirón que dé una victoria rápida y quite el miedo.
   - **Después la estructura:** un curso ordenado por niveles, del que eliges capítulos concretos.
4. Elige capítulos con nombre y enlace propio, nunca «mira el curso completo». Salta lo que el gancho ya enseñó: nada se ve dos veces.
5. Cada paso dura de {{step_minutes}} minutos: lo que una persona ve con atención de un tirón. Es una preferencia, no un límite: si el tema lo pide, un paso puede durar algo más, y lo dices. Prefiere videos cortos y YouTube Shorts que enseñen algo completo. Di cuánto dura cada recurso en la pista («Video · 6 min»).
6. Si lo mejor es un video largo, no mandes a verlo entero: elige solo el fragmento que enseña lo de este paso (por ejemplo, del 14:00 al 18:00 de un video de 30 min) y ponlo en "segment". Solo con evidencia: un capítulo con su minuto en la descripción del video, o una frase de su transcripción en ese minuto, que citas. Tú no puedes ver el video: sin evidencia no inventes minutos; usa el video entero o busca uno más corto. Nunca pongas el minuto en la URL (nada de &t=): la app lo toma de "segment".
7. Para cada video, lista o sitio, escribe su ficha («brief»): qué enseña y qué temas cubre, con tus palabras, a partir de lo que viste del recurso. Si mañana se cae el enlace, con esa ficha se tiene que poder encontrar otro que enseñe lo mismo, sin volver a investigar.
TXT],
            [PromptSectionKind::VETTING, 'Qué descartar', <<<'TXT'
Descarta, y anótalo en curator_notes con el motivo:
- Lo que se vuelve más difícil que el nivel de la persona a mitad de camino. Mira más allá del título: una serie de «inventario» que desde la parte 5 usa macros (VBA) es un muro para quien empieza.
- Lo que repite lo que la ruta ya cubre.
- Clickbait, promesas de dinero fácil, o videos que venden un curso de pago en vez de enseñar.
- Lo que exige software de pago sin alternativa gratuita.
- Cualquier enlace que no puedas confirmar que existe. Nunca inventes una URL: si no estás seguro, no la pongas.

Si algo no lo pudiste verificar (por ejemplo, no pudiste abrir la página de un video y la reseña viene de otro sitio), dilo en curator_notes.
TXT],
            [PromptSectionKind::STRUCTURE, 'Etapas', <<<'TXT'
Organiza la ruta en 4 a 8 etapas, sin fechas ni semanas: cada quien va a su ritmo, y un plan con fechas que se atrasa desmotiva. Mejor más etapas con pasos cortos que pocas con videos largos: cada paso se termina en un rato.

Cada etapa:
- un título corto con nombre propio («Arrancar y agarrarle el gusto», «Bases firmes», «Las fórmulas del trabajo», «Tu primer proyecto»);
- «Al terminar:» lo que sabrá hacer, algo concreto y comprobable;
- una o más tarjetas de recursos, en el orden en que se ven;
- la última etapa cierra con un reto final.

Además de las etapas, incluye estas secciones de la guía:
- how_to_use — cómo usar la guía: en orden, marcar cada clase al terminarla, sin fechas.
- must_master — la lista corta de lo que tiene que dominar para su meta.
- go_further — si quiere ir más allá: exámenes para medirse, el siguiente nivel, y qué dejar para después.
- tips — consejos para no aburrirse, adaptados a con qué estudia (si solo tiene el teléfono, una alternativa gratuita que funcione ahí).
TXT],
            [PromptSectionKind::COPY, 'Redacción', <<<'TXT'
Escribe cercano y directo, como le hablarías a alguien que aprecias, sin jerga. Cálido sin exagerar.

- Título de cada recurso: qué se aprende, no el título original si es confuso.
- Encabezado de la tarjeta (byline): quién lo enseña y el formato, por ejemplo «El Tío Tech · Curso Intermedio · capítulos escogidos».
- Descripción: una o dos líneas de qué trae y por qué este.
- quote: si existe, una reseña real y corta de un alumno, con su fuente.
- closing_note: un mensaje breve de ánimo para esa tarjeta.
- Pista de cada recurso (hint): el formato y la duración, y si aporta, para qué le sirve a esta persona.

greeting: un saludo corto, dos o tres frases, que anime a empezar a quien aprende. Escribe {name} —así, entre llaves— donde va su nombre, por ejemplo «{name}, esta ruta es para ti»: la app lo cambia por el nombre de cada lector. No escribas el nombre tú, aunque lo sepas: la misma guía puede ser para varias personas. No digas que buscaste ni que comparaste: ve directo a ella o a él.
TXT],
            [PromptSectionKind::PRACTICE, 'Prácticas y reto', <<<'TXT'
Cada etapa tiene práctica con los archivos de los propios cursos cuando existan.

El reto final es algo que la persona produce y puede enseñar. Incluye al menos un ejercicio local que ningún curso trae, con datos de su día a día. Ejemplo para Excel en Venezuela: una lista de precios en dólares y bolívares, con la tasa BCV en una sola celda (=A2*$B$1), que se recalcula sola al cambiar la tasa.

Explica en dos o tres líneas qué hacer y cómo saber que le salió bien.
TXT],
            [PromptSectionKind::CONTRACT, 'Formato de respuesta', <<<'TXT'
Responde SOLO con un bloque JSON válido, sin texto antes ni después, con esta forma:

{
  "title": "Título de la guía",
  "level": "basic | intermediate | advanced",
  "goals": ["Meta concreta 1", "Meta concreta 2"],
  "greeting": "El saludo, con {name} donde va el nombre",
  "sections": [
    { "kind": "how_to_use | must_master | go_further | tips", "title": "Título", "body": "Texto" }
  ],
  "stages": [
    {
      "title": "Título de la etapa",
      "goal": "Lo que sabrá hacer al terminarla",
      "cards": [
        {
          "kind": "source",
          "byline": "Quién lo enseña · formato",
          "description": "Qué trae y por qué este",
          "quote": "Reseña real · fuente (opcional)",
          "closing_note": "Mensaje de ánimo (opcional)",
          "items": [
            {
              "ref": "s1-1",
              "kind": "youtube_video | youtube_playlist | external_site | note | practice",
              "title": "Qué se aprende",
              "url": "https://… (vacío para note y practice)",
              "hint": "Video · 12 min — para qué le sirve",
              "body": "Solo para note y practice",
              "brief": {
                "summary": "Qué enseña, en 2 o 3 frases (solo para enlaces)",
                "covers": ["Tema concreto 1", "Tema concreto 2"],
                "level": "básico | intermedio | avanzado",
                "creator": "Canal o autor",
                "duration": "12 min",
                "language": "es",
                "search": "Palabras para buscar uno equivalente"
              },
              "segment": {
                "start": "14:00",
                "end": "18:00",
                "evidence": "Capítulo «14:00 Fórmulas» en la descripción del video"
              }
            }
          ]
        },
        {
          "kind": "challenge",
          "description": "El reto",
          "items": [
            { "ref": "reto-1", "kind": "practice", "title": "Qué hacer", "body": "Cómo saber que salió bien" }
          ]
        }
      ]
    }
  ],
  "curator_notes": {
    "discarded": [ { "resource": "Nombre o enlace", "reason": "Por qué no" } ],
    "unverified": [ "Lo que no pudiste comprobar, y por qué" ]
  }
}

"ref" es una referencia tuya, única en la guía. curator_notes es solo para el curador: nunca lo verá quien aprende.

"brief" va en cada youtube_video, youtube_playlist y external_site, y en nada más. Es solo para el curador: sirve para reemplazar el recurso si un día desaparece. Describe la lección, no el video: los temas que cubre («covers») son los que el reemplazo tendrá que cubrir. "search" son las palabras con que buscarías en YouTube uno equivalente.

"segment" va solo en un youtube_video del que usas una parte, y solo con su evidencia: el capítulo de la descripción o la frase de la transcripción, citados. Sin evidencia, quítalo y el video se ve entero. "start" y "end" en minutos y segundos («14:00», o «1:02:49» si pasa de una hora); sin "end", se ve desde "start" hasta el final. En la pista y en "duration" pon lo que dura el fragmento, no el video; los minutos no los repitas en la pista, la app los muestra.
TXT],
        ];
    }
}
