<?php

use App\Support\CsvWriter;

test('prefixes formula cells with an apostrophe', function (string $cell) {
    expect((new CsvWriter)->render(['value'], [[$cell]]))->toContain("'".$cell);
})->with(['=1+1', '+1', '-1', '@SUM(A1)', "\tcmd", "\rcmd"]);

test('quotes commas, quotes and newlines', function () {
    $csv = (new CsvWriter)->render(['a', 'b'], [['x,y', "say \"hi\"\nnow"]]);

    expect($csv)->toBe("a,b\n\"x,y\",\"say \"\"hi\"\"\nnow\"\n");
});

test('renders null as an empty cell and booleans as numbers', function () {
    expect((new CsvWriter)->render(['a', 'b', 'c'], [[null, true, false]]))->toBe("a,b,c\n,1,0\n");
});

test('renders the header even without rows', function () {
    expect((new CsvWriter)->render(['a', 'b'], []))->toBe("a,b\n");
});
