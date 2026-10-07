<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Internet;

use Error;
use Fight\Common\Domain\Collection\HashSet;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StringObject;
use Fight\Common\Domain\Value\Internet\E164PhoneNumber;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(E164PhoneNumber::class)]
class E164PhoneNumberTest extends UnitTestCase
{
    #[DataProvider('validNumbers')]
    public function test_that_factory_preserves_lexical_numbers_and_round_trips(string $input): void
    {
        $number = E164PhoneNumber::fromString($input);

        self::assertSame($input, $number->toString());
        self::assertSame($input, (string) $number);
        self::assertSame($input, $number->jsonSerialize());
        self::assertSame(json_encode($input, JSON_THROW_ON_ERROR), json_encode($number, JSON_THROW_ON_ERROR));
        self::assertTrue($number->equals(E164PhoneNumber::fromString($number->toString())));
        self::assertTrue($number->equals(E164PhoneNumber::fromString(
            json_decode(json_encode($number, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR)
        )));
        self::assertSame($input, $number->hashValue());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validNumbers(): iterable
    {
        yield 'one digit lower edge' => ['+1'];
        yield 'no assigned country code policy' => ['+9'];
        yield 'internal and trailing zeros' => ['+100'];
        yield 'North American form' => ['+15550001234'];
        yield 'UK form' => ['+442079460123'];
        yield 'fifteen digits' => ['+123456789012345'];
        yield 'fifteen nines remain text' => ['+999999999999999'];
    }

    #[DataProvider('invalidNumbers')]
    public function test_that_factory_rejects_invalid_syntax_without_repair(string $input): void
    {
        $this->expectException(DomainException::class);

        E164PhoneNumber::fromString($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNumbers(): iterable
    {
        yield 'empty' => [''];
        yield 'plus only' => ['+'];
        yield 'zero first digit' => ['+0'];
        yield 'leading zero' => ['+0987654321'];
        yield 'missing plus' => ['15550001234'];
        yield 'international prefix is not repaired' => ['00442079460123'];
        yield 'double plus' => ['++15550001234'];
        yield 'embedded plus' => ['+1555+0001234'];
        yield 'trailing plus' => ['+15550001234+'];
        yield 'letter' => ['+1555A001234'];
        yield 'sender ID' => ['FIGHT'];
        yield 'unprefixed short code' => ['12345'];
        yield 'hyphen' => ['+1-5550001234'];
        yield 'parentheses' => ['+1(555)0001234'];
        yield 'period' => ['+1.5550001234'];
        yield 'slash' => ['+1/5550001234'];
        yield 'extension' => ['+15550001234x12'];
        yield 'URI extension' => ['+15550001234;ext=12'];
        yield 'phone URI' => ['tel:+15550001234'];
        yield 'leading space' => [' +15550001234'];
        yield 'embedded space' => ['+1 5550001234'];
        yield 'trailing space' => ['+15550001234 '];
        yield 'leading newline' => ["\n+15550001234"];
        yield 'trailing newline' => ["+15550001234\n"];
        yield 'CRLF' => ["+15550001234\r\n"];
        yield 'tab' => ["+1555\t0001234"];
        yield 'NUL' => ["+15550001234\0"];
        yield 'control byte' => ["+1555\x010001234"];
        yield 'full width plus' => ['＋15550001234'];
        yield 'full width first digit' => ['+１5550001234'];
        yield 'Arabic digit suffix' => ['+1١'];
        yield 'nonbreaking space' => ["+15550001234\u{00A0}"];
        yield 'invalid UTF-8' => ["+1\xff"];
        yield 'sixteen digits' => ['+1234567890123456'];
        yield 'very long input' => ['+'.str_repeat('1', 4096)];
    }

    public function test_that_equality_and_hashes_use_concrete_type_and_exact_string(): void
    {
        $first = E164PhoneNumber::fromString('+15550001234');
        $equal = E164PhoneNumber::fromString('+15550001234');
        $different = E164PhoneNumber::fromString('+15550001235');
        $string = StringObject::fromString('+15550001234');

        self::assertTrue($first->equals($first));
        self::assertNotSame($first, $equal);
        self::assertTrue($first->equals($equal));
        self::assertTrue($equal->equals($first));
        self::assertSame($first->hashValue(), $equal->hashValue());
        self::assertFalse($first->equals($different));
        self::assertNotSame($first->hashValue(), $different->hashValue());
        self::assertFalse($first->equals($string));
        self::assertFalse($string->equals($first));
        self::assertFalse($first->equals('+15550001234'));
        self::assertFalse($first->equals(null));

        $set = HashSet::of(E164PhoneNumber::class);
        $set->add($first);
        $set->add($equal);
        self::assertSame(1, $set->count());
        self::assertTrue($set->contains(E164PhoneNumber::fromString('+15550001234')));
        self::assertFalse($set->contains($different));
        $set->remove(E164PhoneNumber::fromString('+15550001234'));
        self::assertTrue($set->isEmpty());
    }

    public function test_that_number_cannot_acquire_mutable_properties(): void
    {
        $number = E164PhoneNumber::fromString('+15550001234');

        try {
            $number->context = 'changed';
            self::fail('Readonly values must reject mutable properties');
        } catch (Error) {
            self::assertSame('+15550001234', $number->toString());
            self::assertSame('+15550001234', $number->hashValue());
        }
    }
}
