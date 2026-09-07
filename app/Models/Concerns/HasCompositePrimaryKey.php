<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Small Eloquent compatibility helper for TrainHub bridge tables whose
 * database primary key is made from more than one column.
 *
 * Eloquent's find()/whereKey() helpers still assume a single key, so callers
 * should query composite-key models by their component columns. Loaded models
 * can however be safely save()'d and delete()'d because update/delete queries
 * are constrained by every primary-key column.
 */
trait HasCompositePrimaryKey
{
    protected function setKeysForSelectQuery($query)
    {
        foreach ((array) $this->getKeyName() as $keyName) {
            $query->where($keyName, '=', $this->getKeyForCompositeQuery($keyName));
        }

        return $query;
    }

    protected function setKeysForSaveQuery($query)
    {
        foreach ((array) $this->getKeyName() as $keyName) {
            $query->where($keyName, '=', $this->getKeyForCompositeQuery($keyName));
        }

        return $query;
    }

    private function getKeyForCompositeQuery(string $keyName): mixed
    {
        return $this->original[$keyName] ?? $this->getAttribute($keyName);
    }

    public function getKey(): mixed
    {
        $key = [];

        foreach ((array) $this->getKeyName() as $keyName) {
            $key[$keyName] = $this->getAttribute($keyName);
        }

        return $key;
    }

    public function getIncrementing(): bool
    {
        return false;
    }
}
