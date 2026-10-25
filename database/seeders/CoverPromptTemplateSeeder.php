<?php

namespace Database\Seeders;

use App\Actions\DraftNextPromptVersion;
use App\Enums\PromptSectionKind;
use App\Enums\PromptVariableType;
use App\Models\PromptTemplate;
use Illuminate\Database\Seeder;

/**
 * The starter prompt for a guide's cover: what the owner gives ChatGPT or Gemini to make the one
 * landscape image every surface is cut from (`ImageSlot::COVER`, ADR 0022).
 *
 * It asks for a **proportion, never pixels** — each tool sizes its output its own way, and the
 * owner sizes it himself — inside the upload rule (landscape, 3:2 to 16:9); for the subject in the
 * central third, the sides and the top and bottom bands left as background, so the share card,
 * the hero and the square thumbnail all keep it; and for **no text and no brand at all**.
 *
 * **No reference image** (2026-10-18). The first text attached the landing photograph as a style
 * reference, and the model redrew it — the walker on the hill at sunset — for every topic, with the
 * topic's logos as props. The scene now comes from the topic: the concrete moment of doing or
 * enjoying it, never a generic metaphor. What makes the covers one family is a **house look in
 * words** — a deep navy ambient, one teal or cyan accent light, a clean background — on whatever
 * subject the topic needs, in the style the owner picks (`style`: a realistic photo by default).
 *
 * **Motivation, shown rather than named** (2026-10-18, the second approach). The house look alone
 * read as calm and nocturnal: a navy ambient, a cold accent, "calm and focus", and people only from
 * behind. The cover has to make someone want to start, so the scene is now **the moment it comes
 * out right** — the payoff the guide leads to — with warm light on the subject, a sense of
 * movement, and a person allowed in profile with a natural expression. Asking for "happy" or
 * "motivational" in words gets stock smiles, so those are forbidden by name instead. The navy
 * stays at the edges, so the covers are still one family.
 *
 * Optional, and not run by `DatabaseSeeder`, as `PromptTemplateSeeder` is not: content for the
 * owner to change. Idempotent by name. Run where the template exists, it brings it up to this
 * text once — the sections, and the `style` blank — in its latest version while that is unused,
 * else in a new one, so a version already used keeps its text; `REVISION` in the role's text is
 * how it knows. Run again, it changes nothing, and the owner's later edits stay.
 */
class CoverPromptTemplateSeeder extends Seeder
{
    public const NAME = PromptTemplate::COVER;

    /** What in the role's text shows a version carries this seeder's current text. */
    private const REVISION = 'el momento en que sale bien';

    /** What this text is, on the version it lands in — the label the Presentation screen shows. */
    private const VERSION_DESCRIPTION = 'Motivación: el momento en que sale bien, luz cálida, sin texto ni marcas.';

    private const DESCRIPTION = 'La imagen de portada de una guía, para ChatGPT o Gemini: horizontal, sin texto ni marcas, con el tema de la guía al centro, el aspecto Arcadia y una escena que dé ganas de empezar.';

