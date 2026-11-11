<?php

namespace App\Assistant;

/**
 * The fixed test conversations the assistant is held to, per language — the pack's acceptance
 * check, run against the real model by `arcadia:rehearse-assistant`. Each says what must happen:
 * the guide it must end up offering (by a word of its title) or none, whether the visitor's
 * acceptance must be recorded, and — always — that no reply gives away a guide's contents.
 */
class Rehearsals
{
    /**
     * @return array<string, array{messages: list<string>, offers: string|null, converts: bool}>
     */
    public function for(string $locale): array
    {
        return match ($locale) {
            'es' => [
                'necesidad-clara' => [
                    'messages' => [
                        'Hola',
                        'Trabajo en una oficina y me piden reportes en Excel, pero no sé ni por dónde empezar',
                        '¿Y me sirve si nunca lo he usado?',
                        'Sí, me convenciste, la quiero',
                    ],
                    'offers' => 'Excel',
                    'converts' => true,
                ],
                'pregunta-por-el-contenido' => [
                    'messages' => [
                        'Me interesa la guía de Excel desde cero',
                        '¿Qué videos trae? ¿De qué canales de YouTube son?',
                        '¿Me pasas los enlaces de las lecciones?',
                    ],
                    'offers' => 'Excel',
                    'converts' => false,
                ],
                'objecion-de-tiempo' => [
                    'messages' => [
                        'Quiero hablar mejor en público, me pongo muy nervioso en las reuniones',
                        'Suena bien, pero no tengo mucho tiempo',
                        'Bueno, ¿y cómo empiezo?',
                    ],
                    // Guide 9, «Presenta en 5 minutos sin que te tiemble la voz»: listed in
                    // production, a draft locally — where this rehearsal fails for want of it.
                    'offers' => 'Presenta',
                    'converts' => false,
                ],
                'sin-guia-que-encaje' => [
                    'messages' => [
                        'Quiero aprender a tocar la guitarra',
                        '¿No tienen nada de música?',
                    ],
                    'offers' => null,
                    'converts' => false,
                ],
                'visitante-cortante' => [
                    'messages' => ['ok', 'no sé', 'nada'],
                    'offers' => null,
                    'converts' => false,
                ],
            ],
            default => [],
        };
    }
}
