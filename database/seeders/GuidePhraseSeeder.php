<?php

namespace Database\Seeders;

use App\Enums\PhraseMoment;
use App\Models\Phrase;
use Illuminate\Database\Seeder;

/**
 * Twenty starter motivation phrases per language, spread over the moments they are shown at —
 * short enough to be read in a glance. Idempotent by text: run again, it adds only what is not
 * there and never changes a phrase the owner edited — though one the owner deleted comes back.
 */
class GuidePhraseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->phrases() as $locale => $moments) {
            foreach ($moments as $moment => $texts) {
                foreach ($texts as $text) {
                    Phrase::firstOrCreate(['locale' => $locale, 'moment' => PhraseMoment::from($moment), 'text' => $text]);
                }
            }
        }
    }

    /** @return array<string, array<string, list<string>>> */
    private function phrases(): array
    {
        return [
            'es' => [
                'welcome' => [
                    'Hoy es un buen día para avanzar, {name}.',
                    'Volviste. Eso ya es ganar.',
                    'Un paso más y vas mejor que ayer.',
                    'Lo que aprendas hoy, nadie te lo quita.',
                ],
                'start' => [
                    'Empieza por el primer video. Solo eso.',
                    'Diez minutos bastan para arrancar.',
                    'El primer paso es el que más cuenta.',
                    'Dale play. Lo demás viene solo.',
                ],
                'progress' => [
                    'Vas bien, {name}. Sigue así.',
                    'Cada video que marcas es un paso real.',
                    'Poco a poco se llega lejos.',
                    'No pares ahora: ya agarraste ritmo.',
                    'Lo que hoy cuesta, mañana sale solo.',
                ],
                'almost' => [
                    '¡Ya casi! Te falta muy poco.',
                    'Estás a un empujón de terminar.',
                    'La meta ya se ve desde aquí.',
                    'Termina lo que empezaste, {name}.',
                ],
                'done' => [
                    '¡Lo lograste, {name}! Guía completa.',
                    'Terminaste. Ahora a usarlo de verdad.',
                    'Meta cumplida. ¿Qué aprendes después?',
                ],
            ],
            'en' => [
                'welcome' => [
                    'Today is a good day to move forward, {name}.',
                    'You came back. That is already a win.',
                    'One more step and you are ahead of yesterday.',
                    'What you learn today is yours to keep.',
                ],
                'start' => [
                    'Start with the first video. Just that.',
                    'Ten minutes is enough to get going.',
                    'The first step is the one that counts most.',
                    'Press play. The rest follows.',
                ],
                'progress' => [
                    'Going well, {name}. Keep it up.',
                    'Every video you tick is a real step.',
                    'Little by little, you get far.',
                    'Do not stop now: you have found your pace.',
                    'What is hard today comes easy tomorrow.',
                ],
                'almost' => [
                    'Almost there! Just a little left.',
                    'One push away from the finish.',
                    'You can see the goal from here.',
                    'Finish what you started, {name}.',
                ],
                'done' => [
                    'You did it, {name}! Guide complete.',
                    'Done. Now put it to real use.',
                    'Goal reached. What will you learn next?',
                ],
            ],
        ];
    }
}
