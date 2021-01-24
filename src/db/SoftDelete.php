<?php declare(strict_types=1);

namespace mii\db;

/**
 *
 *  Table must have a column int(11) 'deleted' default NULL
 *
 */
trait SoftDelete
{
    protected static function prepareQuery(SelectQuery $query): SelectQuery
    {
        return parent::prepareQuery($query->where(static::table().'.deleted', 'is', null));
    }

    public static function findDeleted(): SelectQuery
    {
        return parent::prepareQuery((new SelectQuery(static::class))->where(static::table().'.deleted', 'is not', null));
    }


    public function restore()
    {
        return static::query()
            ->update()
            ->set(['deleted' => null])
            ->where('id', '=', $this->id)
            ->execute();
    }


    public function delete(): void
    {
        $this->innerDelete();
    }

    protected function innerDelete() : void {
        if (!$this->loaded()) {
            throw new \Exception('Cannot delete a non-loaded model ' . \get_class($this) . '!', [], []);
        }

        $this->__loaded = false;

        static::query()
            ->update()
            ->set(['deleted' => \time()])
            ->where('id', '=', $this->id)
            ->execute();
    }

    public function realDelete(): void
    {
        parent::delete();
    }
}
