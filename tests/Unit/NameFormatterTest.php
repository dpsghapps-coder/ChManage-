<?php

namespace Tests\Unit;

use App\Support\NameFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NameFormatterTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: ?string}> */
    public static function names(): array
    {
        return [
            'shouting' => ['OWUSU ANSAH KWAME', 'Owusu Ansah Kwame'],
            'lower case' => ['kwame mensah', 'Kwame Mensah'],
            'only all-caps or all-lower pieces are rewritten' => ['aGNES OBENEWAA', 'aGNES Obenewaa'],
            'hyphenated' => ['OWUSU-ANSAH', 'Owusu-Ansah'],
            'hyphen part in lower case' => ['Eben-ezer', 'Eben-Ezer'],
            'apostrophe' => ["O'BRIEN", "O'Brien"],
            'titles with a dot' => ['REV. DR. S. AYEYE NYAMPONG', 'Rev. Dr. S. Ayeye Nyampong'],
            'initials' => ['t.k abankwa', 'T.K Abankwa'],
            'single initial' => ['j', 'J'],
            'roman numerals' => ['KWAME II', 'Kwame II'],
            'double and edge spaces' => ["  Owusu   Agnes\tKorankyewaa \n", 'Owusu Agnes Korankyewaa'],
            'already right' => ['Kwame Mensah', 'Kwame Mensah'],
            'own capitals are kept' => ['McDonald DeGraft', 'McDonald DeGraft'],
            'accents' => ['ÉLODIE ÖZTÜRK', 'Élodie Öztürk'],
            'empty' => ['', ''],
            'only spaces' => ['   ', ''],
            'null' => [null, null],
        ];
    }

    #[DataProvider('names')]
    public function test_it_puts_names_in_title_case(?string $given, ?string $expected): void
    {
        $this->assertSame($expected, NameFormatter::titleCase($given));
    }

    public function test_it_is_idempotent(): void
    {
        foreach (self::names() as [$given]) {
            $once = NameFormatter::titleCase($given);

            $this->assertSame($once, NameFormatter::titleCase($once));
        }
    }
}
