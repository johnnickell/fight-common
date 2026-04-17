<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\Basic;

use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Exception\ImmutableException;
use Fight\Common\Domain\Exception\IndexException;
use Fight\Common\Domain\Value\Basic\Traits\StringOffsets;
use Fight\Common\Domain\Value\ValueObject;
use Fight\Common\Domain\Utility\Validate;
use Traversable;

/**
 * Class StringObject
 */
final readonly class StringObject extends ValueObject
{
    use StringOffsets;

    private int $length;

    /**
     * Constructs StringObject
     */
    private function __construct(private string $value)
    {
        $this->length = strlen($this->value);
    }

    /**
     * @inheritDoc
     */
    public static function fromString(string $value): static
    {
        return new static($value);
    }

    /**
     * Creates instance
     */
    public static function create(string $value): static
    {
        return new static($value);
    }

    /**
     * Retrieves the string value
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Retrieves the string length
     */
    public function length(): int
    {
        return $this->length;
    }

    /**
     * Checks if empty
     */
    public function isEmpty(): bool
    {
        return $this->length === 0;
    }

    /**
     * Retrieves the character count
     */
    public function count(): int
    {
        return $this->length;
    }

    /**
     * Retrieves the character at an index
     *
     * @throws IndexException When the index is invalid
     */
    public function get(int $index): string
    {
        $value = $this->value;
        $length = $this->length;

        if ($index < -$length || $index > $length - 1) {
            $message = sprintf('Index (%d) out of range[%d, %d]', $index, -$length, $length - 1);
            throw new IndexException($message);
        }

        if ($index < 0) {
            $index += $length;
        }

        return $value[$index];
    }

    /**
     * Checks if an index is valid
     */
    public function has(int $index): bool
    {
        $length = $this->length;

        if ($index < -$length || $index > $length - 1) {
            return false;
        }

        return true;
    }

    /**
     * Not implemented
     *
     * @throws ImmutableException When called
     */
    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new ImmutableException('Cannot modify immutable string');
    }

    /**
     * Retrieves the character at an index
     *
     * @throws IndexException When the index is invalid
     */
    public function offsetGet(mixed $offset): string
    {
        assert(Validate::isInt($offset));

        return $this->get($offset);
    }

    /**
     * Checks if an index is valid
     */
    public function offsetExists(mixed $offset): bool
    {
        assert(Validate::isInt($offset));

        return $this->has($offset);
    }

    /**
     * Not implemented
     *
     * @throws ImmutableException When called
     */
    public function offsetUnset(mixed $offset): never
    {
        throw new ImmutableException('Cannot modify immutable string');
    }

    /**
     * Retrieves a list of characters
     */
    public function chars(): ArrayList
    {
        $list = ArrayList::of('string');

        foreach (str_split($this->value) as $char) {
            $list->add($char);
        }

        return $list;
    }

    /**
     * Checks if this string contains a search string
     */
    public function contains(string $search, bool $caseSensitive = true): bool
    {
        if ($this->value === '') {
            return false;
        }

        if ($search === '') {
            return true;
        }

        if ($caseSensitive === false) {
            $result = stripos($this->value, $search);
        } else {
            $result = strpos($this->value, $search);
        }

        if ($result === false) {
            return false;
        }

        return true;
    }

    /**
     * Checks if this string starts with a search string
     */
    public function startsWith(string $search, bool $caseSensitive = true): bool
    {
        if ($this->value === '') {
            return false;
        }

        if ($search === '') {
            return true;
        }

        $searchLength = strlen($search);
        $start = substr($this->value, 0, $searchLength);

        if ($caseSensitive === false) {
            $search = strtolower($search);
            $start = strtolower($start);
        }

        return $search === $start;
    }

    /**
     * Checks if this string ends with a search string
     */
    public function endsWith(string $search, bool $caseSensitive = true): bool
    {
        $length = $this->length;

        if ($this->value === '') {
            return false;
        }

        if ($search === '') {
            return true;
        }

        $searchLength = strlen($search);
        $end = substr($this->value, $length - $searchLength, $searchLength);

        if ($caseSensitive === false) {
            $search = strtolower($search);
            $end = strtolower($end);
        }

        return $search === $end;
    }

    /**
     * Retrieves the first index of a search string
     *
     * Returns -1 if the search string is not found.
     *
     * @throws DomainException When the start index is invalid
     */
    public function indexOf(string $search, ?int $start = null, bool $caseSensitive = true): int
    {
        if ($this->value === '') {
            return -1;
        }

        if ($start === null) {
            $start = 0;
        }
        $start = $this->prepareOffset($start, $this->length);

        if ($search === '') {
            return $start;
        }

        if ($caseSensitive === false) {
            $result = stripos($this->value, $search, $start);
        } else {
            $result = strpos($this->value, $search, $start);
        }

        if ($result === false) {
            return -1;
        }

        return $result;
    }

    /**
     * Retrieves the last index of a search string
     *
     * Returns -1 if the search string is not found.
     *
     * @throws DomainException When the stop index is invalid
     */
    public function lastIndexOf(string $search, ?int $stop = null, bool $caseSensitive = true): int
    {
        $length = $this->length;

        if ($this->value === '') {
            return -1;
        }

        if ($stop === null) {
            $stop = 0;
        }
        if ($stop !== 0) {
            $stop = $this->prepareOffset($stop, $length) - $length;
        }

        if ($search === '') {
            return $stop < 0 ? $stop + $length : $stop;
        }

        if ($caseSensitive === false) {
            $result = strripos($this->value, $search, $stop);
        } else {
            $result = strrpos($this->value, $search, $stop);
        }

        if ($result === false) {
            return -1;
        }

        return $result;
    }

    /**
     * Creates a string with the given string appended
     */
    public function append(string $string): static
    {
        return static::create($this->value.$string);
    }

    /**
     * Creates a string with the given string prepended
     */
    public function prepend(string $string): static
    {
        return static::create($string.$this->value);
    }

    /**
     * Creates a string with the given string inserted at a given index
     *
     * @throws DomainException When the index is out of bounds
     */
    public function insert(int $index, string $string): static
    {
        $length = $this->length;

        $index = $this->prepareOffset($index, $length);
        $start = substr($this->value, 0, $index);
        $end = substr($this->value, $index, $length - $index);

        return static::create($start.$string.$end);
    }

    /**
     * Creates a string that is wrapped with a given string
     */
    public function surround(string $string): static
    {
        return static::create($string.$this->value.$string);
    }

    /**
     * Creates a string centered and padded to a given length
     *
     * The pad is a single character string used to pad the string.
     *
     * @throws DomainException When the string length is invalid
     * @throws DomainException When the padding character is invalid
     */
    public function pad(int $length, ?string $char = null): static
    {
        $totalLength = $this->length;

        if ($length < 1) {
            $message = sprintf('Invalid length for padded string: %d', $length);
            throw new DomainException($message);
        }

        if ($char === null) {
            $char = ' ';
        }

        if (strlen($char) !== 1) {
            $message = sprintf('Invalid string padding character: %s', $char);
            throw new DomainException($message);
        }

        if ($length < $totalLength) {
            return static::create($this->value);
        }

        $padLength = (float) ($length - $totalLength);

        return static::create(self::padString(
            $this->value,
            (int) floor($padLength / 2),
            (int) ceil($padLength / 2),
            $char
        ));
    }

    /**
     * Creates a string padded on the left to a given length
     *
     * The pad is a single character string used to pad the string.
     *
     * @throws DomainException When the string length is invalid
     * @throws DomainException When the padding character is invalid
     */
    public function padLeft(int $length, ?string $char = null): static
    {
        $totalLength = $this->length;

        if ($length < 1) {
            $message = sprintf('Invalid length for padded string: %d', $length);
            throw new DomainException($message);
        }

        if ($char === null) {
            $char = ' ';
        }

        if (strlen($char) !== 1) {
            $message = sprintf('Invalid string padding character: %s', $char);
            throw new DomainException($message);
        }

        if ($length < $totalLength) {
            return static::create($this->value);
        }

        $padLength = $length - $totalLength;

        return static::create(self::padString(
            $this->value,
            $padLength,
            0,
            $char
        ));
    }

    /**
     * Creates a string padded on the right to a given length
     *
     * The pad is a single character string used to pad the string.
     *
     * @throws DomainException When the string length is invalid
     * @throws DomainException When the padding character is invalid
     */
    public function padRight(int $length, ?string $char = null): static
    {
        $totalLength = $this->length;

        if ($length < 1) {
            $message = sprintf('Invalid length for padded string: %d', $length);
            throw new DomainException($message);
        }

        if ($char === null) {
            $char = ' ';
        }

        if (strlen($char) !== 1) {
            $message = sprintf('Invalid string padding character: %s', $char);
            throw new DomainException($message);
        }

        if ($length < $totalLength) {
            return static::create($this->value);
        }

        $padLength = $length - $totalLength;

        return static::create(self::padString(
            $this->value,
            0,
            $padLength,
            $char
        ));
    }

    /**
     * Creates a string truncated to a given length
     *
     * If a substring is provided, it is appended to the end of the string.
     *
     * If truncating occurs, the string is further truncated and the substring
     * is appended without exceeding the desired length.
     *
     * @throws DomainException When the string length is invalid
     * @throws DomainException When the append string is invalid
     */
    public function truncate(int $length, string $append = ''): static
    {
        if ($length < 1) {
            $message = sprintf('Invalid length for truncated string: %d', $length);
            throw new DomainException($message);
        }

        $extra = strlen($append);

        if ($extra > $length - 1) {
            $message = sprintf('Append string length (%d) must be less than truncated length (%d)', $extra, $length);
            throw new DomainException($message);
        }

        $length -= $extra;

        if ($this->length <= $length) {
            return static::create($this->value.$append);
        }

        return static::create(substr($this->value, 0, $length).$append);
    }

    /**
     * Creates a string truncated to a given length without splitting words
     *
     * If a substring is provided, it is appended to the end of the string.
     *
     * If truncating occurs, the string is further truncated and the substring
     * is appended without exceeding the desired length.
     *
     * @throws DomainException When the string length is invalid
     * @throws DomainException When the append string is invalid
     */
    public function truncateWords(int $length, string $append = ''): static
    {
        if ($length < 1) {
            $message = sprintf('Invalid length for truncated string: %d', $length);
            throw new DomainException($message);
        }

        $extra = strlen($append);

        if ($extra > $length - 1) {
            $message = sprintf('Append string length (%d) must be less than truncated length (%d)', $extra, $length);
            throw new DomainException($message);
        }

        $length -= $extra;

        if ($this->length <= $length) {
            return static::create($this->value.$append);
        }

        $truncated = substr($this->value, 0, $length);
        $last = strpos($this->value, ' ', $length - 1);

        if ($last !== $length) {
            $last = strrpos($truncated, ' ', 0);
            if ($last === false) {
                return static::create($truncated.$append);
            }
            $truncated = substr($truncated, 0, $last);
        }

        return static::create($truncated.$append);
    }

    /**
     * Creates a string that repeats the original string
     *
     * @throws DomainException When the multiplier is invalid
     */
    public function repeat(int $multiplier): static
    {
        if ($multiplier < 1) {
            $message = sprintf('Invalid multiplier: %d', $multiplier);
            throw new DomainException($message);
        }

        return static::create(str_repeat($this->value, $multiplier));
    }

    /**
     * Creates a substring between two indexes
     *
     * @throws DomainException When the start index is invalid
     * @throws DomainException When the stop index is invalid
     */
    public function slice(int $start, ?int $stop = null): static
    {
        if ($stop === null) {
            $stop = 0;
        }

        $start = $this->prepareOffset($start, $this->length);
        $length = $this->prepareLengthFromStop($stop, $start, $this->length);

        return static::create(substr($this->value, $start, $length));
    }

    /**
     * Creates a substring starting at an index
     *
     * @throws DomainException When the start index is invalid
     * @throws DomainException When the string length is invalid
     */
    public function substr(int $start, ?int $length = null): static
    {
        if ($length === null) {
            $length = 0;
        }

        $start = $this->prepareOffset($start, $this->length);
        $length = $this->prepareLength($length, $start, $this->length);

        return static::create(substr($this->value, $start, $length));
    }

    /**
     * Creates a list of strings split by a delimiter
     *
     * @throws DomainException When the delimiter is empty
     */
    public function split(string $delimiter = ' ', ?int $limit = null): ArrayList
    {
        if (empty($delimiter)) {
            throw new DomainException('Delimiter cannot be empty');
        }

        if ($limit === null) {
            $parts = explode($delimiter, $this->value);
        } else {
            $parts = explode($delimiter, $this->value, $limit);
        }

        $list = ArrayList::of(self::class);

        foreach ($parts as $part) {
            $list->add(static::create($part));
        }

        return $list;
    }

    /**
     * Creates a list of string chunks
     *
     * Each string in the list is represented by a static instance.
     *
     * @throws DomainException When the chunk size is invalid
     */
    public function chunk(int $size = 1): ArrayList
    {
        if ($size < 1) {
            $message = sprintf('Invalid chunk size: %d', $size);
            throw new DomainException($message);
        }

        $parts = str_split($this->value, $size);
        $list = ArrayList::of(self::class);

        foreach ($parts as $part) {
            $list->add(static::create($part));
        }

        return $list;
    }

    /**
     * Creates a string replacing all occurrences of search with replacement
     *
     * If search and replacement are arrays, then a value is used from each
     * array to search and replace on subject.
     *
     * If replacement has fewer values than search, then an empty string is
     * used for the rest of replacement values.
     *
     * If search is an array and replacement is a string, then the replacement
     * string is used for every value of search.
     */
    public function replace($search, $replace): static
    {
        return static::create(str_replace($search, $replace, $this->value));
    }

    /**
     * Creates a string with both ends trimmed
     */
    public function trim(?string $mask = null): static
    {
        if ($mask === null) {
            return static::create(trim($this->value));
        }

        return static::create(trim($this->value, $mask));
    }

    /**
     * Creates a string with the left end trimmed
     */
    public function trimLeft(?string $mask = null): static
    {
        if ($mask === null) {
            return static::create(ltrim($this->value));
        }

        return static::create(ltrim($this->value, $mask));
    }

    /**
     * Creates a string with the right end trimmed
     */
    public function trimRight(?string $mask = null): static
    {
        if ($mask === null) {
            return static::create(rtrim($this->value));
        }

        return static::create(rtrim($this->value, $mask));
    }

    /**
     * Creates a string with tabs replaced by spaces
     *
     * @throws DomainException When the tab size is invalid
     */
    public function expandTabs(int $tabSize = 4): static
    {
        if ($tabSize < 0) {
            $message = sprintf('Invalid tab size: %d', $tabSize);
            throw new DomainException($message);
        }

        $spaces = str_repeat(' ', $tabSize);

        return static::create(str_replace("\t", $spaces, $this->value));
    }

    /**
     * Creates a lower-case string
     */
    public function toLowerCase(): static
    {
        return static::create(strtolower($this->value));
    }

    /**
     * Creates an upper-case string
     */
    public function toUpperCase(): static
    {
        return static::create(strtoupper($this->value));
    }

    /**
     * Creates a string with the first character lower-case
     */
    public function toFirstLowerCase(): static
    {
        return static::create(lcfirst($this->value));
    }

    /**
     * Creates a string with the first character upper-case
     */
    public function toFirstUpperCase(): static
    {
        return static::create(ucfirst($this->value));
    }

    /**
     * Creates a camel-case string
     *
     * Trims surrounding spaces and capitalizes letters following digits,
     * spaces, dashes and underscores.
     *
     * The first letter is lowercase and spaces, dashes, and underscores are
     * removed.
     */
    public function toCamelCase(): static
    {
        $value = trim($this->value);
        $length = strlen($value);

        if ($length === 0) {
            return static::create('');
        }

        return static::create(lcfirst(self::capsCase($value)));
    }

    /**
     * Creates a pascal-case string
     *
     * Trims surrounding spaces and capitalizes letters following digits,
     * spaces, dashes and underscores.
     *
     * The first letter is capitalized and spaces, dashes, and underscores are
     * removed.
     */
    public function toPascalCase(): static
    {
        $value = trim($this->value);
        $length = strlen($value);

        if ($length === 0) {
            return static::create('');
        }

        return static::create(self::capsCase($value));
    }

    /**
     * Creates a snake-case string
     *
     * Semantic alias for toLowerUnderscored.
     *
     * Trims surrounding spaces and adds an underscore before uppercase
     * characters (except the first character).
     *
     * Underscores are added in place of spaces and hyphens, and the string is
     * converted to lowercase.
     */
    public function toSnakeCase(): static
    {
        $value = trim($this->value);
        $length = strlen($value);

        if ($length === 0) {
            return static::create('');
        }

        return static::create(strtolower(self::delimitString($value, '_')));
    }

    /**
     * Creates a hyphenated lowercase string
     *
     * Trims surrounding spaces and adds a hyphen before uppercase characters
     * (except the first character).
     *
     * Hyphens are added in place of spaces and underscores, and the string is
     * converted to lowercase.
     */
    public function toLowerHyphenated(): static
    {
        $value = trim($this->value);
        $length = strlen($value);

        if ($length === 0) {
            return static::create('');
        }

        return static::create(strtolower(self::delimitString($value, '-')));
    }

    /**
     * Creates a hyphenated uppercase string
     *
     * Trims surrounding spaces and adds a hyphen before uppercase characters
     * (except the first character).
     *
     * Hyphens are added in place of spaces and underscores, and the string is
     * converted to uppercase.
     */
    public function toUpperHyphenated(): static
    {
        $value = trim($this->value);
        $length = strlen($value);

        if ($length === 0) {
            return static::create('');
        }

        return static::create(strtoupper(self::delimitString($value, '-')));
    }

    /**
     * Creates an underscored lowercase string
     *
     * Trims surrounding spaces and adds an underscore before uppercase
     * characters (except the first character).
     *
     * Underscores are added in place of spaces and hyphens, and the string is
     * converted to lowercase.
     */
    public function toLowerUnderscored(): static
    {
        $value = trim($this->value);
        $length = strlen($value);

        if ($length === 0) {
            return static::create('');
        }

        return static::create(strtolower(self::delimitString($value, '_')));
    }

    /**
     * Creates an underscored uppercase string
     *
     * Trims surrounding spaces and adds an underscore before uppercase
     * characters (except the first character).
     *
     * Underscores are added in place of spaces and hyphens, and the string is
     * converted to uppercase.
     */
    public function toUpperUnderscored(): static
    {
        $value = trim($this->value);
        $length = strlen($value);

        if ($length === 0) {
            return static::create('');
        }

        return static::create(strtoupper(self::delimitString($value, '_')));
    }

    /**
     * Creates a string that is suitable for a URL segment
     *
     * Attempts to convert the string to ASCII characters and replaces non-word
     * characters with hyphens.
     *
     * Duplicate hyphens are removed and the string is converted to lowercase.
     */
    public function toSlug(): static
    {
        $slug = trim($this->value);
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $slug);
        $slug = strtolower($slug);
        $slug = preg_replace('/\W/', '-', $slug);
        $slug = preg_replace('/-+/', '-', (string) $slug);
        $slug = trim((string) $slug, '-');

        return static::create($slug);
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * @inheritDoc
     */
    public function compareTo(mixed $object): int
    {
        if ($this === $object) {
            return 0;
        }

        Assert::areSameType($this, $object);

        $strComp = strnatcmp($this->value, (string) $object->value);

        return $strComp <=> 0;
    }

    /**
     * Retrieves an iterator for characters
     */
    public function getIterator(): Traversable
    {
        return $this->chars();
    }

    /**
     * Applies padding to a string
     */
    private static function padString(string $string, int $left, int $right, string $char): string
    {
        $leftPadding = str_repeat($char, $left);
        $rightPadding = str_repeat($char, $right);

        return $leftPadding.$string.$rightPadding;
    }

    /**
     * Applies caps formatting to a string
     */
    private static function capsCase(string $string): string
    {
        $output = [];

        if (
            preg_match('/\A[a-z0-9]+\z/i', $string)
            && strtoupper($string) !== $string
        ) {
            $parts = self::explodeOnCaps($string);
        } else {
            $parts = self::explodeOnDelimiters($string);
        }

        foreach ($parts as $part) {
            $output[] = ucfirst(strtolower((string) $part));
        }

        return implode('', $output);
    }

    /**
     * Applies delimiter formatting to a string
     */
    private static function delimitString(string $string, string $delimiter): string
    {
        $output = [];

        if (
            preg_match('/\A[a-z0-9]+\z/ui', $string)
            && strtoupper($string) !== $string
        ) {
            $parts = self::explodeOnCaps($string);
        } else {
            $parts = self::explodeOnDelimiters($string);
        }

        foreach ($parts as $part) {
            $output[] = $part.$delimiter;
        }

        return rtrim(implode('', $output), $delimiter);
    }

    /**
     * Splits a string into a list on capital letters
     */
    private static function explodeOnCaps(string $string): array
    {
        $string = preg_replace('/\B([A-Z])/', '_\1', $string);
        $string = preg_replace('/([0-9]+)/', '_\1', (string) $string);
        $string = preg_replace('/_+/', '_', (string) $string);
        $string = trim((string) $string, '_');

        return explode('_', $string);
    }

    /**
     * Splits a string into a list on non-word breaks
     */
    private static function explodeOnDelimiters(string $string): array
    {
        $string = preg_replace('/[^a-z0-9]+/i', '_', $string);
        $string = trim((string) $string, '_');

        return explode('_', $string);
    }
}
