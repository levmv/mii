<?php declare(strict_types=1);

namespace mii\db;

/**
 *
 *  Table must have a column int(11) 'deleted' default NULL
 *
 */
trait SoftDelete
{
    public static function select_query($with_order = true, SelectQuery $query = null): SelectQuery
    {
        return static::find()->where('deleted', 'is', null);
    }

    public static function find_deleted()
    {
        $query = static::query()
            ->select(['*'], true)
            ->from(static::$table)
            ->as_object(static::class)
            ->where('deleted', 'is not', null);

        if (!empty(static::$order_by)) {
            foreach (static::$order_by as $column => $direction) {
                $query->order_by($column, $direction);
            }
        }

        return $query;
    }

    public function restore()
    {
        return static::query()
            ->update(static::$table)
            ->set(['deleted' => null])
            ->where('id', '=', $this->id)
            ->execute();
    }


    public function delete(): void
    {
        if (!$this->loaded()) {

            throw new \Exception('Cannot delete a non-loaded model ' . get_class($this) . '!', [], []);
        }

        $this->__loaded = false;

        $this->raw_query()
            ->update($this->get_table())
            ->set(['deleted' => time()])
            ->where('id', '=', $this->id)
            ->execute();
    }

    public function real_delete() : void
    {
        parent::delete();
    }

}
