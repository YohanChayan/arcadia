<?php

namespace Database\Seeders;

use App\Actions\AssignReader;
use App\Actions\ImportGuideContent;
use App\Enums\GuideStatus;
use App\Enums\InquiryPurpose;
use App\Enums\Level;
use App\Models\Guide;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\GuideImport;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fake data to work against locally — never production's, which the server migration imports.
 *
 * Every guide is written through `GuideImport` and `ImportGuideContent`, the path an AI answer
 * takes from the editor, so a fake guide is held to the same checks as a real one and cannot be
 * malformed. What it makes, enough to exercise the editor and the reader:
 *
 * - in Spanish, a published guide open for enrollment, a published guide two readers are
 *   assigned to, and a draft; in English, one published guide;
 * - two readers, Ana and Bob, verified, signing in with the password `password`;
 * - two requests waiting in the editor's inbox, and one access request.
 *
 * Refuses to run in production. Run it again and it adds a second copy: start over with
 * `php artisan migrate:fresh --seed`.
 */
class DemoSeeder extends Seeder
{
    public function __construct(
        private readonly GuideImport $import,
        private readonly ImportGuideContent $write,
        private readonly AssignReader $assign,
    ) {}

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder writes fake guides and accounts: never in production.');
        }

        $owner = User::where('email', 'yhndevside@gmail.com')->firstOrFail();

        $excel = $this->guide('es', $this->excel(), published: true, openForEnrollment: true);
        $english = $this->guide('es', $this->english(), published: true);
        $this->guide('es', $this->cooking(), published: false);
        $this->guide('en', $this->photography(), published: true, openForEnrollment: true);

        $ana = $this->reader('Ana Pérez', 'ana@example.com');
        $bob = $this->reader('Bob Díaz', 'bob@example.com');

        $this->assign->handle($english, $ana->email, $owner, notify: false, name: 'Ana');
        $this->assign->handle($english, $bob->email, $owner, notify: false, name: 'Bob');

        Inquiry::create(['locale' => 'es', 'topic' => 'Aprender a usar Canva para mi emprendimiento', 'purpose' => InquiryPurpose::OWN, 'contact' => '0414-555 0101', 'name' => 'Carla']);
        Inquiry::create(['locale' => 'en', 'topic' => 'Excel for a job interview next week', 'purpose' => InquiryPurpose::JOB, 'contact' => 'dan@example.com', 'name' => 'Dan']);

        $excel->accessRequests()->create(['user_id' => $bob->id, 'message' => 'Me gustaría seguir esta guía también.']);
    }

    /**
     * @param  array<string, mixed>  $answer  in the shape the research prompt asks the AI for
     */
    private function guide(string $locale, array $answer, bool $published, bool $openForEnrollment = false): Guide
    {
        if ($problems = $this->import->problems($answer)) {
            throw new RuntimeException('A demo guide fails the import checks: '.json_encode($problems, JSON_UNESCAPED_UNICODE));
        }

        $guide = Guide::create([
            'locale' => $locale,
            'title' => $answer['title'],
            'level' => Level::from($answer['level']),
            'status' => $published ? GuideStatus::PUBLISHED : GuideStatus::DRAFT,
            'open_for_enrollment' => $openForEnrollment,
        ]);

        $draft = $guide->latestVersion();
        $this->write->handle($guide, $draft, $answer, replace: false, applyDetails: true);

        if ($published) {
            $draft->release();
        }

        return $guide->fresh();
    }

    private function reader(string $name, string $email): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => 'password', 'email_verified_at' => now()]);
        $user->membership()->create(['role_id' => Role::where('alias_number', Role::ALIAS_READER)->firstOrFail()->id]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function excel(): array
    {
        return [
            'title' => 'Excel para tu primer trabajo',
            'level' => 'basic',
            'goals' => ['Armar una lista de precios que se actualiza sola', 'Presentar una prueba de Excel sin nervios'],
            'greeting' => 'Esta ruta te lleva de cero a resolver la prueba de Excel de una entrevista.',
            'sections' => [
                ['kind' => 'how_to_use', 'title' => 'Cómo usar esta guía', 'body' => 'Sigue las etapas en orden. Cada una termina con un reto: hazlo antes de seguir.'],
                ['kind' => 'tips', 'title' => 'Consejos', 'body' => 'Ten Excel abierto mientras ves los videos y repite cada paso.'],
            ],
            'stages' => [
                [
                    'title' => 'Moverte en Excel',
                    'goal' => 'abres, escribes y guardas sin miedo',
                    'cards' => [
                        [
                            'kind' => 'source',
                            'byline' => 'Canal de ejemplo · un video',
                            'description' => 'Lo básico de la hoja de cálculo, de un tirón.',
                            'items' => [
                                ['ref' => 'e1-1', 'kind' => 'youtube_video', 'title' => 'Excel desde cero', 'url' => 'https://www.youtube.com/watch?v=demo0000001', 'hint' => 'Video · 40 min'],
                                ['ref' => 'e1-2', 'kind' => 'note', 'title' => 'Antes de empezar', 'body' => 'Pausa el video cada vez que el autor escriba algo, y escríbelo tú.'],
                            ],
                        ],
                        [
                            'kind' => 'challenge',
                            'description' => 'Tu primera hoja',
                            'items' => [['ref' => 'e1-reto', 'kind' => 'practice', 'title' => 'Lista de compras', 'body' => 'Haz una lista de diez productos con su precio y suma el total con =SUMA().']],
                        ],
                    ],
                ],
                [
                    'title' => 'Fórmulas que se usan de verdad',
                    'goal' => 'resuelves cálculos con referencias absolutas',
                    'cards' => [
                        [
                            'kind' => 'source',
                            'byline' => 'Sitio de ejemplo · artículo',
                            'description' => 'Referencias relativas y absolutas, con ejemplos.',
                            'items' => [
                                ['ref' => 'e2-1', 'kind' => 'external_site', 'title' => 'Referencias en Excel', 'url' => 'https://example.com/excel/referencias', 'hint' => 'Lectura · 10 min'],
                            ],
                        ],
                        [
                            'kind' => 'challenge',
                            'description' => 'Precios al cambio del día',
                            'items' => [['ref' => 'e2-reto', 'kind' => 'practice', 'title' => 'Lista de precios en dos monedas', 'body' => 'Pon la tasa en B1 y calcula cada precio con =A2*$B$1.']],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function english(): array
    {
        return [
            'title' => 'Inglés para conversar en el trabajo',
            'level' => 'intermediate',
            'goals' => ['Presentarte en una reunión en inglés', 'Escribir un correo profesional'],
            'greeting' => 'Una ruta corta para soltarte a hablar en el trabajo.',
            'sections' => [
                ['kind' => 'how_to_use', 'title' => 'Cómo usar esta guía', 'body' => 'Quince minutos al día, en el orden de las etapas.'],
            ],
            'stages' => [
                [
                    'title' => 'Presentarte',
                    'goal' => 'dices quién eres y qué haces',
                    'cards' => [
                        [
                            'kind' => 'source',
                            'byline' => 'Canal de ejemplo · lista de videos',
                            'description' => 'Frases para presentarte, con pronunciación.',
                            'items' => [
                                ['ref' => 'i1-1', 'kind' => 'youtube_playlist', 'title' => 'Presentaciones en inglés', 'url' => 'https://www.youtube.com/playlist?list=PLdemo00000001', 'hint' => 'Lista · 6 videos'],
                            ],
                        ],
                        [
                            'kind' => 'challenge',
                            'description' => 'Grábate',
                            'items' => [['ref' => 'i1-reto', 'kind' => 'practice', 'title' => 'Tu presentación en un minuto', 'body' => 'Grábate presentándote y escúchate una vez.']],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function cooking(): array
    {
        return [
            'title' => 'Repostería para vender por encargo',
            'level' => 'basic',
            'goals' => ['Hornear una torta que se pueda vender'],
            'stages' => [
                [
                    'title' => 'La torta base',
                    'cards' => [
                        [
                            'kind' => 'source',
                            'byline' => 'Canal de ejemplo · un video',
                            'items' => [
                                ['ref' => 'r1-1', 'kind' => 'youtube_video', 'title' => 'Bizcocho básico', 'url' => 'https://www.youtube.com/watch?v=demo0000002', 'hint' => 'Video · 15 min'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function photography(): array
    {
        return [
            'title' => 'Phone photography for your small business',
            'level' => 'basic',
            'goals' => ['Take product photos that sell', 'Edit a photo on your phone in five minutes'],
            'greeting' => 'Better product photos, with the phone you already have.',
            'sections' => [
                ['kind' => 'how_to_use', 'title' => 'How to use this guide', 'body' => 'One stage a day. Take the challenge photo before moving on.'],
            ],
            'stages' => [
                [
                    'title' => 'Light',
                    'goal' => 'you find good light anywhere at home',
                    'cards' => [
                        [
                            'kind' => 'source',
                            'byline' => 'Example channel · one video',
                            'description' => 'Window light, explained simply.',
                            'items' => [
                                ['ref' => 'p1-1', 'kind' => 'youtube_video', 'title' => 'Window light for products', 'url' => 'https://www.youtube.com/watch?v=demo0000003', 'hint' => 'Video · 12 min'],
                            ],
                        ],
                        [
                            'kind' => 'challenge',
                            'description' => 'Three photos, three lights',
                            'items' => [['ref' => 'p1-challenge', 'kind' => 'practice', 'title' => 'The same product, three ways', 'body' => 'Photograph one product by a window, under a lamp, and outdoors.']],
                        ],
                    ],
                ],
            ],
        ];
    }
}
