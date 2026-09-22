<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = AuditLog::query()
            ->with('user:id,username,first_name,last_name')
            ->when($request->string('group')->value(), fn ($q, $group) => $q->where('event', 'like', "{$group}.%"))
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where('description', 'like', "%{$term}%"))
            ->orderByDesc('id')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'event' => $log->event,
                'description' => $log->description,
                'properties' => $log->properties,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
                'user' => $log->user ? ['id' => $log->user->id, 'username' => $log->user->username, 'name' => $log->user->name] : null,
            ]);

        return Inertia::render('admin/audit/index', [
            'logs' => $logs,
            'filters' => $request->only('q', 'group'),
            'groups' => ['auth' => 'Sign-ins', 'user' => 'Users', 'role' => 'Roles', 'staff' => 'Staff'],
        ]);
    }
}
