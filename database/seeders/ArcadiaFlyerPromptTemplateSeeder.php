<?php

namespace Database\Seeders;

use App\Actions\DraftNextPromptVersion;
use App\Enums\PromptSectionKind;
use App\Enums\PromptVariableType;
use App\Models\PromptTemplate;
use Illuminate\Database\Seeder;

/**
 * The starter prompt for an Arcadia flyer: what the owner gives ChatGPT or Gemini to make a social
 * media image that sells **Arcadia itself, not one guide** — the idea that there is a guide for the
 * viewer's own situation, whatever the topic.
 *
 * **People buy results, not processes** (2026-10-19, the third version). A gym sells someone fit,
 * not hours of weights; this flyer sells the after — the job got, the motorbike fixed by its owner,
 * the cakes sold to order. So the headline is **a transformation verb and a result** ("Consigue el
 * trabajo que quieres"), the result's words highlighted in the accent; the three cards are results,
 * each opening on its verb; the scene is the result already lived, never someone studying; and
 * process words — estudia, curso, clase, lección — are kept out of the image. "Arcadia" is a small
 * signature in a corner: the second version made it the headline, which says nothing to anyone
 * not yet knowing the name. Left blank, the headline and the results are the model's, by the same
 * formula.
 *
 * **Growth, not a job** (2026-10-19, the fourth version). The third version's own example, "Consigue
 * el trabajo que quieres", came back as the headline over a man at a laptop: it read as a job board,
 * and most guides are not about work. A guide is **the key of a subject** — what you need to grow,
 * at work, in your own life or to start something of your own — so the headline now speaks of
 * growth that fits all three results, with two examples of that kind, and the scene avoids the
 * office and the screen. The WhatsApp blank is a `phone`, grouped with dashes when rendered.
 *
 * **No topic of its own** (2026-10-19, the fifth version). The fourth version's scene list began
 * with "su propio taller", and three trade-flavoured default results did the rest: the flyer came
 * back as a mechanic at an engine, an advert for one trade. The scene now shows a confident person
 * in an everyday place with **no tool or object of any subject** — the cards carry what is
 * concrete — and the results start **empty**: left so, the model writes one per area of growth,
 * work, starting something of one's own, and the personal, none tied to a topic.
 *
 * **Decided, and a layer of mini artefacts** (2026-10-19, the seventh version). "Crece con lo que de
 * verdad importa" read as a brochure, so the headline is now short, imperative and decided —
 * soft abstractions ("lo clave", "tu potencial") are named as what not to write — with one to
 * three words highlighted, in two lines. And the empty background gets **six to ten mini
 * artefacts**: line icons on small glass chips, a flat layer over the photo like the cards rather
 * than objects in the scene, a little of everything — trades, digital, money, creative, everyday —
 * so "any subject" shows without reading. The owner can name them (`artifacts`).
 *
 * **WhatsApp only when filled** (2026-10-19). Its line already went with an empty blank, but the
 * brand rule still named WhatsApp among the logos to avoid — and a name in a prompt, even
 * forbidden, invites the thing. The warning now sits on the WhatsApp line itself, so with no
 * number the word never reaches the model.
 *
 * **Unlike the cover, it carries text**, so the text is the risk: every quoted string is drawn
 * exactly, and **an empty field never reaches the model** — each optional text line is
 * `{{?name}}`, which `PromptRenderer` drops whole when empty. The first version asked the model to
 * leave out any field reading "—", and it drew the dash.
 *
 * **It starts filled** (`default_value`): three results drawn from published guides, the site's
 * address, an invitation, 4:5; the price is a `price` blank, at the host's price.
 *
 * A **proportion, never pixels**, as the cover: 4:5 unless the owner names 1:1 or 9:16, with
 * generous margins because the networks crop the edges and cover a story's top and bottom.
 *
 * Optional, and not run by `DatabaseSeeder`: content for the owner to change. Idempotent by name.
 * Run where the template exists without this text (`REVISION`), it writes this text as a **new
 * version**, whether or not the latest was used, so each approach stays to compare; run again, it
 * changes nothing, and the owner's later edits stay.
 */
class ArcadiaFlyerPromptTemplateSeeder extends Seeder
{
    public const NAME = 'Flyer de Arcadia — imagen';

