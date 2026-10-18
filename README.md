# Ingest test data

Made-up feeds for testing [Ingest for Statamic](https://github.com/1994limited/ingest-statamic) and anything else that reads feeds. Every file is generated from one seeded dataset by `generator/generate.php`, so it is the same on every run. All names, books, properties, jobs and events are invented.

Read files through their **raw** URLs, not the `github.com/…/blob/…` pages:

```
https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/books/index.json
```

Pin a tag instead of `main` when you need files that never move (docs, tests).

## What's here

### Index endpoints (every item)

| Feed | URL |
|---|---|
| Books, JSON (`data.*`) | [`books/index.json`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/books/index.json) |
| Books, NDJSON | [`books/index.ndjson`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/books/index.ndjson) |
| Books, XML (`catalogue/book`, attributes, CDATA) | [`books/index.xml`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/books/index.xml) |
| Books, CSV (`|`-separated lists, UK dates) | [`books/index.csv`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/books/index.csv) |
| Books, ONIX 3.0–style (one product per format) | [`books/onix.xml`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/books/onix.xml) |
| Stock and prices by SKU | [`books/stock.csv`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/books/stock.csv) |
| Products with variants, JSON | [`products/variants.json`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/products/variants.json) |
| Products, Google Merchant–style RSS (`g:` namespace) | [`products/merchant.xml`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/products/merchant.xml) |
| Property, Rightmove BLM | [`property/listings.blm`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/property/listings.blm) |
| Property, Kyero–style XML | [`property/kyero.xml`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/property/kyero.xml) |
| Property, JSON | [`property/listings.json`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/property/listings.json) |
| Jobs, XML / JSON | [`jobs/jobs.xml`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/jobs/jobs.xml) · [`jobs/jobs.json`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/jobs/jobs.json) |
| Events (ticketing-API shape) | [`events/index.json`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/events/index.json) |
| News, RSS 2.0 (`content:encoded`, `dc:`, `media:`) | [`news/rss.xml`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/news/rss.xml) |
| News, Atom | [`news/atom.xml`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/news/atom.xml) |
| Podcast (enclosures, `itunes:`) | [`news/podcast.xml`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/news/podcast.xml) |
| Staff picks, Google-Sheets-export shape | [`sheets/staff-picks.csv`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/sheets/staff-picks.csv) |

### Show endpoints (one item)

| Feed | Example |
|---|---|
| One book, JSON / XML | [`books/show/.json.json`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/books/show/.json.json) · [`.xml`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/books/show/.json.xml) |
| One event | [`events/show/evt_0b7a171a6e6a.json`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/events/show/evt_0b7a171a6e6a.json) |
| Opening hours (a single object, for globals) | [`settings/opening-hours.json`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/settings/opening-hours.json) |

### Paginated endpoints

Books come in pages of 25 (60 books, 3 pages). Next links are **relative**, so they resolve against the page's own URL.

| Strategy | Start here | How to find the next page |
|---|---|---|
| Next URL in the body | [`books/paged/next-url/page-1.json`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/books/paged/next-url/page-1.json) | `links.next` (null on the last page) |
| Page number in the path | [`books/paged/numbered/1.json`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/books/paged/numbered/1.json) | `{page}.json` up to `meta.last_page` |
| Offset in the path | [`books/paged/offset/0.json`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/books/paged/offset/0.json) | `{offset}.json` in steps of `limit` until `count` |
| Cursor | [`books/paged/cursor/start.json`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/books/paged/cursor/start.json) | `{next_cursor}.json` while `has_more` |
| XML next element | [`books/paged/xml/page-1.xml`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/books/paged/xml/page-1.xml) | `catalogue/next` |
| Events cursor | [`events/paged/start.json`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/events/paged/start.json) | `pagination.next` |

Static hosting ignores query strings, so `?page=2` style pagination, auth checks, error codes and slow responses can't be served from here. Test those locally.

### States: the same feed over time

For sync tests: point a feed at `v1`, run it, switch to `v2`, run again. [`states/books/manifest.json`](https://raw.githubusercontent.com/1994limited/ingest-statamic-test-data/main/states/books/manifest.json) lists exactly what changed.

| File | What it is |
|---|---|
| `states/books/v1.{json,xml,csv}` | 20 books |
| `states/books/v2.{json,xml,csv}` | 5 prices changed, 1 title changed, 1 book removed, 1 added |
| `states/books/v3.{json,xml,csv}` | v2 with one cover image changed (new URL and new file) |
| `states/books/empty.{json,xml,csv}` | Valid, but no items |
| `states/books/truncated.json` | Cut off mid-file |
| `states/books/malformed.xml` | Not well-formed |
| `states/books/not-a-feed.html` | An HTML login page where a feed should be |
| `states/books/xxe.xml` | Declares an external entity; a safe parser must not expand it |
| `states/csv/semicolon.csv`, `tab.tsv`, `bom.csv`, `windows-1252.csv` | CSV dialects and encodings |

### Things worth knowing

- Book 7 and book 13 carry hostile HTML in their descriptions (`<script>`, `onerror`, `javascript:` links, an iframe), for testing sanitising.
- Images live in `images/` and are referenced by **relative** paths (`images/covers/{isbn}.png`); resolve them against the repository root.
- ISBNs use the 979-1 prefix with a valid check digit; they are not real books.
- Raw GitHub serves everything as `text/plain` and caches for about five minutes.

## Regenerating

```bash
php generator/generate.php
```

Needs PHP 8.3+ with GD. CI regenerates and fails if the committed files differ, so change the generator, never the output.

## Licence

[CC0 1.0](LICENSE.md): no rights reserved. Use it for anything.
