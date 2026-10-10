<?php

use App\Actions\CreateLink;
use App\Models\Link;
use App\Models\User;
use App\Support\LinkCodeGenerator;

test('generates a six character code over the alphabet', function () {
    expect((new LinkCodeGenerator)->generate())->toMatch('/^[0-9A-Za-z]{6}$/');
});

test('generated codes are unique across calls', function () {
    $generator = new LinkCodeGenerator;
    $codes = collect(range(1, 50))->map(function () use ($generator) {
        return Link::factory()->create(['code' => $generator->generate()])->code;
    });

    expect($codes->unique())->toHaveCount(50);
});

test('grows to seven characters once the threshold is reached', function () {
    $generator = new LinkCodeGenerator(1 / 62 ** 6);

    expect($generator->currentLength())->toBe(6);

    Link::factory()->create(['code' => 'abc123']);

    expect($generator->currentLength())->toBe(7)
        ->and($generator->generate())->toMatch('/^[0-9A-Za-z]{7}$/');
});

test('retries on collision', function () {
    Link::factory()->create(['code' => 'taken1']);

    $generator = new class extends LinkCodeGenerator
    {
        /** @var list<string> */
        private array $queue = ['taken1', 'fresh2'];

        protected function randomCode(int $length): string
        {
            return array_shift($this->queue);
        }
    };

    expect($generator->generate())->toBe('fresh2');
});

test('CreateLink retries once on a unique constraint violation', function () {
    $user = User::factory()->create();
    Link::factory()->create(['code' => 'taken1']);

    $generator = new class extends LinkCodeGenerator
    {
        public int $calls = 0;

        public function generate(?string $domainId = null): string
        {
            return $this->calls++ === 0 ? 'taken1' : 'fresh2';
        }
    };

    $link = (new CreateLink($generator))($user, ['original_url' => 'https://example.com']);

    expect($link->code)->toBe('fresh2')->and($generator->calls)->toBe(2);
});

test('currentLength ignores aliases', function () {
    Link::factory()->create(['code' => 'abcdefg', 'alias' => 'sixsix']);

    expect((new LinkCodeGenerator(1e-11))->currentLength())->toBe(6);
});
