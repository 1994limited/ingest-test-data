<?php

/**
 * Generates every feed in this repository from one seeded, made-up dataset.
 *
 *   php generator/generate.php            # writes into the repository root
 *   php generator/generate.php --out=/tmp/feeds
 *
 * The output is the same on every run: same seed, same fixed "now". Change
 * the data here, never by hand in the generated files.
 */

declare(strict_types=1);

require __DIR__.'/Words.php';
require __DIR__.'/Writer.php';

use Ingest\TestData\Words;
use Ingest\TestData\Writer;

$options = getopt('', ['out::']);
$out = rtrim($options['out'] ?? dirname(__DIR__), '/');

mt_srand(1994);

const NOW = '2026-10-01T09:00:00+00:00';
const PAGE_SIZE = 25;

$w = new Writer($out);
$words = new Words;

foreach (['books', 'products', 'property', 'jobs', 'events', 'news', 'settings', 'sheets', 'states', 'images'] as $dir) {
    $w->clean($dir);
}

/*
|--------------------------------------------------------------------------
| Books: a supplier catalogue (Cargo products with format variants)
|--------------------------------------------------------------------------
*/

$authors = [];
for ($i = 0; $i < 24; $i++) {
    $authors[] = $words->person();
}

$subjectPool = ['Fiction', 'Crime', 'History', 'Nature', 'Cookery', 'Travel', 'Poetry', 'Science', 'Children', 'Art', 'Biography', 'Gardening'];
$publishers = ['Marlow & Finch', 'Hollowtree Press', 'Northgate Books', 'Pebble Lane', 'Copperleaf'];

$books = [];
for ($i = 1; $i <= 60; $i++) {
    $isbn = isbn13(100000 + $i * 37);
    $title = $words->title();
    $contributors = [['name' => $authors[mt_rand(0, count($authors) - 1)], 'role' => 'author']];
    if (mt_rand(1, 5) === 1) {
        $contributors[] = ['name' => $authors[mt_rand(0, count($authors) - 1)], 'role' => 'illustrator'];
    }

    $base = mt_rand(799, 2499) / 100;
    $formats = [
        ['sku' => $isbn.'-HB', 'binding' => 'Hardback', 'price' => money($base + 8), 'stock' => mt_rand(0, 40)],
        ['sku' => $isbn.'-PB', 'binding' => 'Paperback', 'price' => money($base), 'stock' => mt_rand(0, 120)],
    ];
    if (mt_rand(1, 3) === 1) {
        $formats[] = ['sku' => $isbn.'-EB', 'binding' => 'Ebook', 'price' => money($base - 3), 'stock' => null];
    }

    $paragraphs = $words->paragraphs(mt_rand(2, 4));
    $html = '<p>'.implode('</p><p>', $paragraphs).'</p>';

    // A couple of items carry hostile HTML, so sanitising gets tested.
    if ($i === 7) {
        $html .= '<p><img src="x" onerror="alert(1)">Signed copies available.</p><script>alert("feed")</script>';
    }
    if ($i === 13) {
        $html .= '<p><a href="javascript:alert(1)">Read more</a> <iframe src="https://example.com"></iframe></p>';
    }

    $books[] = [
        'isbn' => $isbn,
        'title' => $title,
        'subtitle' => mt_rand(1, 3) === 1 ? $words->phrase(4) : null,
        'contributors' => $contributors,
        'publisher' => $publishers[mt_rand(0, count($publishers) - 1)],
        'published' => date('Y-m-d', strtotime('2018-01-01') + mt_rand(0, 3000) * 86400),
        'pages' => mt_rand(96, 640),
        'subjects' => pick($subjectPool, mt_rand(1, 3)),
        'description_html' => $html,
        'cover' => "images/covers/{$isbn}.png",
        'formats' => $formats,
        'status' => $i % 19 === 0 ? 'withdrawn' : 'live',
        'updated_at' => NOW,
    ];
}

// The same catalogue in each shape a supplier might send.
$w->json('books/index.json', ['data' => $books, 'meta' => ['total' => count($books), 'generated_at' => NOW]]);
$w->ndjson('books/index.ndjson', $books);
$w->xml('books/index.xml', booksXml($books));
$w->csv('books/index.csv', booksCsv($books));
$w->csv('books/stock.csv', stockCsv($books));
$w->xml('books/onix.xml', onix($books));

