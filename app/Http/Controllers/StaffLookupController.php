<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Position;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Add a department or position on the fly from the staff forms. */
class StaffLookupController extends Controller
{
    public function storeDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150', Rule::unique('departments', 'name')]]);
        $department = Department::create($data);
        Audit::record('lookup.department_created', "Added department {$department->name}", $department);

        return back();
    }

    public function storePosition(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('positions', 'name')]]);
        $position = Position::create($data);
        Audit::record('lookup.position_created', "Added position {$position->name}", $position);

        return back();
    }
}
