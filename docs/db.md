### DB, Database

Основной класс работающий с mysql – Database.

Для удобства, есть обертка DB, позволяющая удобно делать прямые запросы:

```
$result = DB::select('SELECT * FROM users WHERE id = :id', [':id' => 1]);

$result = DB::update('UPDATE users SET name = :name WHERE id = :id', [':name' => 'John', ':id' => 1]);
```

select() вернет объект типа /mii/db/Result, insert() вернет массив (последний id, количество затронутых строк), 
остальные виды запросов — количество затронутых строк.

Для еще более удобной работы, традиционный query builder:

```
(new Query)->select()->from('users')->where('username', '=', 'john')->get();

(new Query)->delete('users')->where('username', '=', 'john')->execute();

(new Query)->update('users')->set(['username', '=', 'john'])->execute();

(new Query)->insert('users')->values(['john', 'dow'])->execute();

(new Query)->insert('users', ['name' => 'john', 'surname' => 'dow')->execute();

```

Для получения результатов возможны варианты:

get() – просто выполняет запрос и возвращает /mii/db/Result
one() – добавляет в запрос limit 1 и возвращает первую строку ответа или null
all() – В случае orm аналогичен get(). В остальных случаях (т.е. если получаем массив), то возвращает полный массив результатов,
 используя mysqli метод fetch_all, что в некоторых случаях повышает производительность.

Еще один специальный случай — count запрос:
```
$count = (new Query)->from('users')->where('name', 'like', '%oh')->count();
```
будет аналогично:

```
$result = (new Query)->select(DB::expr('COUNT(*)'))->from('users')->where('name', 'like', '%oh')->get();
$count = count($result);
```

Кстати, count возвращает исходное значение select в оригинальный запрос, так что его можно безболезненно использовать вместе 
со сложным запросом:

```
$query = (new Query)
            ->select(['id', 'name', 'surname'])
            ->where('some', '=', 1)
            ->and_where('else', '=', 2);
            
$count = $query->count();

$result = $query->limit($count/2)->get();

```
 
 
Важно понимать, что почти всегда (кроме последнего случая с all и count) запрос возвращает объект типа Result. С ним можно работать,
как с массивом (т.к. ArrayAccess, Iterator и т.п.), но можно использовать и специфические методы.

Так, column($name, $default = null) — вернет значение конкретного поля первой записи результата.


### ORM
 
Наш ORM – один из самых простых (и тупых) в мире ORM, реализующих шаблон ActiveRecord. Работает быстро, памяти потребляет мало.

Первоначально фреймворк использовал ORM Jelly, позже AutoModeler. Но, как показала практика, в реальных (наших) проектах в 99% случаев 
использовался паразительный минимум возможностей этих систем (1%). Т.к. большинство наших проектов — это простые сайты (пусть и большие по
масштабам), логика работа с базой данных в большинстве случаев очень простая: выбрать запись таблицы A, выбрать N записей из 
таблицы B по ключу из A...  и всё. Усложнять систему ради упрощения кода в 1% случаев выглядело не лучшим решением, так что
был написан очень примитивный ORM.

Пример модели:

```
class User extends ORM {

    public $id = 0;
    public $name = '';
    public $surname = '';
}
```

Создание записи:
```
$user = new User();
$user->name = 'test';
$user->create();
```

Обновление по аналогии:
```
$user->update();
```

Доступ к query builder'у:

```
$users = User::find()->where('id', '=', 1)->all();
```

выбор записи по PK:

```
User::find(1);
```