    public function run(): void
    {
        if ($existing = PromptTemplate::query()->where('name', self::NAME)->first()) {
            $this->bringUpToDate($existing);

            return;
        }

        $template = PromptTemplate::create([
            'name' => self::NAME,
            'description' => self::DESCRIPTION,
        ]);

        $version = $template->latestVersion();
        $version->update(['description' => self::VERSION_DESCRIPTION]);

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

    private function bringUpToDate(PromptTemplate $template): void
    {
        $latest = $template->latestVersion();

        if ($latest->sections()->where('kind', PromptSectionKind::PERSONA)->where('body', 'like', '%'.self::REVISION.'%')->exists()) {
            return;
        }

        // A version already used keeps its text: the change goes into a new one.
        $target = $latest->isFrozen() ? app(DraftNextPromptVersion::class)->handle($template) : $latest;
        $target->update(['description' => self::VERSION_DESCRIPTION]);
        $template->update(['description' => self::DESCRIPTION]);

        foreach ($this->sections() as [$kind, $title, $body]) {
            $sections = $target->sections()->where('kind', $kind)->get();

            $sections->isEmpty()
                ? $target->sections()->create(['kind' => $kind, 'title' => $title, 'body' => $body, 'position' => $target->sections()->max('position') + 1])
                : $sections->each->update(['title' => $title, 'body' => $body]);
        }

        foreach ($this->variables() as [$name, $label, $type, $required]) {
            $target->variables()->firstOrCreate(
                ['name' => $name],
                ['label' => $label, 'type' => $type, 'required' => $required, 'position' => $target->variables()->max('position') + 1],
            );
        }
    }

    /** @return list<array{string, string, PromptVariableType, bool}> */
    private function variables(): array
    {
        return [
            ['title', 'Título de la guía', PromptVariableType::TEXT, true],
            ['level', 'Nivel', PromptVariableType::LEVEL, true],
            ['category', 'Categoría', PromptVariableType::CATEGORY, false],
            ['pitch', 'Presentación breve', PromptVariableType::LONGTEXT, false],
            ['goals', 'Metas (una por línea)', PromptVariableType::LONGTEXT, false],
            ['scene', 'Idea de escena', PromptVariableType::TEXT, false],
            ['style', 'Estilo (vacío: fotografía realista)', PromptVariableType::TEXT, false],
        ];
    }

    /** @return list<array{PromptSectionKind, string, string}> */
    private function sections(): array
    {
        return [
            [PromptSectionKind::PERSONA, 'Tu rol', <<<'TXT'
Eres el director de arte de Arcadia, un sitio de guías de aprendizaje: rutas por etapas con los mejores videos gratuitos, para seguir en el celular o la computadora. Vas a crear la imagen de portada de una guía. La ve primero alguien que todavía no la tiene, y tiene que hacer dos cosas de un vistazo: decirle de qué trata y hacerle sentir «yo puedo lograr esto, y se siente bien».

Esa sensación no se explica, se muestra: la portada captura el momento en que sale bien — el resultado al que lleva la guía, la satisfacción de haberlo hecho. No el esfuerzo, no la espera: la recompensa.

Todas las portadas de Arcadia comparten el aspecto Arcadia (abajo, en «Estilo y color»), pero cada una muestra su propio tema: dos guías distintas nunca deben parecer la misma escena.
TXT],
            [PromptSectionKind::BRIEF, 'La guía', <<<'TXT'
Título: {{title}}
Nivel: {{level}}
Categoría: {{category}}
De qué trata: {{pitch}}
Lo que la persona podrá hacer:
{{goals}}
Idea de escena (si la hay): {{scene}}

Antes de crear, elige la escena: el instante concreto en que lo que enseña esta guía ya funciona, en su lugar natural — sacado de las metas de arriba si las hay. No una metáfora: nada de horizontes, cimas, escaleras, caminos, bombillas ni personas mirando a lo lejos; sirven para cualquier guía y por eso no dicen nada de esta. Si hay una idea de escena, parte de ella y llévala a ese instante.

Algunos ejemplos del tipo de escena, no para copiar:
- Cocinar: unas manos terminando de emplatar un plato que salió bien, el vapor subiendo, la cocina cálida y viva alrededor.
- Excel: un escritorio de mañana, una laptop con un gráfico abstracto de barras que sube, sin nada legible; alguien de perfil se echa atrás en la silla, satisfecho, con el café en la mano.
- Criptomonedas: monedas genéricas brillantes, sin símbolos reales, y un celular con una curva abstracta al alza, en una mano relajada.
- Guitarra: los dedos a mitad de un acorde, las cuerdas vibrando con un leve desenfoque de movimiento, la luz cálida sobre la madera.
TXT],
            [PromptSectionKind::STRUCTURE, 'Composición', <<<'TXT'
- Imagen horizontal, en proporción 3:2 o 16:9. Si la herramienta deja elegir la proporción, elige una de esas dos. No hace falta un tamaño en píxeles.
- La imagen se recorta en tres formas: una tarjeta ancha para la vista previa del enlace, la imagen completa arriba de la guía y un cuadrado pequeño, de unos 80 píxeles, en la lista de guías del lector. Por eso:
  - El tema principal va centrado y ocupa el tercio central de la imagen, completo.
  - Los dos costados y las franjas de arriba y abajo son solo fondo: nada importante ahí, ni siquiera un objeto secundario.
- Un solo protagonista — una persona, unas manos o un objeto —, grande y nítido, que se entienda también en el cuadrado de 80 píxeles. Fondo limpio y desenfocado, sin detalle que compita.
- Una persona solo si el tema la pide; muchas veces unas manos o los objetos cuentan más. Si aparece, es inventada y no mira a cámara: de perfil o de tres cuartos, con una expresión natural — concentración que se resuelve, una media sonrisa de satisfacción —, nunca alguien real o reconocible.
TXT],
            [PromptSectionKind::COPY, 'Estilo y color', <<<'TXT'
Estilo: {{style}} — si está vacío, fotografía realista, cinematográfica, con poca profundidad de campo. Puede ser también, por ejemplo, ilustración 3D o ilustración editorial, si el tema se ve mejor así.

El aspecto Arcadia, en cualquier estilo:
- Ambiente de azul marino profundo (#030D1C) en los bordes y el fondo: es el color sobre el que se muestra la imagen.
- El protagonista bañado en luz cálida — dorada, de mañana o de lámpara —, con sus colores propios: es lo más luminoso de la imagen.
- Una luz de acento en verde azulado (#2EE6B8) o cian (#2DD6F5) — un contraluz, un reflejo, un brillo — que lo recorte del fondo. El acento no tiñe toda la imagen.
- Energía y avance: algo que se mueve o acaba de pasar — vapor, una cuerda que vibra, una barra que sube —, y un tono optimista, nunca solemne, frío ni nocturno.
- Una sola cosa importante, bien iluminada, y aire alrededor.
TXT],
            [PromptSectionKind::VETTING, 'Lo que no debe tener', <<<'TXT'
- Ningún texto: ni letras, ni números, ni palabras en pantallas, carteles, libros, etiquetas o ropa. El título lo pone la página.
- Ninguna marca: todo objeto es genérico e inventado. Una moneda es un disco liso o con un símbolo inventado — nunca Bitcoin, Tether, Binance ni otra real —; una pantalla muestra formas abstractas — nunca Excel, Canva ni otra app reconocible —; un producto no lleva logo.
- Nada de motivación de banco de imágenes: puños en alto, saltos, brazos abiertos, pulgares arriba, choques de manos, confeti, trofeos ni sonrisas forzadas a cámara. La alegría está en la escena, no en la pose.
- Nada de marcos, bordes, marcas de agua, collage, varias escenas en una ni estilo clip-art.
- Nada de caras de personas reales o famosas, ni manos deformes.
TXT],
            [PromptSectionKind::CONTRACT, 'Qué entregar', <<<'TXT'
Una sola imagen horizontal (3:2 o 16:9), sin texto ni marcas, con el tema centrado en el tercio central, en JPG o PNG.
TXT],
        ];
    }
}
