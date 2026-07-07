<?php

namespace Modules\HR\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\HR\Models\Employee;

class EmployeeService
{
    /**
     * List employees for a business with optional filters.
     *
     * @param  array{status?: ?string, search?: ?string}  $filters
     */
    public function list(string $businessId, array $filters = []): Collection
    {
        $query = Employee::query()->where('business_id', $businessId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = mb_strtolower($filters['search']);
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
        }

        return $query->orderBy('name')->get();
    }

    public function create(string $businessId, array $data): Employee
    {
        return Employee::create([...$data, 'business_id' => $businessId]);
    }

    public function update(Employee $employee, array $data): Employee
    {
        $employee->update($data);

        return $employee->fresh();
    }

    /**
     * Resolve the employee record linked to a user account (self-service seam
     * for attendance & leave). Null when the user has no employee profile.
     */
    public function findByUser(string $businessId, string $userId): ?Employee
    {
        return Employee::query()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();
    }
}
