<?php

namespace Tests\Feature;

use App\Knowledge\Contracts\Retriever;
use App\Knowledge\RetrievalQuery;
use App\Knowledge\RetrievedPassage;
use App\Knowledge\Retrievers\FakeRetriever;
use Tests\TestCase;

/**
 * Callers reach the knowledge index only through the `Retriever` contract. Set to `fake` — as in
 * the tests — it answers with canned passages in the query's locale, up to its limit.
 */
class KnowledgeRetrieverTest extends TestCase
{
    public function test_the_contract_resolves_to_the_fake_when_set_to_fake(): void
    {
        $this->assertInstanceOf(FakeRetriever::class, app(Retriever::class));
    }

    public function test_passages_follow_the_query_locale_and_limit(): void
    {
        $passages = app(Retriever::class)->retrieve(new RetrievalQuery('quiero aprender excel', 'en', limit: 2));

        $this->assertCount(2, $passages);
        $passages->each(function (RetrievedPassage $passage): void {
            $this->assertSame('en', $passage->locale);
            $this->assertSame(FakeRetriever::SOURCE, $passage->source);
        });
    }

    public function test_passages_come_closest_first(): void
    {
        $scores = app(Retriever::class)->retrieve(new RetrievalQuery('excel', 'es'))->pluck('score')->all();

        $this->assertSame(collect($scores)->sortDesc()->values()->all(), $scores);
    }
}
