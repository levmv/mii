<?php /** @noinspection MagicMethodsValidityInspection */
declare(strict_types=1);

namespace mii\db;

/**
 *
 *  End model must have columns:
 *  int created
 *  int updated
 */
trait AutoDates
{
    protected function innerBeforeChange()
    {
        if ($this->loaded()) {
            $this->updated = \time();
        } else {
            $this->created = \time();
        }
    }
}
