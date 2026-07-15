<?php

use Illuminate\Support\Facades\Route;
use Modules\HR\Http\Controllers\AttendanceController;
use Modules\HR\Http\Controllers\EmployeeController;
use Modules\HR\Http\Controllers\LeaveController;

Route::middleware(['auth:sanctum', 'permission.team'])->prefix('v1/hr')->group(function () {
    // Karyawan (profil & data induk)
    Route::get('employees', [EmployeeController::class, 'index'])
        ->middleware('can:employee.view')->name('hr.employees.index');
    Route::post('employees', [EmployeeController::class, 'store'])
        ->middleware('can:employee.create')->name('hr.employees.store');
    Route::get('employees/{id}', [EmployeeController::class, 'show'])
        ->middleware('can:employee.view')->name('hr.employees.show');
    Route::put('employees/{id}', [EmployeeController::class, 'update'])
        ->middleware('can:employee.update')->name('hr.employees.update');

    // Ringkasan HR (dashboard & laporan)
    Route::get('summary', [EmployeeController::class, 'summary'])
        ->middleware('can:employee.view')->name('hr.summary');

    // Absensi
    Route::get('attendance', [AttendanceController::class, 'index'])
        ->middleware('can:attendance.view')->name('hr.attendance.index');
    Route::get('attendance/me', [AttendanceController::class, 'me'])
        ->middleware('can:attendance.create')->name('hr.attendance.me');
    Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn'])
        ->middleware('can:attendance.create')->name('hr.attendance.clock-in');
    Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut'])
        ->middleware('can:attendance.create')->name('hr.attendance.clock-out');

    // Cuti & izin
    Route::get('leaves', [LeaveController::class, 'index'])
        ->middleware('can:leave.view')->name('hr.leaves.index');
    Route::get('leaves/mine', [LeaveController::class, 'mine'])
        ->middleware('can:leave.create')->name('hr.leaves.mine');
    Route::get('leaves/quota', [LeaveController::class, 'quota'])
        ->middleware('can:leave.create')->name('hr.leaves.quota');
    Route::post('leaves', [LeaveController::class, 'store'])
        ->middleware('can:leave.create')->name('hr.leaves.store');
    Route::post('leaves/{id}/approve', [LeaveController::class, 'approve'])
        ->middleware('can:leave.approve')->name('hr.leaves.approve');
    Route::post('leaves/{id}/reject', [LeaveController::class, 'reject'])
        ->middleware('can:leave.approve')->name('hr.leaves.reject');
    Route::get('employees/{id}/quota', [LeaveController::class, 'employeeQuota'])
        ->middleware('can:employee.view')->name('hr.employees.quota');
});