    private const DESCRIPTION = 'Un flyer para redes sociales que vende Arcadia, no una guía: el resultado que alguien consigue, con un titular de transformación, tres resultados alrededor de la persona que ya lo vive, precio y contacto.';

    private const VERSION_DESCRIPTION = 'Titular decidido y motivador; capa de mini artefactos de un poco de todo; resultados por área; WhatsApp solo si se llena.';

    /** What in the text section shows a version carries this seeder's current text. */
    private const REVISION = 'mini artefactos';

    public function run(): void
    {
        if ($template = PromptTemplate::query()->where('name', self::NAME)->first()) {
            if ($template->latestVersion()->sections()->where('body', 'like', '%'.self::REVISION.'%')->exists()) {
                return;
            }

            // The earlier text stays as the version it is; this one is the next.
            $version = app(DraftNextPromptVersion::class)->handle($template);
            $version->sections()->delete();
            $version->variables()->delete();
            $template->update(['description' => self::DESCRIPTION]);
        } else {
            $template = PromptTemplate::create(['name' => self::NAME, 'description' => self::DESCRIPTION]);
            $version = $template->latestVersion();
        }

        $version->update(['description' => self::VERSION_DESCRIPTION]);

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
            $version->sections()->create([
                'kind' => $kind,
                'title' => $title,
                'body' => $body,
                'position' => $position + 1,
            ]);
        }
    }

    /** @return list<array{string, string, PromptVariableType, bool, string|null}> */
    private function variables(): array
    {
        return [
            ['headline', 'Titular (vacío: lo escribe la IA)', PromptVariableType::TEXT, false, null],
            ['highlight', 'Palabras a resaltar (vacío: el resultado)', PromptVariableType::TEXT, false, null],
            ['situations', 'Resultados (uno por línea; vacío: uno por área — trabajo, emprender, lo personal)', PromptVariableType::LONGTEXT, false, null],
            ['artifacts', 'Mini artefactos (uno por línea; vacío: un poco de todo)', PromptVariableType::LONGTEXT, false, null],
            ['audience', 'Para quién', PromptVariableType::TEXT, false, null],
            ['price', 'Precio', PromptVariableType::PRICE, false, null],
            ['cta', 'Invitación', PromptVariableType::TEXT, false, 'Escríbenos y empieza hoy'],
            ['whatsapp', 'WhatsApp (sale como 0412-802-3555)', PromptVariableType::PHONE, false, null],
            ['email', 'Correo', PromptVariableType::TEXT, false, null],
            ['web', 'Web o usuario', PromptVariableType::TEXT, false, 'arcadia.yhndevside.com'],
            ['format', 'Formato (1:1, 4:5 o 9:16)', PromptVariableType::TEXT, false, '4:5'],
            ['language', 'Idioma del texto', PromptVariableType::LANGUAGE, true, 'es'],
            ['style', 'Estilo (vacío: fotografía realista)', PromptVariableType::TEXT, false, null],
        ];
    }

    /** @return list<array{PromptSectionKind, string, string}> */
    private function sections(): array
    {
        return [
            [PromptSectionKind::CONTRACT, 'Formato y entrega', <<<'TXT'
Crea una sola imagen vertical para redes sociales, en proporción {{format}}. Si la herramienta deja elegir la proporción, elige esa. No hace falta un tamaño en píxeles. Entrega solo la imagen, en JPG o PNG.

Todo el texto que aparezca en la imagen va en {{language}}.
TXT],
            [PromptSectionKind::PERSONA, 'Tu rol', <<<'TXT'
Eres el director de arte de Arcadia, un servicio de guías hechas a la medida de la situación concreta de quien las pide, sobre cualquier tema. Una guía de Arcadia no es un curso aburrido: es lo clave de un tema, lo que de verdad necesitas saber para crecer — en el trabajo, en lo personal o para emprender — y, con eso, transformarte.

Vas a crear un flyer para redes sociales, y una regla manda sobre todas las demás: la gente compra resultados, no procesos. Un gimnasio no vende horas de pesas: vende a alguien en forma, disfrutándolo. Este flyer tampoco vende estudiar: vende el después — la persona que avanzó, que se atrevió a empezar lo suyo, que por fin se siente segura en lo que antes la frenaba.

Quien lo vea tiene que pensar «yo quiero eso» en el primer segundo, antes de leer nada más que el titular.
TXT],
            [PromptSectionKind::BRIEF, 'El mensaje', <<<'TXT'
Titular: {{?headline}}
Palabras a resaltar: {{?highlight}}
Para quién: {{?audience}}
Mini artefactos: {{?artifacts}}
Resultados de ejemplo:
{{situations}}

Cada resultado es algo que una persona ya logró, no un tema ni una materia, y empieza por su verbo, de ocho palabras como máximo. Si hay más de tres, usa los tres primeros.

Si los resultados dicen —, escribe tú tres generales, uno por cada área en que una guía te hace crecer, sin nombrar ningún tema, oficio ni herramienta:
- el trabajo — por ejemplo «Avanza en tu trabajo con seguridad»;
- emprender — por ejemplo «Emprende con lo que ya sabes»;
- lo personal — por ejemplo «Crece en lo que te importa».

Si no hay titular, escríbelo tú: una frase de crecimiento y transformación, de seis palabras como máximo, que sirva para los tres resultados a la vez — no para uno solo. Habla de crecer, no de un área: nunca de trabajo, empleo ni entrevista, porque la mayoría de las guías no son de eso.

El tono del titular es de decisión total: energía, motivación, cero dudas — como quien ya decidió y va por ello. Frases cortas e imperativas, que se pueden partir con puntos. Nada tibio ni abstracto: nada de «lo clave», «lo que importa», «tu camino» ni «tu potencial», que suenan a folleto y no mueven a nadie. Como mucho un signo de exclamación. Estos dos son el tipo de titular que buscamos:
- «Decídete. Aprende. Transfórmate.» — resaltado: «Transfórmate.»
- «Hoy empieza tu mejor versión» — resaltado: «tu mejor versión».
Verbos que sirven: decídete, atrévete, transfórmate, desarróllate, crece, conviértete, logra, domina, empieza; y aprende, solo seguido de lo que se gana con ello.
TXT],
            [PromptSectionKind::STRUCTURE, 'Composición', <<<'TXT'
En el centro, ocupando cerca de un tercio de la imagen, una sola persona inventada que ya creció: seguridad, orgullo tranquilo, una media sonrisa; de perfil o de tres cuartos, sin mirar a cámara. Este flyer no es de ningún tema, así que la persona tampoco: va sin herramientas ni objetos de ningún oficio, sin uniforme ni ropa de trabajo, en un lugar cotidiano y luminoso — una calle con luz de tarde, una terraza, un espacio propio —, con las manos libres o en un gesto natural. Lo concreto lo cuentan las tarjetas, no la escena. Nunca estudiando, nunca frente a una laptop o una pantalla: ese es el proceso, y aquí se muestra el después.

Alrededor flotan tres tarjetas, como las de una app: rectángulos redondeados, apenas translúcidos, a distintas alturas y ligeramente inclinados, sin taparle la cara. Cada tarjeta lleva un icono simple y genérico que vaya con su resultado — un maletín, una tienda pequeña, una estrella — y el resultado.

Encima de la foto, como una capa gráfica aparte — del mismo estilo que las tarjetas, nunca objetos dentro de la escena ni en las manos de la persona —, flotan entre seis y diez mini artefactos: cada uno, un icono de línea en el verde azulado del acento dentro de un pequeño círculo o píldora de vidrio, de entre el 4 % y el 7 % del ancho de la imagen. Si hay mini artefactos en «El mensaje», son esos; si no, un poco de todo, uno o dos de cada familia: oficios (una llave, unas tijeras, un enchufe), lo digital (una cuadrícula, un gráfico que sube), el dinero y el negocio (una moneda lisa, una bolsa de compra), lo creativo (una nota musical, un pincel, una cámara) y lo cotidiano (una taza, un batidor, una planta). Dos o tres, más grandes y nítidos, cerca de las tarjetas; el resto, más pequeños y apenas desenfocados, como un bokeh, para dar profundidad. Van solo en los huecos del fondo: nunca sobre la cara, el titular, las tarjetas ni la franja de abajo. Sin texto, sin números y sin marcas. Le dan textura y vida a la imagen; si alguno compite con la persona, sobra.

Arriba, el titular: lo más grande de la imagen y lo primero que se lee. Abajo, una franja limpia con los datos que haya en «Texto exacto» — precio, invitación, contacto —; si no hay ninguno, no hay franja. En una esquina, pequeña, la palabra Arcadia como firma.

Deja un margen amplio en todos los bordes: las redes recortan las orillas y, en 9:16, tapan la franja de arriba y la de abajo con su propia interfaz. Nada importante pegado al borde.
TXT],
            [PromptSectionKind::COPY, 'Texto exacto', <<<'TXT'
El texto de la imagen es este, cada uno en el lugar que le da la composición. Lo que va entre comillas se escribe exactamente, con su ortografía y sus tildes; lo que escribas tú — el titular o los resultados, si faltan — con el mismo cuidado:

- Titular, lo más grande, en una o dos líneas: «{{?headline}}»
- Si no hay titular entre comillas en la línea de arriba, el que escribiste con la fórmula de «El mensaje».
- Una tarjeta por resultado, en tamaño mediano, con su verbo inicial en negrita.
- Firma, pequeña, en una esquina: «Arcadia»
- Precio, destacado en la franja de abajo: «{{?price}}»
- Invitación, junto al precio, como texto: «{{?cta}}»
- WhatsApp, pequeño, con un icono genérico de chat delante (nunca su logo): «{{?whatsapp}}»
- Correo, pequeño, con un icono genérico de sobre delante: «{{?email}}»
- Web, pequeña, con un icono genérico de globo delante: «{{?web}}»

Lo que importa se resalta. En el titular, de una a tres palabras — las de más fuerza, o las «Palabras a resaltar», si las hay — van más gruesas y en el verde azulado del acento; el resto, en blanco. Nada más se resalta así: si todo destaca, nada destaca. El titular cabe en dos líneas.

Los datos de contacto van juntos, en una sola línea. Esta lista es todo el texto de la imagen: lo que no está en ella no se dibuja — ni texto, ni icono, ni su espacio. Los números se escriben dígito por dígito, exactamente como están arriba.
TXT],
            [PromptSectionKind::COPY, 'Estilo y color', <<<'TXT'
Estilo: {{style}} — si dice —, fotografía realista y cinematográfica para la persona, con las tarjetas, los iconos y el texto en diseño gráfico plano y limpio encima.

El aspecto Arcadia, en cualquier estilo:
- Fondo azul marino profundo (#030D1C) en los bordes, limpio, con un brillo suave detrás de la persona.
- La persona bañada en luz cálida, dorada: lo más luminoso de la imagen después del titular.
- Un acento verde azulado (#2EE6B8) o cian (#2DD6F5) en las palabras resaltadas, los bordes de las tarjetas, los iconos y el precio. El acento no tiñe toda la imagen.
- Tipografía sans serif moderna, muy gruesa en el titular; texto claro sobre el fondo oscuro, con buen contraste, legible en un celular.
- Un tono de logro, optimista y cercano: nunca solemne ni de anuncio de banco.
TXT],
            [PromptSectionKind::VETTING, 'Lo que no debe tener', <<<'TXT'
- Ninguna palabra de proceso en la imagen: estudia, estudiar, curso, clase, lección, módulo, temario, contenido, video, etapa. Venden el esfuerzo, no el resultado.
- Nada del proceso en la escena: nadie estudiando, ni laptops con clases, libros, cuadernos, pizarras ni aulas.
- Ningún texto aparte del de «Texto exacto»: nada legible en pantallas, libros, ropa ni fondo.
- Ninguna marca: los iconos son genéricos e inventados. Nunca el logo de una app, una red social u otra marca, ni su nombre.
- Ningún botón, píldora ni icono vacío: cada icono acompaña a un dato de «Texto exacto».
- Nada de motivación de banco de imágenes: pulgares arriba, puños en alto, saltos, brazos abiertos, confeti ni sonrisas forzadas a cámara.
- Nada de collage de fotos, marcos, marcas de agua ni más de tres tarjetas.
- Ninguna cara de una persona real o famosa, ni manos deformes.
TXT],
        ];
    }
}