foreach ($books as $book) {
    $w->json("books/show/{$book['isbn']}.json", ['data' => $book]);
    $w->xml("books/show/{$book['isbn']}.xml", booksXml([$book], single: true));
    cover($w, $book);
}

// Pagination, four ways. Links are relative, so they resolve against the page URL.
$pages = array_chunk($books, PAGE_SIZE);
$count = count($pages);

foreach ($pages as $n => $items) {
    $page = $n + 1;

    $w->json("books/paged/next-url/page-{$page}.json", [
        'data' => $items,
        'links' => ['next' => $page < $count ? 'page-'.($page + 1).'.json' : null],
    ]);

    $w->json("books/paged/numbered/{$page}.json", [
        'data' => $items,
        'meta' => ['current_page' => $page, 'last_page' => $count, 'per_page' => PAGE_SIZE, 'total' => count($books)],
    ]);

    $offset = $n * PAGE_SIZE;
    $w->json("books/paged/offset/{$offset}.json", [
        'results' => $items,
        'offset' => $offset,
        'limit' => PAGE_SIZE,
        'count' => count($books),
    ]);

    $cursor = $page === 1 ? 'start' : substr(sha1("books-{$page}"), 0, 10);
    $next = $page < $count ? substr(sha1('books-'.($page + 1)), 0, 10) : null;
    $w->json("books/paged/cursor/{$cursor}.json", [
        'items' => $items,
        'next_cursor' => $next,
        'has_more' => $next !== null,
    ]);

    $w->xml("books/paged/xml/page-{$page}.xml", booksXml($items, next: $page < $count ? 'page-'.($page + 1).'.xml' : null));
}

/*
|--------------------------------------------------------------------------
| States: the same feed over time, for sync tests
|--------------------------------------------------------------------------
*/

$v1 = array_slice($books, 0, 20);

$v2 = $v1;
foreach ([1, 4, 9, 12, 17] as $i) {          // prices changed
    $v2[$i]['formats'][1]['price'] = money((float) $v2[$i]['formats'][1]['price'] + 1.5);
    $v2[$i]['updated_at'] = '2026-10-02T09:00:00+00:00';
}
$v2[3]['title'] .= ' (Revised Edition)';      // title changed
$v2[3]['updated_at'] = '2026-10-02T09:00:00+00:00';
$removed = $v2[6]['isbn'];
unset($v2[6]);                                 // one gone
$v2[] = $books[20];                            // one new
$v2 = array_values($v2);

$v3 = $v2;
$v3[0]['cover'] = 'images/covers/'.$v3[0]['isbn'].'-v3.png';
cover($w, $v3[0], variant: true);              // changed image

foreach (['v1' => $v1, 'v2' => $v2, 'v3' => $v3] as $state => $items) {
    $w->json("states/books/{$state}.json", ['data' => $items]);
    $w->xml("states/books/{$state}.xml", booksXml($items));
    $w->csv("states/books/{$state}.csv", booksCsv($items));
}

