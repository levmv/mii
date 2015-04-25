# DB, Database

Основной класс работающий с mysql – Database.

Так код простого запроса к базе данных будет выглядеть примерно так:
```php
Database::instance()->query(Database::SELECT, 'SELECT * FROM users WHERE id = ' . $id);
```

Но так делать не надо. Есть обертка DB, позволяющая удобно делать прямые запросы:

```php
$result = DB::select('SELECT * FROM users WHERE id = :id', [':id' => 1]);

$result = DB::update('UPDATE users SET name = :name WHERE id = :id', [':name' => 'John', ':id' => 1]);
```

Любой SELECT запрос всегда возвращает объект типа /mii/db/Result, INSERT вернет массив [последний id, количество затронутых строк], 
остальные виды запросов — количество затронутых строк. Подробнее про Result будет чуть позже. 

Для еще более удобной работы, есть традиционный query builder:

```php
(new Query)->select()->from('users')->where('username', '=', 'john')->get();

(new Query)->select(['name', 'surname'])->from('users')->where('id', '=', '1')->one();

(new Query)->delete('users')->where('username', '=', 'john')->execute();

(new Query)->update('users')->set(['username', '=', 'john'])->execute();

(new Query)->insert('users')->values(['john', 'doe'])->execute();

(new Query)->insert('users', ['name' => 'john', 'surname' => 'doe')->execute();

```

Основной способ выполнения запроса для Query Builder это метод get. Он просто выполняет запрос и возвращает /mii/db/Result

Но есть ряд специальных способов выполнения:

one() – добавляет в запрос limit 1 и возвращает первую строку ответа или null

all() – аналогичен вызову метода Result::all(). Т.е. заменяет в данном случае цепочку ->get()->all();

count() — добавляет в select конструкцию COUNT(), выполняет запрос, возвращает количество результатов (int) 

```
$count = (new Query)->from('users')->where('name', 'like', '%oh')->count();
```
будет аналогично:

```
$result = (new Query)->select(DB::expr('COUNT(*)'))->from('users')->where('name', 'like', '%oh')->get();
$count = count($result);
```

*(Кстати, count возвращает исходное значение select в оригинальный запрос, так что его можно безболезненно использовать вместе 
со сложным запросом.)*


### Result

Результатом выполнения любого запроса к базе (кроме, очевидно, one|all|count) будет (внезапно) экземпляр класса /mii/db/Result.

По сути это достаточно тонкая обертка вокруг mysqli_result, реализующая интерфейсы Countable, Iterator, SeekableIterator, ArrayAccess
и еще несколько удобностей. Проще говоря, с Result можно работать как с массивом (разве что нельзя записывать в него значения 
и не получится вывести его содержимое с помощью print_r).

Удобности:

`all()` – если мы получаем объекты, то не делает ничего (вернет $this). Если массив, то возвращает полный массив результатов,
используя mysqli метод fetch_all, что в некоторых случаях повышает производительность. Стоит использовать этот метод в тех случаях,
когда вам нужен полный массив результатов для его последующей сложной обработки. Если же единственное предполагаемое использование массива это 
простое прохождение его в цикле foreach (к примеру, для вывода), то смысла в этом методе нет.

`column($name, $default = null)` — вернет значение конкретного поля текущей строки результата.

`as_array()` — возвращает результаты в виде массива:

```php
// Простой массив всех строк результата
$rows = $result->as_array();

// Ассоциативный массив из всех строк по ключу id
$rows = $result->as_array('id');

// Ассоциативный массив "id" => "name"
$rows = $result->as_array('id', 'name');

```




