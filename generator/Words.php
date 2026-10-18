<?php

declare(strict_types=1);

namespace Ingest\TestData;

/**
 * Made-up names, titles and prose. Everything comes from mt_rand(), which
 * generate.php seeds, so the output never changes between runs.
 */
class Words
{
    private const FIRST = ['Ada', 'Bea', 'Cal', 'Dora', 'Eli', 'Fern', 'Gus', 'Hattie', 'Ivo', 'June', 'Kit', 'Lola', 'Milo', 'Nell', 'Otto', 'Pip', 'Rosa', 'Sol', 'Tess', 'Wren'];

    private const LAST = ['Ashdown', 'Bramley', 'Calloway', 'Dunmore', 'Everleigh', 'Fairweather', 'Gilchrist', 'Hartwell', 'Ingram', 'Kettering', 'Lockwood', 'Marchbank', 'Netherby', 'Oakhurst', 'Pennywhistle', 'Quill', 'Rookwood', 'Stanhope', 'Thistlewood', 'Underhill'];

    private const ADJ = ['Quiet', 'Lost', 'Silver', 'Hidden', 'Winter', 'Distant', 'Paper', 'Crooked', 'Golden', 'Salt', 'Small', 'Borrowed', 'Last', 'Wild', 'Folded'];

    private const NOUN = ['Harbour', 'Orchard', 'Lantern', 'Atlas', 'Garden', 'River', 'Kingdom', 'Library', 'Compass', 'Meadow', 'Lighthouse', 'Archive', 'Hedgerow', 'Tide', 'Station'];

    private const WORDS = ['the', 'a', 'quiet', 'river', 'carried', 'letters', 'across', 'town', 'where', 'every', 'house', 'kept', 'secret', 'shelf', 'of', 'old', 'maps', 'and', 'nobody', 'remembered', 'who', 'had', 'drawn', 'them', 'until', 'one', 'summer', 'a', 'stranger', 'arrived', 'with', 'a', 'suitcase', 'full', 'of', 'pressed', 'flowers', 'recipes', 'questions', 'and', 'small', 'kindnesses', 'that', 'changed', 'everything', 'slowly'];

    private const PRODUCT = ['Tote Bag', 'Reading Socks', 'Bookmark Set', 'Enamel Mug', 'Notebook', 'Library Tee', 'Canvas Apron', 'Pencil Case', 'Scarf', 'Beanie', 'Sweatshirt', 'Book Light'];

    private const STREET = ['High Street', 'Mill Lane', 'Church Road', 'Station Road', 'Orchard Close', 'Castle Way', 'School Lane', 'Meadow View'];

    private const TOWN = ['Lewes', 'Ditchling', 'Seaford', 'Hastings', 'Rye', 'Brighton', 'Hove', 'Uckfield', 'Battle', 'Eastbourne'];

    public function person(): string
    {
        return $this->one(self::FIRST).' '.$this->one(self::LAST);
    }

    public function title(): string
    {
        return match (mt_rand(1, 3)) {
            1 => 'The '.$this->one(self::ADJ).' '.$this->one(self::NOUN),
            2 => $this->one(self::ADJ).' '.$this->one(self::NOUN).'s',
            default => (($noun = $this->one(self::NOUN)) && preg_match('/^[AEIOU]/', $noun) ? 'An ' : 'A ').$noun.' for '.$this->one(self::FIRST),
        };
    }

    public function product(): string
    {
        return $this->one(self::ADJ).' '.$this->one(self::PRODUCT);
    }

    public function street(): string
    {
        return $this->one(self::STREET);
    }

    public function town(): string
    {
        return $this->one(self::TOWN);
    }

    public function postcode(): string
    {
        return 'BN'.mt_rand(1, 27).' '.mt_rand(1, 9).chr(mt_rand(65, 90)).chr(mt_rand(65, 90));
    }

    public function phrase(int $words): string
    {
        return ucfirst($this->words($words));
    }

    public function sentence(int $words): string
    {
        return ucfirst($this->words($words)).'.';
    }

    /** @return string[] */
    public function paragraphs(int $count): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $sentences = [];
            for ($s = 0, $n = mt_rand(2, 4); $s < $n; $s++) {
                $sentences[] = $this->sentence(mt_rand(8, 16));
            }
            $out[] = implode(' ', $sentences);
        }

        return $out;
    }

    private function words(int $count): string
    {
        $words = [];
        for ($i = 0; $i < $count; $i++) {
            $words[] = $this->one(self::WORDS);
        }

        return implode(' ', $words);
    }

    private function one(array $pool): string
    {
        return $pool[mt_rand(0, count($pool) - 1)];
    }
}
