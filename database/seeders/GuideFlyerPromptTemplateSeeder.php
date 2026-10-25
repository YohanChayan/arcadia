<?php

namespace Database\Seeders;

use App\Actions\DraftNextPromptVersion;
use App\Enums\PromptSectionKind;
use App\Enums\PromptVariableType;
use App\Models\PromptTemplate;
use Illuminate\Database\Seeder;

/**
 * The starter prompt for one guide's flyer: what the owner gives ChatGPT or Gemini to make a social
 * media image that sells **this guide** — its title, what the reader will be able to do, its price
 * and how to ask for it. The sibling of `ArcadiaFlyerPromptTemplateSeeder`, which sells Arcadia with
 * no guide of its own.
 *
 * **People buy results, not processes** (2026-10-19, the second version), as the general flyer
 * says it: a gym sells someone fit, not hours of weights. The headline is **the result this guide
 * promises**, with a transformation verb ("Aprueba la prueba de Excel"), its result words
 * highlighted in the accent — written by the model from the title and goals unless the owner gives
 * one; the first version used the title itself, which names the topic rather than the outcome.
 * Around the person — **living the result**, never studying — float up to three cards, **the
 * guide's goals written as results**, each opening on its verb. Process words stay out of the
 * image, and "Arcadia" signs it, small.
 *
 * **Decided, and a layer of mini artefacts** (2026-10-19, the fifth version), as the general
 * flyer: a short, imperative, decided headline with one to three words highlighted, and six to ten
 * mini artefacts of **this guide's subject** — line icons on glass chips, a flat layer over the
 * photo — unless the owner names them (`artifacts`).
 *
 * **Growth, not only a job** (2026-10-19, the third version), as the general flyer learnt: a guide
 * is the key of its subject, for growing at work, in one's own life or to start something, so the
 * headline and the scene follow what this guide is for and never turn it into a job it is not
 * about. The WhatsApp blank is a `phone`, grouped with dashes when rendered.
 *
 * **WhatsApp only when filled** (2026-10-19). Its line already went with an empty blank, but the
 * brand rule still named WhatsApp among the logos to avoid — and a name in a prompt, even
 * forbidden, invites the thing. The warning now sits on the WhatsApp line itself, so with no
 * number the word never reaches the model.
 *
 * **Text drawn exactly, and an empty field never reaches the model**, as the general flyer learnt:
 * each optional text line is `{{?name}}`, which `PromptRenderer` drops whole when empty. The
 * headline, when the model writes it, and the goals are the only text it may word itself — no digit
 * is at risk in either.
 *
 * **It starts filled**: the Presentation screen gives it the guide's title, category, pitch, goals
 * and language (`ImageController`); the variables' defaults give the rest — the site's address, an
 * invitation, 4:5 — and the `price` blank starts at the host's price.
 *
 * Optional, and not run by `DatabaseSeeder`. Idempotent by name; run where the template exists
 * without this text (`REVISION`), it writes it as a new version, so each approach stays to compare.
 */
class GuideFlyerPromptTemplateSeeder extends Seeder
{
    public const NAME = PromptTemplate::GUIDE_FLYER;

    private const DESCRIPTION = 'El flyer de una guía para redes sociales, para ChatGPT o Gemini: el resultado que promete como titular, sus metas como logros alrededor de la persona que ya lo vive, con precio y contacto.';

