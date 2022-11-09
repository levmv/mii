<?php /** @noinspection PhpUndefinedMethodInspection */
/** @noinspection MagicMethodsValidityInspection */
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
    protected function innerBeforeChange(): void
    {
        $this->updated = $time = \time();

        if (!$this->loaded()) {
            $this->created = $time;
        }
    }
}
