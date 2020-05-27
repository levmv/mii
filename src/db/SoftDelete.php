<?php declare(strict_types=1);

namespace mii\db;

/**
 *
 *  Table must have a column int(11) 'deleted' default NULL
 *
 */
trait SoftDelete
{
    public function select_query($with_order = true, Query $query = null): Query
    {
        return parent::select_query($with_order, $query)->where('deleted', 'is', null);
    }

    public static function find_deleted()
    {
        $model = (new static);

        $query = $model->raw_query()
            ->select(['*'], true)
            ->from($model->get_table())
            ->as_object(static::class, [null, true])
            ->where('deleted', 'is not', null);

        if ($model->order_by) {
            foreach ($model->order_by as $column => $direction) {
                $query->order_by($column, $direction);
            }
        }

        return $query;
    }

    public function restore()
    {
        return $this->raw_query()
            ->update($this->get_table())
            ->set(['deleted' => null])
            ->where('id', '=', $this->id)
            ->execute();
    }


    public function delete(): void
    {
        if (!$this->loaded()) {

            throw new \Exception('Cannot delete a non-loaded model ' . get_class($this) . '!', [], []);
        }

        $this->_loaded = false;

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