    private const VERSION_DESCRIPTION = 'Titular decidido con el resultado de la guía; capa de mini artefactos de su tema; WhatsApp solo si se llena.';

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
            ['title', 'Título de la guía', PromptVariableType::TEXT, true, null],
            ['headline', 'Titular (vacío: el resultado, lo escribe la IA)', PromptVariableType::TEXT, false, null],
            ['highlight', 'Palabras a resaltar (vacío: el resultado)', PromptVariableType::TEXT, false, null],
            ['subtitle', 'Subtítulo', PromptVariableType::TEXT, false, null],
            ['category', 'Categoría', PromptVariableType::CATEGORY, false, null],
            ['pitch', 'Presentación breve', PromptVariableType::LONGTEXT, false, null],
            ['goals', 'Metas (una por línea; van las tres primeras)', PromptVariableType::LONGTEXT, false, null],
            ['scene', 'Idea de escena', PromptVariableType::TEXT, false, null],
            ['artifacts', 'Mini artefactos (uno por línea; vacío: los del tema de la guía)', PromptVariableType::LONGTEXT, false, null],
            ['price', 'Precio', PromptVariableType::PRICE, false, null],
            ['cta', 'Invitación', PromptVariableType::TEXT, false, 'Escríbenos para inscribirte'],
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
Eres el director de arte de Arcadia, un servicio de guías hechas a la medida de una situación concreta. Una guía de Arcadia no es un curso aburrido: es lo clave de un tema, lo que de verdad necesitas saber para crecer — en el trabajo, en lo personal o para emprender — y, con eso, transformarte.

Vas a crear el flyer de una guía en particular, para redes sociales, y una regla manda sobre todas las demás: la gente compra resultados, no procesos. Un gimnasio no vende horas de pesas: vende a alguien en forma, disfrutándolo. Este flyer no vende estudiar esta guía: vende lo que la persona tiene cuando la termina.

Quien lo vea tiene que pensar «yo quiero eso» en el primer segundo, antes de leer nada más que el titular.
TXT],
            [PromptSectionKind::BRIEF, 'La guía', <<<'TXT'
Título de la guía: {{title}}
Categoría: {{?category}}
De qué trata: {{?pitch}}
Lo que la persona podrá hacer:
{{goals}}
Titular: {{?headline}}
Palabras a resaltar: {{?highlight}}
Idea de escena: {{?scene}}
Mini artefactos: {{?artifacts}}

Si no hay titular, escríbelo tú: el resultado que esta guía promete, con un verbo de transformación, de seis palabras como máximo — sacado del título y de las metas, no copiado del título.

El tono del titular es de decisión total: energía, motivación, cero dudas — como quien ya decidió y va por ello. Frases cortas e imperativas, que se pueden partir con puntos. Nada tibio ni abstracto: nada de «lo clave», «lo que importa», «tu camino» ni «tu potencial», que suenan a folleto y no mueven a nadie. Como mucho un signo de exclamación. Por ejemplo, para «Excel para aprobar la prueba de la entrevista»: «Domina Excel. Aprueba esa prueba.»; para «Mantenimiento básico de tu carro»: «Tu carro, tus reglas»; para «Hacer tortas y postres para vender por encargo»: «Hornea. Vende. Hazlo tuyo.». Verbos que sirven: conviértete, consigue, logra, domina, transfórmate, desarróllate, crece, gana, aprueba, vende, arregla; y aprende, solo seguido de un resultado concreto. El resultado es el de esta guía: si no trata de trabajo, no lo conviertas en uno.

Las metas se vuelven logros: cada una empieza por su verbo y dice lo que la persona ya puede hacer, de ocho palabras como máximo. Si las metas dicen —, escribe tú tres así, a partir del título y de qué trata.

Antes de crear, elige la escena: la persona viviendo el resultado de esta guía, en el lugar donde se disfruta — el primer día en el trabajo nuevo, el carro andando de vuelta del taller, el cliente recibiendo el pedido. No el proceso: nadie aprendiendo. No una metáfora: nada de horizontes, cimas, escaleras, caminos, bombillas ni personas mirando a lo lejos. Si hay una idea de escena, parte de ella y llévala a ese después.
TXT],
            [PromptSectionKind::STRUCTURE, 'Composición', <<<'TXT'
En el centro, ocupando cerca de un tercio de la imagen, una sola persona inventada en esa escena — seguridad y orgullo tranquilo, una media sonrisa —, de perfil o de tres cuartos, sin mirar a cámara; o, si el resultado se cuenta mejor así, solo sus manos y lo que ya lograron.

Alrededor flotan tres tarjetas, como las de una app: rectángulos redondeados, apenas translúcidos, a distintas alturas y ligeramente inclinados, sin tapar lo importante. Cada tarjeta lleva una marca de verificación o un icono simple y genérico del tema, y un logro.

Encima de la foto, como una capa gráfica aparte — del mismo estilo que las tarjetas, nunca objetos dentro de la escena ni en las manos de la persona —, flotan entre seis y diez mini artefactos: cada uno, un icono de línea en el verde azulado del acento dentro de un pequeño círculo o píldora de vidrio, de entre el 4 % y el 7 % del ancho de la imagen. Si hay mini artefactos en «La guía», son esos; si no, los del tema de esta guía — sus herramientas, sus objetos, sus símbolos —, variados entre sí. Dos o tres, más grandes y nítidos, cerca de las tarjetas; el resto, más pequeños y apenas desenfocados, como un bokeh, para dar profundidad. Van solo en los huecos del fondo: nunca sobre la cara, el titular, las tarjetas ni la franja de abajo. Sin texto, sin números y sin marcas. Le dan textura y vida a la imagen; si alguno compite con la persona, sobra.

Arriba, el titular: lo más grande de la imagen y lo primero que se lee; debajo, el subtítulo, si lo hay, más pequeño. Abajo, una franja limpia con los datos que haya en «Texto exacto» — precio, invitación, contacto —; si no hay ninguno, no hay franja. En una esquina, pequeña, la palabra Arcadia como firma.

Deja un margen amplio en todos los bordes: las redes recortan las orillas y, en 9:16, tapan la franja de arriba y la de abajo con su propia interfaz. Nada importante pegado al borde.
TXT],
            [PromptSectionKind::COPY, 'Texto exacto', <<<'TXT'
El texto de la imagen es este, cada uno en el lugar que le da la composición. Lo que va entre comillas se escribe exactamente, con su ortografía y sus tildes; lo que escribas tú — el titular, si falta, y los logros — con el mismo cuidado:

- Titular, lo más grande, en una o dos líneas: «{{?headline}}»
- Si no hay titular entre comillas en la línea de arriba, el que escribiste a partir de la guía.
- Subtítulo, debajo del titular: «{{?subtitle}}»
- Una tarjeta por logro, los tres primeros, en tamaño mediano, con su verbo inicial en negrita.
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
Estilo: {{style}} — si dice —, fotografía realista y cinematográfica para la escena, con las tarjetas, los iconos y el texto en diseño gráfico plano y limpio encima.

El aspecto Arcadia, en cualquier estilo:
- Fondo azul marino profundo (#030D1C) en los bordes, limpio, con un brillo suave detrás del protagonista.
- El protagonista bañado en luz cálida, dorada: lo más luminoso de la imagen después del titular.
- Un acento verde azulado (#2EE6B8) o cian (#2DD6F5) en las palabras resaltadas, los bordes de las tarjetas, los iconos y el precio. El acento no tiñe toda la imagen.
- Tipografía sans serif moderna, muy gruesa en el titular; texto claro sobre el fondo oscuro, con buen contraste, legible en un celular.
- Un tono de logro, optimista y cercano: nunca solemne ni de anuncio de banco.
TXT],
            [PromptSectionKind::VETTING, 'Lo que no debe tener', <<<'TXT'
- Ninguna palabra de proceso en la imagen: estudia, estudiar, curso, clase, lección, módulo, temario, contenido, video, etapa. Venden el esfuerzo, no el resultado.
- Nada del proceso en la escena: nadie estudiando, ni laptops con clases, libros, cuadernos, pizarras ni aulas.
- Ningún texto aparte del de «Texto exacto»: nada legible en pantallas, libros, ropa, etiquetas ni fondo.
- Ninguna marca: todo objeto e icono es genérico e inventado. Nunca el logo de una app, una red social u otra marca, ni su nombre dibujado en un objeto.
- Ningún botón, píldora ni icono vacío: cada icono acompaña a un dato de «Texto exacto».
- Nada de motivación de banco de imágenes: pulgares arriba, puños en alto, saltos, brazos abiertos, confeti ni sonrisas forzadas a cámara.
- Nada de collage de fotos, marcos, marcas de agua ni más de tres tarjetas.
- Ninguna cara de una persona real o famosa, ni manos deformes.
TXT],
        ];
    }
}
