<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRequest;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function __construct(private ActivityLogger $log) {}

    public function index()
    {
        return AdminResource::collection(Admin::orderBy('id')->get());
    }

    public function store(AdminRequest $request)
    {
        $data = $request->validated();

        // The team form has no password field yet. Without one, the account gets a
        // random password nobody knows (set a real one with a "password" field or tinker).
        $admin = Admin::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'active' => (bool) $data['active'],
            'password' => $data['password'] ?? Str::random(40),
        ]);

        $this->log->record(
            $request->user(), 'create', 'admin', $admin->id,
            "Administrateur « {$admin->name} » créé",
        );

        return (new AdminResource($admin))->response()->setStatusCode(201);
    }

    public function update(AdminRequest $request, Admin $admin)
    {
        $data = $request->validated();
        $active = (bool) $data['active'];

        if ($admin->active && ! $active) {
            if ($admin->is($request->user())) {
                throw ValidationException::withMessages([
                    'active' => 'Vous ne pouvez pas désactiver votre propre compte.',
                ]);
            }
            $this->ensureAnotherActive($admin);
        }

        $admin->name = $data['name'];
        $admin->phone = $data['phone'];
        $admin->active = $active;

        $revoke = ! $active;
        if (! empty($data['password'])) {
            $admin->password = $data['password'];
            $revoke = true;
        }
        $admin->save();

        // Deactivated or new password: old tokens stop working
        if ($revoke) {
            $admin->tokens()->delete();
        }

        $this->log->record(
            $request->user(), 'update', 'admin', $admin->id,
            sprintf('Administrateur « %s » modifié (%s)', $admin->name, $active ? 'actif' : 'désactivé'),
        );

        return new AdminResource($admin);
    }

    public function destroy(Request $request, Admin $admin)
    {
        if ($admin->is($request->user())) {
            throw ValidationException::withMessages([
                'admin' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ]);
        }

        if ($admin->active) {
            $this->ensureAnotherActive($admin);
        }

        $name = $admin->name;
        $id = $admin->id;

        $admin->tokens()->delete();
        $admin->delete();

        $this->log->record(
            $request->user(), 'delete', 'admin', $id,
            "Administrateur « {$name} » supprimé",
        );

        return response()->noContent();
    }

    private function ensureAnotherActive(Admin $admin): void
    {
        $other = Admin::where('active', true)->where('id', '!=', $admin->id)->exists();

        if (! $other) {
            throw ValidationException::withMessages([
                'admin' => 'Il doit rester au moins un administrateur actif.',
            ]);
        }
    }
}