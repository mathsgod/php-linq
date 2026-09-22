# php-linq

An experimental LINQ-style query library for PHP 8.2+. Unlike callback-only
collection wrappers, a query is represented by an expression tree and executed
by a query provider.

## LINQ to Objects

`Enumerable` provides lazy, generator-based processing with ordinary PHP
callbacks:

```php
use PhpLinq\Enumerable;

$names = Enumerable::from($users)
    ->where(fn (User $user): bool => $user->active)
    ->orderBy(fn (User $user): int => $user->age)
    ->select(fn (User $user): string => $user->name)
    ->take(10)
    ->toArray();
```

Streaming operators include `where`, `select`, `selectMany`, `skip`, `take`,
`skipWhile`, `takeWhile`, `distinct`, `append`, `prepend`, `concat`, and
`chunk`. Operators which require a complete view of the sequence, such as
`orderBy`, `reverse`, and `takeLast`, buffer their input when enumerated.

Call `asEnumerable()` on an `IQueryable` to execute the provider-backed part
first and continue with callback-based, in-memory operators.

## Generic collections

Mutable collections modelled after `System.Collections.Generic` are available
under `PhpLinq\\Collections\\Generic`:

```php
use PhpLinq\Collections\Generic\GenericList;
use PhpLinq\Collections\Generic\Queue;
use PhpLinq\Collections\Generic\Stack;

$list = new GenericList([1, 2]);
$list->add(3);
$list->insert(0, 0);

$stack = new Stack();
$stack->push('job');
$job = $stack->pop();

$queue = new Queue();
$queue->enqueue('job');
$job = $queue->dequeue();
```

PHP reserves the keyword `list`, so the .NET `List<T>` equivalent is named
`GenericList<T>`. All three collections implement `IteratorAggregate` and can
be passed directly to `Enumerable::from()`.

The generic collection contracts are organised as follows:

```text
IReadOnlyCollection<T>
├── ICollection<T>
│   ├── IList<T> → GenericList<T>
│   └── ISet<T>  → HashSet<T>
├── Stack<T>
└── Queue<T>

IReadOnlyCollection<KeyValuePair<TKey, TValue>>
└── IDictionary<TKey, TValue> → Dictionary<TKey, TValue>
```

`HashSet<T>` supports custom `EqualityComparer<T>` implementations and mutable
set operations including union, intersection, difference, symmetric
difference, subset/superset checks, overlap checks, and set equality.

```php
use PhpLinq\Expr;
use PhpLinq\InMemoryQueryProvider;
use PhpLinq\Queryable;

$provider = new InMemoryQueryProvider([
    'users' => [
        ['name' => 'Ada', 'active' => true, 'age' => 36],
        ['name' => 'Bob', 'active' => false, 'age' => 22],
    ],
]);

$names = Queryable::from($provider, 'users')
    ->where(Expr::eq(Expr::field('active'), true))
    ->orderBy(Expr::field('name'))
    ->select(Expr::field('name'))
    ->toArray();
```

The expression tree is provider-independent. `InMemoryQueryProvider` executes
it against arrays or other iterables; a future SQL provider can translate the
same nodes into parameterized SQL.

Supported query operators: `where`, `select`, `orderBy`, `orderByDescending`,
`skip`, `take`, `count`, `any`, and `first`.

## Generic dictionary

`Collections\\Generic\\Dictionary<TKey, TValue>` is a hash-table collection
with object-key support and pluggable equality semantics:

```php
use PhpLinq\Collections\Generic\Dictionary;

/** @var Dictionary<string, int> $scores */
$scores = new Dictionary();
$scores->add('Ada', 10);       // throws when the key already exists
$scores['Ada'] = 15;           // indexer-style insert or replace
$scores->tryGetValue('Ada', $score);
```

It provides `add`, `set`, `get`, `tryAdd`, `tryGetValue`, `containsKey`,
`containsValue`, `remove`, `clear`, `keys`, and `values`. Iteration returns
`KeyValuePair<TKey, TValue>` objects so keys are not restricted to PHP's
integer and string iterator-key types.

## SQL providers

The same expression tree can be compiled and executed through PDO:

```php
use PhpLinq\Queryable;
use PhpLinq\Sql\MySqlDialect;
use PhpLinq\SqlQueryProvider;

$users = Queryable::from(
    new SqlQueryProvider($pdo, new MySqlDialect()),
    'users',
);
```

The included dialects are `MySqlDialect`, `SqlServerDialect`,
`PostgreSqlDialect`, and `SqliteDialect`. They generate their native identifier
quoting and pagination syntax, including SQL Server `TOP` / `OFFSET ... FETCH`
and MySQL, PostgreSQL, and SQLite `LIMIT` variants. Query values are always
emitted as bound parameters.

Install the development dependencies and run the PHPUnit test suite with:

```sh
composer install
composer test
```
