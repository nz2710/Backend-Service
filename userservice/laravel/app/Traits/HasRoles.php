<?php

namespace App\Traits;

use App\Models\Role;

trait HasRoles
{
    public function assignRole($roles)
    {
        if (!$roles) {
            return false;
        }

        $role = $this->getStoredRole($roles);

        if (!$role instanceof Role) {
            return false;
        }

        $model = $this->getModel();

        if ($model->exists) {
            $this->roles()->sync($role, false);
            $model->load('roles');
        }

        return $this;
    }

    public function removeRole($role)
    {
        $this->roles()->detach($this->getStoredRole($role));

        $this->load('roles');

        return $this;
    }

    public function hasRole($roles)
    {
        $this->loadMissing('roles');

        if (is_string($roles)  && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $this->roles->contains('name', strtolower($roles));
        }

        if (is_int($roles)) {
            return $this->roles->contains('id', $roles);
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    public function hasAnyRole(...$roles): bool
    {
        return $this->hasRole($roles);
    }

    protected function getStoredRole($role)
    {
        if (is_numeric($role)) {
            return Role::where('id', $role)->get()->first();
        }

        if (is_string($role)) {
            return Role::where('name', $role)->get()->first();
        }

        return false;
    }

    protected function convertPipeToArray(string $pipeString)
    {
        $pipeString = trim($pipeString);

        if (strlen($pipeString) <= 2) {
            return $pipeString;
        }

        $quoteCharacter = substr($pipeString, 0, 1);
        $endCharacter = substr($quoteCharacter, -1, 1);

        if ($quoteCharacter !== $endCharacter) {
            return explode('|', $pipeString);
        }

        if (!in_array($quoteCharacter, ["'", '"'])) {
            return explode('|', $pipeString);
        }

        return explode('|', trim($pipeString, $quoteCharacter));
    }
}