$w->json('states/books/empty.json', ['data' => []]);
$w->xml('states/books/empty.xml', booksXml([]));
$w->csv('states/books/empty.csv', [array_keys(booksCsv($v1)[0] ?? [])]);
$w->raw('states/books/truncated.json', substr(json_encode(['data' => $v1], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 0, 2500));
$w->raw('states/books/malformed.xml', "<?xml version=\"1.0\"?>\n<catalogue><book><isbn>{$v1[0]['isbn']}</isbn><title>Broken & unescaped</book>\n");
$w->raw('states/books/not-a-feed.html', "<!doctype html><title>Login</title><p>Please sign in to continue.</p>\n");
$w->raw('states/books/xxe.xml', "<?xml version=\"1.0\"?>\n<!DOCTYPE catalogue [<!ENTITY secret SYSTEM \"file:///etc/passwd\">]>\n<catalogue><book><isbn>9780000000001</isbn><title>&secret;</title></book></catalogue>\n");

// CSV dialects.
$rows = booksCsv($v1);
$w->csv('states/csv/semicolon.csv', $rows, ';');
$w->csv('states/csv/tab.tsv', $rows, "\t");
$w->raw('states/csv/bom.csv', "\xEF\xBB\xBF".$w->csvString($rows));
$w->raw('states/csv/windows-1252.csv', mb_convert_encoding($w->csvString(accented()), 'Windows-1252', 'UTF-8'));

$w->json('states/books/manifest.json', [
    'v1' => ['items' => count($v1)],
    'v2' => ['items' => count($v2), 'price_changed' => 5, 'title_changed' => 1, 'removed' => [$removed], 'added' => [$books[20]['isbn']]],
    'v3' => ['items' => count($v3), 'cover_changed' => [$v3[0]['isbn']]],
]);

/*
|--------------------------------------------------------------------------
| Products: Google Merchant–style XML and a variants JSON
|--------------------------------------------------------------------------
*/

$colours = ['Black', 'Natural', 'Forest', 'Rust', 'Navy'];
$sizes = ['S', 'M', 'L', 'XL'];
$products = [];
for ($i = 1; $i <= 12; $i++) {
    $group = sprintf('DE-%03d', $i);
    $name = $words->product();
    $price = mt_rand(800, 4500) / 100;
    $options = [];
    foreach (pick($colours, mt_rand(1, 3)) as $colour) {
        foreach ($i % 3 === 0 ? ['One size'] : $sizes as $size) {
            $options[] = [
                'sku' => $group.'-'.strtoupper(substr($colour, 0, 3)).'-'.str_replace(' ', '', $size),
                'colour' => $colour,
                'size' => $size,
                'price' => money($price),
                'stock' => mt_rand(0, 30),
            ];
        }
    }
    $products[] = [
        'id' => $group,
        'name' => $name,
        'description' => $words->sentence(16),
        'brand' => 'Dog-ear Goods',
        'image' => "images/products/{$group}.png",
        'options' => $options,
    ];
    swatch($w, "images/products/{$group}.png", $name);
}
$w->json('products/variants.json', ['products' => $products]);
$w->xml('products/merchant.xml', merchant($products));

/*
|--------------------------------------------------------------------------
| Property: BLM, Kyero XML and JSON
|--------------------------------------------------------------------------
*/

$properties = [];
for ($i = 1; $i <= 25; $i++) {
    $ref = sprintf('DEA%04d', $i);
    $beds = mt_rand(1, 5);
    $properties[] = [
        'ref' => $ref,
        'branch_id' => mt_rand(1, 2) === 1 ? '10001' : '10002',
        'address_1' => mt_rand(1, 120).' '.$words->street(),
        'town' => $words->town(),
        'postcode' => $words->postcode(),
        'bedrooms' => $beds,
        'bathrooms' => max(1, $beds - mt_rand(0, 2)),
        'price' => $i % 4 === 0 ? mt_rand(900, 2600) : mt_rand(180, 950) * 1000,
        'transaction' => $i % 4 === 0 ? 'let' : 'sale',
        'type' => ['Terraced', 'Semi-detached', 'Detached', 'Flat', 'Cottage'][mt_rand(0, 4)],
        'summary' => $words->sentence(18),
        'description' => implode("\n\n", $words->paragraphs(2)),
        'features' => pick(['Garden', 'Parking', 'Fireplace', 'Near station', 'Period features', 'New kitchen', 'Garage'], 3),
        'status' => $i % 7 === 0 ? 'sold_stc' : 'available',
        'images' => ["images/property/{$ref}-1.png", "images/property/{$ref}-2.png"],
        'updated_at' => NOW,
    ];
    swatch($w, "images/property/{$ref}-1.png", $ref.' front');
    swatch($w, "images/property/{$ref}-2.png", $ref.' garden');
}
$w->json('property/listings.json', ['properties' => $properties]);
$w->raw('property/listings.blm', blm($properties));
$w->xml('property/kyero.xml', kyero($properties));

/*
|--------------------------------------------------------------------------
| Jobs
|--------------------------------------------------------------------------
*/

$jobs = [];
for ($i = 1; $i <= 18; $i++) {
    $posted = strtotime('2026-09-01') + mt_rand(0, 28) * 86400;
    $jobs[] = [
        'id' => 'JOB-'.(4000 + $i),
        'title' => ['Bookseller', 'Senior Bookseller', 'Events Coordinator', 'Warehouse Assistant', 'Web Editor', 'Children\'s Buyer'][mt_rand(0, 5)],
        'location' => $words->town(),
        'type' => ['Full time', 'Part time', 'Seasonal'][mt_rand(0, 2)],
        'salary' => ['min' => $min = mt_rand(22, 32) * 1000, 'max' => $min + mt_rand(1, 6) * 1000, 'currency' => 'GBP', 'period' => 'year'],
        'description_html' => '<p>'.implode('</p><p>', $words->paragraphs(2)).'</p><ul><li>'.implode('</li><li>', [$words->phrase(5), $words->phrase(6), $words->phrase(4)]).'</li></ul>',
        'posted_at' => date('c', $posted),
        'closes_at' => date('Y-m-d', $posted + 30 * 86400),
        'apply_url' => 'https://jobs.example.test/apply/'.(4000 + $i),
    ];
}
$w->json('jobs/jobs.json', ['jobs' => $jobs]);
$w->xml('jobs/jobs.xml', jobsXml($jobs));

/*
|--------------------------------------------------------------------------
| Events: a ticketing-API shape, index + show + cursor pages
|--------------------------------------------------------------------------
*/

$venues = [
    ['id' => 'v1', 'name' => 'Dog-ear Books, Lewes', 'city' => 'Lewes'],
    ['id' => 'v2', 'name' => 'The Corn Exchange', 'city' => 'Brighton'],
    ['id' => 'v3', 'name' => 'Town Hall Library', 'city' => 'Hastings'],
];
$events = [];
for ($i = 1; $i <= 30; $i++) {
    $start = strtotime('2026-10-05 18:30:00 Europe/London') + $i * 3 * 86400;
    $events[] = [
        'id' => 'evt_'.substr(sha1("event{$i}"), 0, 12),
        'name' => 'An evening with '.$authors[mt_rand(0, count($authors) - 1)],
        'summary' => $words->sentence(14),
        'starts_at' => date('Y-m-d\TH:i:sP', $start),
        'ends_at' => date('Y-m-d\TH:i:sP', $start + 90 * 60),
        'timezone' => 'Europe/London',
        'venue' => $venues[mt_rand(0, 2)],
        'tickets' => ['price' => money(mt_rand(0, 3) * 5), 'available' => mt_rand(0, 80)],
        'status' => $i % 11 === 0 ? 'cancelled' : 'on_sale',
    ];
}
$w->json('events/index.json', ['events' => $events]);
foreach ($events as $event) {
    $w->json("events/show/{$event['id']}.json", $event);
}
foreach (array_chunk($events, 10) as $n => $items) {
    $cursor = $n === 0 ? 'start' : 'c'.($n * 10);
    $w->json("events/paged/{$cursor}.json", [
        'events' => $items,
        'pagination' => ['next' => $n < 2 ? 'c'.(($n + 1) * 10).'.json' : null],
    ]);
}

/*
|--------------------------------------------------------------------------
| News: RSS, Atom and a podcast feed
|--------------------------------------------------------------------------
*/

$posts = [];
for ($i = 1; $i <= 15; $i++) {
    $posts[] = [
        'guid' => 'urn:dog-ear:post:'.$i,
        'title' => $words->title(),
        'link' => 'https://news.example.test/posts/'.$i,
        'date' => strtotime('2026-09-30 12:00:00 UTC') - $i * 2 * 86400,
        'author' => $authors[mt_rand(0, count($authors) - 1)],
        'summary' => $words->sentence(20),
        'html' => '<p>'.implode('</p><p>', $words->paragraphs(3)).'</p>',
        'category' => pick(['Events', 'New in', 'Interviews', 'Prizes'], 1)[0],
        'image' => 'images/news/post-'.$i.'.png',
    ];
    swatch($w, 'images/news/post-'.$i.'.png', 'Post '.$i);
}
$w->xml('news/rss.xml', rss($posts));
$w->xml('news/atom.xml', atom($posts));
$w->xml('news/podcast.xml', podcast(array_slice($posts, 0, 6)));

/*
|--------------------------------------------------------------------------
| Settings (a single object → Globals) and a Google-Sheets-style CSV
|--------------------------------------------------------------------------
*/

$hours = [
    'shop' => 'Dog-ear Books',
    'phone' => '01273 000000',
    'hours' => [
        ['day' => 'Monday', 'opens' => '09:00', 'closes' => '17:30'],
        ['day' => 'Tuesday', 'opens' => '09:00', 'closes' => '17:30'],
        ['day' => 'Wednesday', 'opens' => '09:00', 'closes' => '17:30'],
        ['day' => 'Thursday', 'opens' => '09:00', 'closes' => '20:00'],
        ['day' => 'Friday', 'opens' => '09:00', 'closes' => '17:30'],
        ['day' => 'Saturday', 'opens' => '10:00', 'closes' => '18:00'],
        ['day' => 'Sunday', 'opens' => null, 'closes' => null],
    ],
    'notice' => 'Late opening every Thursday.',
    'updated_at' => NOW,
];
$w->json('settings/opening-hours.json', $hours);

$picks = [['Timestamp', 'Staff member', 'ISBN', 'Why we love it', 'Featured?', 'Order']];
foreach (array_slice($books, 0, 10) as $n => $book) {
    $picks[] = [
        date('d/m/Y H:i:s', strtotime('2026-09-20 10:00') + $n * 3600),
        ['Ali', 'Morgan', 'Sam', 'Jo'][$n % 4],
        $book['isbn'],
        $words->sentence(12),
        $n < 3 ? 'TRUE' : 'FALSE',
        (string) ($n + 1),
    ];
}
$w->csv('sheets/staff-picks.csv', $picks);

$w->report();

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function isbn13(int $n): string
{
    $digits = '979'.'1'.str_pad((string) $n, 8, '0', STR_PAD_LEFT);
    $sum = 0;
    foreach (str_split($digits) as $i => $d) {
        $sum += (int) $d * ($i % 2 === 0 ? 1 : 3);
    }

    return $digits.((10 - $sum % 10) % 10);
}

function money(float $amount): string
{
    return number_format(max(0, $amount), 2, '.', '');
}

function pick(array $pool, int $n): array
{
    $keys = [];
    while (count($keys) < min($n, count($pool))) {
        $keys[mt_rand(0, count($pool) - 1)] = true;
    }
    ksort($keys);

    return array_values(array_intersect_key($pool, $keys));
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function booksXml(array $books, bool $single = false, ?string $next = null): string
{
    $xml = '';
    foreach ($books as $b) {
        $xml .= "  <book id=\"{$b['isbn']}\" status=\"{$b['status']}\">\n";
        $xml .= '    <isbn>'.$b['isbn']."</isbn>\n";
        $xml .= '    <title>'.e($b['title'])."</title>\n";
        if ($b['subtitle']) {
            $xml .= '    <subtitle>'.e($b['subtitle'])."</subtitle>\n";
        }
        $xml .= "    <contributors>\n";
        foreach ($b['contributors'] as $c) {
            $xml .= "      <contributor role=\"{$c['role']}\">".e($c['name'])."</contributor>\n";
        }
        $xml .= "    </contributors>\n";
        $xml .= '    <publisher>'.e($b['publisher'])."</publisher>\n";
        $xml .= "    <published>{$b['published']}</published>\n";
        $xml .= "    <pages>{$b['pages']}</pages>\n";
        $xml .= "    <subjects>\n";
        foreach ($b['subjects'] as $s) {
            $xml .= '      <subject>'.e($s)."</subject>\n";
        }
        $xml .= "    </subjects>\n";
        $xml .= '    <description><![CDATA['.$b['description_html']."]]></description>\n";
        $xml .= "    <cover>{$b['cover']}</cover>\n";
        $xml .= "    <formats>\n";
        foreach ($b['formats'] as $f) {
            $stock = $f['stock'] === null ? '' : "<stock>{$f['stock']}</stock>";
            $xml .= "      <format sku=\"{$f['sku']}\"><binding>{$f['binding']}</binding><price currency=\"GBP\">{$f['price']}</price>{$stock}</format>\n";
        }
        $xml .= "    </formats>\n";
        $xml .= "    <updated>{$b['updated_at']}</updated>\n";
        $xml .= "  </book>\n";
    }

    if ($single) {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".ltrim(preg_replace('/^  /m', '', $xml));
    }

    $nextTag = $next ? "  <next>{$next}</next>\n" : '';

    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<catalogue generated=\"".NOW."\">\n{$nextTag}{$xml}</catalogue>\n";
}

function booksCsv(array $books): array
{
    $rows = [];
    foreach ($books as $b) {
        $rows[] = [
            'isbn' => $b['isbn'],
            'title' => $b['title'],
            'subtitle' => (string) $b['subtitle'],
            'authors' => implode('|', array_column(array_filter($b['contributors'], fn ($c) => $c['role'] === 'author'), 'name')),
            'publisher' => $b['publisher'],
            'published' => date('d/m/Y', strtotime($b['published'])),
            'pages' => (string) $b['pages'],
            'subjects' => implode('|', $b['subjects']),
            'description' => strip_tags($b['description_html']),
            'cover' => $b['cover'],
            'paperback_price' => $b['formats'][1]['price'],
            'paperback_stock' => (string) $b['formats'][1]['stock'],
            'status' => $b['status'],
        ];
    }

    return $rows === [] ? [] : array_merge([array_keys($rows[0])], array_map('array_values', $rows));
}

function stockCsv(array $books): array
{
    $rows = [['sku', 'price', 'stock']];
    foreach ($books as $b) {
        foreach ($b['formats'] as $f) {
            if ($f['stock'] !== null) {
                $rows[] = [$f['sku'], $f['price'], (string) $f['stock']];
            }
        }
    }

    return $rows;
}

function accented(): array
{
    return [
        ['ref', 'title', 'author', 'price'],
        ['A1', 'Café society', 'Zoë Brontë', '£12.99'],
        ['A2', 'Smörgåsbord', 'José Núñez', '£9.50'],
        ['A3', 'Déjà vu – a memoir', 'François Lefèvre', '£14.00'],
    ];
}

function onix(array $books): string
{
    $e = 'e'; // lets the heredoc call e()
    $products = '';
    foreach ($books as $b) {
        foreach ($b['formats'] as $f) {
            $form = ['Hardback' => 'BB', 'Paperback' => 'BC', 'Ebook' => 'ED'][$f['binding']];
            $contributors = '';
            foreach ($b['contributors'] as $n => $c) {
                $role = $c['role'] === 'author' ? 'A01' : 'A12';
                $contributors .= '<Contributor><SequenceNumber>'.($n + 1)."</SequenceNumber><ContributorRole>{$role}</ContributorRole><PersonName>".e($c['name']).'</PersonName></Contributor>';
            }
            $subjects = '';
            foreach ($b['subjects'] as $s) {
                $subjects .= '<Subject><SubjectSchemeIdentifier>20</SubjectSchemeIdentifier><SubjectHeadingText>'.e($s).'</SubjectHeadingText></Subject>';
            }
            $stock = $f['stock'] === null ? '' : "<Stock><OnHand>{$f['stock']}</OnHand></Stock>";
            $products .= <<<XML
  <Product>
    <RecordReference>{$f['sku']}</RecordReference>
    <NotificationType>03</NotificationType>
    <ProductIdentifier><ProductIDType>15</ProductIDType><IDValue>{$b['isbn']}</IDValue></ProductIdentifier>
    <DescriptiveDetail>
      <ProductForm>{$form}</ProductForm>
      <TitleDetail><TitleType>01</TitleType><TitleElement><TitleElementLevel>01</TitleElementLevel><TitleText>{$e($b['title'])}</TitleText></TitleElement></TitleDetail>
      {$contributors}
      <Extent><ExtentType>00</ExtentType><ExtentValue>{$b['pages']}</ExtentValue><ExtentUnit>03</ExtentUnit></Extent>
      {$subjects}
    </DescriptiveDetail>
    <CollateralDetail>
      <TextContent><TextType>03</TextType><Text textformat="05"><![CDATA[{$b['description_html']}]]></Text></TextContent>
      <SupportingResource><ResourceContentType>01</ResourceContentType><ResourceVersion><ResourceLink>{$b['cover']}</ResourceLink></ResourceVersion></SupportingResource>
    </CollateralDetail>
    <PublishingDetail>
      <Publisher><PublishingRole>01</PublishingRole><PublisherName>{$e($b['publisher'])}</PublisherName></Publisher>
      <PublishingStatus>{$e($b['status'] === 'live' ? '04' : '07')}</PublishingStatus>
      <PublishingDate><PublishingDateRole>01</PublishingDateRole><Date>{$e(str_replace('-', '', $b['published']))}</Date></PublishingDate>
    </PublishingDetail>
    <ProductSupply><SupplyDetail>{$stock}<Price><PriceType>02</PriceType><PriceAmount>{$f['price']}</PriceAmount><CurrencyCode>GBP</CurrencyCode></Price></SupplyDetail></ProductSupply>
  </Product>

XML;
        }
    }

    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<ONIXMessage release=\"3.0\" xmlns=\"http://ns.editeur.org/onix/3.0/reference\">\n  <Header><Sender><SenderName>Made-up Distribution Ltd</SenderName></Sender><SentDateTime>20261001T0900Z</SentDateTime></Header>\n{$products}</ONIXMessage>\n";
}

function merchant(array $products): string
{
    $items = '';
    foreach ($products as $p) {
        foreach ($p['options'] as $o) {
            $title = e("{$p['name']} – {$o['colour']}, {$o['size']}");
            $availability = $o['stock'] > 0 ? 'in_stock' : 'out_of_stock';
            $items .= "    <item>\n      <g:id>{$o['sku']}</g:id>\n      <g:item_group_id>{$p['id']}</g:item_group_id>\n      <title>{$title}</title>\n      <description>".e($p['description'])."</description>\n      <link>https://shop.example.test/p/{$p['id']}</link>\n      <g:image_link>{$p['image']}</g:image_link>\n      <g:price>{$o['price']} GBP</g:price>\n      <g:availability>{$availability}</g:availability>\n      <g:color>{$o['colour']}</g:color>\n      <g:size>{$o['size']}</g:size>\n      <g:brand>".e($p['brand'])."</g:brand>\n      <g:quantity>{$o['stock']}</g:quantity>\n    </item>\n";
        }
    }

    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<rss version=\"2.0\" xmlns:g=\"http://base.google.com/ns/1.0\">\n  <channel>\n    <title>Dog-ear Goods</title>\n    <link>https://shop.example.test</link>\n    <description>Made-up product feed</description>\n{$items}  </channel>\n</rss>\n";
}

function blm(array $properties): string
{
    $fields = ['AGENT_REF', 'BRANCH_ID', 'STATUS_ID', 'BEDROOMS', 'PRICE', 'ADDRESS_1', 'TOWN', 'POSTCODE1', 'POSTCODE2', 'FEATURE1', 'FEATURE2', 'FEATURE3', 'SUMMARY', 'DESCRIPTION', 'PROP_SUB_ID', 'TRANS_TYPE_ID', 'CREATE_DATE', 'UPDATE_DATE', 'MEDIA_IMAGE_00', 'MEDIA_IMAGE_01'];
    $types = ['Terraced' => 1, 'Semi-detached' => 3, 'Detached' => 4, 'Flat' => 8, 'Cottage' => 23];
    $out = "#HEADER#\nVersion : 3\nEOF : '^'\nEOR : '~'\nProperty Count : ".count($properties)."\nGenerated Date : 01-Oct-2026 09:00\n\n";
    $out .= "#DEFINITION#\n".implode('^', $fields)."^~\n\n#DATA#\n";
    foreach ($properties as $p) {
        [$pc1, $pc2] = explode(' ', $p['postcode']);
        $row = [
            $p['branch_id'].'_'.$p['ref'], $p['branch_id'], $p['status'] === 'available' ? '0' : '1', $p['bedrooms'], $p['price'],
            $p['address_1'], $p['town'], $pc1, $pc2,
            $p['features'][0] ?? '', $p['features'][1] ?? '', $p['features'][2] ?? '',
            $p['summary'], str_replace("\n\n", '<br><br>', $p['description']),
            $types[$p['type']], $p['transaction'] === 'sale' ? '1' : '2',
            '2026-09-01 09:00:00', '2026-10-01 09:00:00',
            $p['images'][0], $p['images'][1],
        ];
        $out .= implode('^', array_map(fn ($v) => str_replace(['^', '~'], '', (string) $v), $row))."^~\n";
    }

    return $out."#END#\n";
}

function kyero(array $properties): string
{
    $xml = '';
    foreach ($properties as $p) {
        $images = '';
        foreach ($p['images'] as $n => $url) {
            $images .= '<image id="'.($n + 1).'"><url>'.e($url).'</url></image>';
        }
        $features = implode('', array_map(fn ($f) => '<feature>'.e($f).'</feature>', $p['features']));
        $freq = $p['transaction'] === 'let' ? '<price_freq>month</price_freq>' : '<price_freq>sale</price_freq>';
        $xml .= "  <property>\n    <id>{$p['ref']}</id>\n    <date>2026-10-01 09:00:00</date>\n    <ref>{$p['ref']}</ref>\n    <price>{$p['price']}</price>\n    <currency>GBP</currency>\n    {$freq}\n    <type>".e($p['type'])."</type>\n    <town>".e($p['town'])."</town>\n    <province>East Sussex</province>\n    <beds>{$p['bedrooms']}</beds>\n    <baths>{$p['bathrooms']}</baths>\n    <desc><en>".e($p['description'])."</en></desc>\n    <features>{$features}</features>\n    <images>{$images}</images>\n  </property>\n";
    }

    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<root>\n  <kyero><feed_version>3</feed_version></kyero>\n{$xml}</root>\n";
}

function jobsXml(array $jobs): string
{
    $xml = '';
    foreach ($jobs as $j) {
        $xml .= "  <job>\n    <referencenumber>{$j['id']}</referencenumber>\n    <title>".e($j['title'])."</title>\n    <city>".e($j['location'])."</city>\n    <country>GB</country>\n    <jobtype>".e($j['type'])."</jobtype>\n    <salary>£{$j['salary']['min']} - £{$j['salary']['max']} per year</salary>\n    <date>".date('D, d M Y H:i:s O', strtotime($j['posted_at']))."</date>\n    <expirationdate>{$j['closes_at']}</expirationdate>\n    <url>".e($j['apply_url'])."</url>\n    <description><![CDATA[{$j['description_html']}]]></description>\n  </job>\n";
    }

    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<source>\n  <publisher>Dog-ear Books</publisher>\n  <lastBuildDate>".date('D, d M Y H:i:s O', strtotime(NOW))."</lastBuildDate>\n{$xml}</source>\n";
}

function rss(array $posts): string
{
    $items = '';
    foreach ($posts as $p) {
        $items .= "    <item>\n      <title>".e($p['title'])."</title>\n      <link>{$p['link']}</link>\n      <guid isPermaLink=\"false\">{$p['guid']}</guid>\n      <pubDate>".gmdate('D, d M Y H:i:s +0000', $p['date'])."</pubDate>\n      <dc:creator>".e($p['author'])."</dc:creator>\n      <category>".e($p['category'])."</category>\n      <description>".e($p['summary'])."</description>\n      <content:encoded><![CDATA[{$p['html']}]]></content:encoded>\n      <media:content url=\"{$p['image']}\" medium=\"image\" type=\"image/png\"/>\n    </item>\n";
    }

    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<rss version=\"2.0\" xmlns:content=\"http://purl.org/rss/1.0/modules/content/\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\" xmlns:media=\"http://search.yahoo.com/mrss/\">\n  <channel>\n    <title>Made-up Publisher News</title>\n    <link>https://news.example.test</link>\n    <description>Fake news for feed tests</description>\n    <language>en-gb</language>\n{$items}  </channel>\n</rss>\n";
}

function atom(array $posts): string
{
    $entries = '';
    foreach ($posts as $p) {
        $entries .= "  <entry>\n    <id>{$p['guid']}</id>\n    <title>".e($p['title'])."</title>\n    <link rel=\"alternate\" href=\"{$p['link']}\"/>\n    <updated>".gmdate('Y-m-d\TH:i:s\Z', $p['date'])."</updated>\n    <author><name>".e($p['author'])."</name></author>\n    <category term=\"".e($p['category'])."\"/>\n    <summary>".e($p['summary'])."</summary>\n    <content type=\"html\">".e($p['html'])."</content>\n  </entry>\n";
    }

    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<feed xmlns=\"http://www.w3.org/2005/Atom\">\n  <id>urn:dog-ear:news</id>\n  <title>Made-up Publisher News</title>\n  <updated>".gmdate('Y-m-d\TH:i:s\Z', $posts[0]['date'])."</updated>\n  <link rel=\"self\" href=\"atom.xml\"/>\n{$entries}</feed>\n";
}

function podcast(array $posts): string
{
    $items = '';
    foreach ($posts as $n => $p) {
        $items .= "    <item>\n      <title>".e($p['title'])."</title>\n      <guid isPermaLink=\"false\">{$p['guid']}:episode</guid>\n      <pubDate>".gmdate('D, d M Y H:i:s +0000', $p['date'])."</pubDate>\n      <description>".e($p['summary'])."</description>\n      <enclosure url=\"https://audio.example.test/ep".($n + 1).".mp3\" length=\"".(20000000 + $n * 1000)."\" type=\"audio/mpeg\"/>\n      <itunes:duration>".sprintf('%02d:%02d', 30 + $n, 15)."</itunes:duration>\n      <itunes:episode>".($n + 1)."</itunes:episode>\n    </item>\n";
    }

    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<rss version=\"2.0\" xmlns:itunes=\"http://www.itunes.com/dtds/podcast-1.0.dtd\">\n  <channel>\n    <title>The Dog-ear Podcast</title>\n    <link>https://podcast.example.test</link>\n    <description>A made-up podcast feed</description>\n    <itunes:author>Dog-ear Books</itunes:author>\n{$items}  </channel>\n</rss>\n";
}

function cover(Writer $w, array $book, bool $variant = false): void
{
    $w->image($book['cover'], 200, 300, $book['title'], $variant ? 'alt' : $book['isbn']);
}

function swatch(Writer $w, string $path, string $label): void
{
    $w->image($path, 320, 240, $label, $path);
}
