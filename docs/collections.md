# Collections

Typed collections organized by data structure family. All collections implement `Countable` + `IteratorAggregate` and enforce item types at runtime via `assert(Validate::isType(...))`.

Concrete classes split into two families:

- **Item collections** — store items (values), accessed by position, hash, or sorted order
- **Key-value collections** — store key-value pairs, accessed by hash or sorted key

---

## Table of Contents

1. [Helpers](#helpers)
2. [Contract Architecture](#contract-architecture)
3. [ArrayList](#arraylist)
4. [HashSet](#hashset)
5. [HashTable](#hashtable)
6. [SortedSet](#sortedset)
7. [SortedTable](#sortedtable)
8. [Stacks](#stacks)
9. [Queues](#queues)
10. [LinkedDeque](#linkeddeque)
11. [Comparators](#comparators)
12. [Internal Data Structures](#internal-data-structures)

---

## Helpers

Helper functions in `Fight\Common\Domain` provide concise construction for the most common types. Import with `use function Fight\Common\Domain\{fn};`:

| Helper | Creates | Notes |
|---|---|---|
| `array_list($items, ?string $type)` | `ArrayList` | `$type` defaults to dynamic |
| `hash_set($items, ?string $type)` | `HashSet` | Iterates + adds each item |
| `hash_table($entries, ?string $keyType, ?string $valueType)` | `HashTable` | Preserves key-value associations |
| `array_stack($items, ?string $type)` | `ArrayStack` | Items pushed in order |
| `array_queue($items, ?string $type)` | `ArrayQueue` | Items enqueued in order |

No helpers for `SortedSet` / `SortedTable` (require a `Comparator`) or `Linked*` variants.

---

## Contract Architecture

```
Collection  (Countable + IteratorAggregate + isEmpty)
├── ItemCollection             typed items, functional methods
│   ├── ItemList               ordered index (add, get, set, sort, slice, page)
│   ├── Set                    uniqueness (add, contains, difference, intersection, union)
│   ├── Stack                  LIFO (push, pop, top)
│   ├── Queue                  FIFO (enqueue, dequeue, front)
│   ├── Deque                  double-ended (addFirst/Last, removeFirst/Last)
│   └── OrderedItemCollection  sorted by Comparator
│       └── OrderedSet         sorted unique (range, rank, select, floor, ceiling)
└── KeyValueCollection         typed keys + values, functional methods
    ├── Table                  hash map (set, get, has, remove, keys)
    └── OrderedKeyValueCollection  sorted keys by Comparator
        └── OrderedTable       sorted map (rangeKeys, rank, select, floor, ceiling)
```

Two shared traits provide type enforcement:

- `ItemTypeMethods` — `itemType(): ?string`, used by all item collections
- `KeyValueTypeMethods` — `keyType(): ?string`, `valueType(): ?string`, used by `HashTable` and `SortedTable`

---

## ArrayList

`Fight\Common\Domain\Collection\ArrayList` implements `ItemList`

Backed by a PHP array. Sequential index access with negative index support. Full functional API: `map`, `filter`, `reject`, `reduce`, `sort`, `reverse`, `unique`, `slice`, `page`.

**Key features:**
- Negative indices for `set()` and `get()` (wraps from the end)
- `page(page, perPage)` returns a slice for pagination
- `indexOf()` / `lastIndexOf()` accepts values or `Closure` predicates
- Internal pointer methods: `rewind`, `end`, `next`, `prev`, `key`, `current`

```php
use Fight\Common\Domain\Collection\ArrayList;
use function Fight\Common\Domain\array_list;

$list = ArrayList::of('string');
$list->add('a');
$list->set(-1, 'b');                         // replaces last element
$head = $list->head();                       // first element
$tail = $list->tail();                       // all but first
$page = $list->page(2, 10);                  // second page of 10

// Helper
$list = array_list(['a', 'b', 'c'], 'string');
```

---

## HashSet

`Fight\Common\Domain\Collection\HashSet` implements `Set`

Backed by hash buckets (`FastHasher` + `SetBucketChain`). Items must have a consistent `FastHasher::hash()` representation. Set operations return new sets.

**Key features:**
- `difference(Set)` — symmetric difference (A ∆ B)
- `intersection(Set)` — items in both sets (A ∩ B)
- `complement(Set)` — items in B but not A (B \ A)
- `union(Set)` — all items from both sets (A ∪ B)

```php
use Fight\Common\Domain\Collection\HashSet;
use function Fight\Common\Domain\hash_set;

$set = HashSet::of('int');
$set->add(1);
$set->add(2);
$intersection = $set->intersection($other);

// Helper
$set = hash_set([1, 2, 3], 'int');
```

---

## HashTable

`Fight\Common\Domain\Collection\HashTable` implements `Table`

Backed by hash buckets (`FastHasher` + `TableBucketChain`). Key-value mapping with `ArrayAccess`. Keys are hashed via `FastHasher`; value semantics apply (two equal keys produce the same hash).

**Key features:**
- `keys()` — lazy iterator over all keys
- `find(predicate)` — returns the first key whose value passes the test
- `map(callback)` — transforms values, preserves keys
- `toArray()` not available (see `keys()` + iteration instead)

```php
use Fight\Common\Domain\Collection\HashTable;
use function Fight\Common\Domain\hash_table;

$table = HashTable::of('string', 'int');
$table->set('a', 1);
$value = $table->get('a');                   // 1
foreach ($table->keys() as $key) { /* ... */ }

// Helper
$table = hash_table(['a' => 1, 'b' => 2]);
```

---

## SortedSet

`Fight\Common\Domain\Collection\SortedSet` implements `OrderedSet`

Backed by a `RedBlackSearchTree` (left-leaning red-black BST). Items are stored in sorted order using a `Comparator`. Named constructors cover common cases.

**Named constructors:**

| Method | Key type | Comparator |
|---|---|---|
| `SortedSet::integer()` | `int` | `IntegerComparator` |
| `SortedSet::float()` | `float` | `FloatComparator` |
| `SortedSet::string()` | `string` | `StringComparator` |
| `SortedSet::comparable(MyClass::class)` | `MyClass` | `ComparableComparator` |
| `SortedSet::callback(fn($a,$b)=>...)` | dynamic | `FunctionComparator` |
| `SortedSet::create($comparator, $type)` | given | custom |

**Key features:**
- `floor(item)` — largest item ≤ the given item
- `ceiling(item)` — smallest item ≥ the given item
- `rank(item)` — number of items less than the given item
- `select(rank)` — item at the given rank (0-indexed)
- `range(lo, hi)` — all items in the inclusive range
- `rangeCount(lo, hi)` — count of items in the inclusive range
- `removeMin()` / `removeMax()` — remove by order
- Set operations: `difference`, `intersection`, `complement`, `union`

```php
use Fight\Common\Domain\Collection\SortedSet;
use Fight\Common\Domain\Collection\Comparison\IntegerComparator;

$set = SortedSet::integer();
$set->add(5);
$set->add(2);
$set->add(8);

$set->min();                                 // 2
$set->max();                                 // 8
$set->floor(4);                              // 2
$set->ceiling(4);                            // 5
$set->rank(5);                               // 2 (items < 5)
$set->select(0);                             // 2 (rank 0 = min)
$set->range(3, 7);                           // yields 5

// Custom comparator
$set = SortedSet::create(new IntegerComparator(), 'int');
```

No helper function — `SortedSet` requires a `Comparator`.

---

## SortedTable

`Fight\Common\Domain\Collection\SortedTable` implements `OrderedTable`

Same backing (`RedBlackSearchTree`) and same named constructors as `SortedSet`, but stores key-value pairs. Keyed operations parallel `SortedSet`:

```php
use Fight\Common\Domain\Collection\SortedTable;

$table = SortedTable::string('int');
$table->set('b', 2);
$table->set('a', 1);
$table->set('c', 3);

$table->keys();                              // yields 'a', 'b', 'c'
$table->min();                               // 'a'
$table->max();                               // 'c'
$table->floor('bb');                         // 'b'
$table->ceiling('bb');                       // 'c'
$table->rank('b');                           // 1
$table->rangeKeys('a', 'b');                 // yields 'a', 'b'
```

No helper function — `SortedTable` requires a `Comparator`.

---

## Stacks

Two implementations of the `Stack` interface (LIFO: `push`, `pop`, `top`).

### LinkedStack

`Fight\Common\Domain\Collection\LinkedStack`

Backed by `SplDoublyLinkedList` in LIFO iteration mode. Iteration order = top-to-bottom.

```php
use Fight\Common\Domain\Collection\LinkedStack;

$stack = LinkedStack::of('string');
$stack->push('a');
$stack->top();                               // 'a'
$stack->pop();                               // 'a'
```

### ArrayStack

`Fight\Common\Domain\Collection\ArrayStack`

Backed by a plain PHP array. No SPL dependency. Iteration order = bottom-to-top.

```php
use Fight\Common\Domain\Collection\ArrayStack;
use function Fight\Common\Domain\array_stack;

$stack = ArrayStack::of('string');
$stack->push('a');
$stack->push('b');
$stack->pop();                               // 'b'

// Helper
$stack = array_stack(['a', 'b', 'c'], 'string');
```

---

## Queues

Two implementations of the `Queue` interface (FIFO: `enqueue`, `dequeue`, `front`).

### LinkedQueue

`Fight\Common\Domain\Collection\LinkedQueue`

Backed by `SplDoublyLinkedList` in FIFO iteration mode. Iteration order = front-to-back.

```php
use Fight\Common\Domain\Collection\LinkedQueue;

$queue = LinkedQueue::of('int');
$queue->enqueue(1);
$queue->enqueue(2);
$queue->front();                             // 1
$queue->dequeue();                           // 1
```

### ArrayQueue

`Fight\Common\Domain\Collection\ArrayQueue`

Backed by a PHP array with a circular buffer for amortized O(1) `enqueue`/`dequeue`. Automatically grows and shrinks capacity.

```php
use Fight\Common\Domain\Collection\ArrayQueue;
use function Fight\Common\Domain\array_queue;

$queue = ArrayQueue::of('int');
$queue->enqueue(1);
$queue->enqueue(2);
$queue->dequeue();                           // 1

// Helper
$queue = array_queue([1, 2, 3], 'int');
```

---

## LinkedDeque

`Fight\Common\Domain\Collection\LinkedDeque` implements `Deque`

Double-ended queue backed by `SplDoublyLinkedList`. Add and remove from either end.

```php
use Fight\Common\Domain\Collection\LinkedDeque;

$deque = LinkedDeque::of('string');
$deque->addFirst('a');
$deque->addLast('b');
$deque->removeFirst();                       // 'a'
$deque->removeLast();                        // 'b'
$deque->first();                             // peek front
$deque->last();                              // peek back
```

No helper function.

---

## Comparators

`Fight\Common\Domain\Type\Comparator` — a single-method interface:

```php
public function compare(mixed $a, mixed $b): int
```

Returns `-1`, `0`, or `1`. Used by `SortedSet` and `SortedTable`.

| Comparator | Compares | Use via |
|---|---|---|
| `IntegerComparator` | native ints | `SortedSet::integer()` |
| `FloatComparator` | native floats | `SortedSet::float()` |
| `StringComparator` | native strings | `SortedSet::string()` |
| `ComparableComparator` | `Comparable` objects | `SortedSet::comparable(MyClass::class)` |
| `FunctionComparator` | any (wraps a callable) | `SortedSet::callback(fn($a, $b) => ...)` |

```php
use Fight\Common\Domain\Collection\Comparison\IntegerComparator;

$comparator = new IntegerComparator();
$comparator->compare(5, 3);                  // 1
$comparator->compare(3, 5);                  // -1
$comparator->compare(4, 4);                  // 0
```

---

## Internal Data Structures

### RedBlackSearchTree

`Fight\Common\Domain\Collection\Tree\RedBlackSearchTree`

A left-leaning red-black BST (LLRB). Provides O(log n) operations for insert, delete, search, rank, select, and range queries. Powers both `SortedSet` and `SortedTable`.

Implements `BinarySearchTree` interface with: `set`, `get`, `has`, `remove`, `min`, `max`, `floor`, `ceiling`, `rank`, `select`, `keys`, `rangeKeys`, `rangeCount`.

### GeneratorIterator

`Fight\Common\Domain\Collection\Iterator\GeneratorIterator`

Wraps a generator function into a rewindable `Iterator`. On `rewind()` it re-invokes the generator function (PHP generators cannot be rewound directly). Used by `HashSet`, `HashTable`, `SortedSet`, and `SortedTable` for lazy iteration without materializing the full collection.

### Bucket Chains

Hash collision resolution for `HashSet` and `HashTable`:

- `SetBucketChain` — linked chain of `ItemBucket` nodes (stores items)
- `TableBucketChain` — linked chain of `KeyValueBucket` nodes (stores key-value pairs)

Both extend the abstract `Bucket` class and handle insertion, lookup, removal, and iteration within a single hash bucket.
