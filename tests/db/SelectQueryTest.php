<?php declare(strict_types=1);

namespace miit\db;

use mii\db\Expression;
use mii\db\Query;
use mii\db\SelectQuery;

class SelectQueryTest extends DatabaseTestCase
{
    public function testCompleteSelect()
    {
        $this->assertEquals(
            'SELECT DISTINCT `table`.`col1`, `table`.`col2` AS `al1`, COUNT(id), `table`.`col3`, `table`.`col4` ' .
            'FROM `table` LEFT JOIN `jt` ON (`jt`.`id` = `t2`.`id`) ' .
            "WHERE `col1` = 1 AND (`col2` < 'sadf' OR `col2` IS NULL) " .
            'HAVING `count` > 0 ORDER BY `id` desc, `col1` asc, `col2` asc LIMIT 5 OFFSET 10 FOR UPDATE',
            (new SelectQuery())
                ->forUpdate()
                ->distinct(true)
                ->select('col1', ['col2', 'al1'], new Expression('COUNT(id)'))
                ->selectAlso('col3', 'col4')
                ->join('jt', 'LEFT')
                ->on('jt.id', '=', 't2.id')
                ->from('table')
                ->from(['table2', 't2]'])
                ->where('col1', '=', 1)
                ->whereGroup(function(SelectQuery $q) {
                    $q->where('col2', '<', 'sadf')
                        ->orWhere('col2', '=', null);
                })
                ->having('count', '>', 0)
                ->orderBy([
                    ['id', 'desc'],
                    ['col1', 'asc'],
                ])
                ->orderBy('col2', 'asc')
                ->limit(5)
                ->offset(10)
                ->compile()
        );
    }


    public function testSelectAny()
    {
        $this->assertEquals(
            'SELECT `t`.* FROM `t`',
            (new SelectQuery())
                ->from('t')
                ->compile()
        );

        $this->assertEquals(
            'SELECT `t`.*, COUNT(t.id) AS cc FROM `t`',
            (new SelectQuery())
                ->selectAlso(new Expression('COUNT(t.id) AS cc'))
                ->from('t')
                ->compile()
        );

        $this->assertEquals(
            'SELECT `t`.`a`, COUNT(t.id) AS cc FROM `t`',
            (new SelectQuery())
                ->select('a')
                ->selectAlso(new Expression('COUNT(t.id) AS cc'))
                ->from('t')
                ->compile()
        );
    }


    public function testWhere()
    {
        $q = static fn () => (new SelectQuery())->select()->from('t');


        $eq = 'SELECT `t`.* FROM `t` WHERE ';

        $tests = [

            ['`col` = 1',
                $q()->where('col', '=', 1), ],

            ["`col` = 'str'",
                $q()->where('col', '=', 'str'), ],

            ["`c` = 'str' AND `c` IS NULL AND `c` != 'str' AND `c` IN (1,2,3) AND `c` NOT IN ('a','b',3) AND `c` LIKE 'str'",
                $q()->whereAll([
                    ['c', '=', 'str'],
                    ['c', '=', null],
                    ['c', '!=', 'str'],
                    ['c', 'IN', [1, 2, 3]],
                    ['c', 'NOT IN', ['a', 'b', 3]],
                    ['c', 'LIKE', 'str'],
                ]), ],
            ['`a` = 1 OR `b` = 1 AND `c` = 1 OR (`x` = 3 AND (`d` = 1 AND `e` = 1))',
                $q()
                    ->where('a', '=', 1)
                    ->orWhere('b', '=', 1)
                    ->where('c', '=', 1)
                    ->orWhereGroup()
                        ->where('x', '=', 3)
                        ->whereGroup()
                            ->where('d', '=', 1)
                            ->where('e', '=', 1)
                        ->end()
                    ->end(),
            ],
            [
                '`a` BETWEEN 1 AND 2',
                $q()->where('a', 'BETWEEN', [1, 2]),
            ],

        ];

        foreach ($tests as [$expected, $query]) {
            $this->assertEquals(
                $eq . $expected,
                $query->compile()
            );
        }
    }


    public function testCount()
    {
        $this->assertSame(
            100,
            (new SelectQuery())
                ->select()
                ->from('items')
                ->count()
        );

        $query = (new SelectQuery())
            ->select('name')
            ->from('items')
            ->where('id', '>', 50)
            ->orderBy('id', 'asc');

        $this->assertSame(50, $query->count());

        $this->assertSame(
            'SELECT `items`.`name` FROM `items` WHERE `id` > 50 ORDER BY `id` asc',
            $query->compile()
        );
    }


    public function testExists()
    {
        $this->assertTrue(
            (new SelectQuery())
                ->from('articles')
                ->where('id', '=', 1)
                ->exists()
        );
    }


    public function testExtSelect()
    {
        $this->assertEquals(
            (new Query())
                ->select('col1', 'col2', 'col3')
                ->from(['table', 't'])
                ->whereGroup()
                    ->where('col1', '=', 1)
                    ->whereGroup()
                        ->where('col2', '=', 2)
                        ->orWhere('col3', '=', 4)
                    ->end()
                ->end()
                ->orWhereGroup(function(SelectQuery $q) {
                    $q->where('col2', '=', 5)
                      ->orWhere('col3', '=', 'six');
                })
                ->whereGroup()
                    ->where('col3', '=', 7)
                ->end()
                ->having('col1', '<', 5)
                ->having()
                    ->having('col2', '=', 'col3')
                    ->orHaving('col3', '=', 5)
                ->end()
                ->orderBy('col3', 'desc')
                ->compile(),
            "SELECT `t`.`col1`, `t`.`col2`, `t`.`col3` FROM `table` AS `t` WHERE (`col1` = 1 AND (`col2` = 2 OR `col3` = 4)) OR (`col2` = 5 OR `col3` = 'six') AND (`col3` = 7) HAVING `col1` < 5 AND (`col2` = 'col3' OR `col3` = 5) ORDER BY `col3` desc"
        );
    }
}
