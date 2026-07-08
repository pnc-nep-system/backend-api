<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgrammeEntryRequest;
use App\Http\Requests\UpdateProgrammeEntryRequest;
use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use Illuminate\Http\Request;

class ProgrammeEntryController extends Controller
{
    public function store(StoreProgrammeEntryRequest $request)
    {
        $entry = ProgrammeEntry::create([
            ...$request->validated(),
            'organisation_id' => $request->user()->organisation_id,
        ]);
        return response()->json([
            'message' => 'Programme entry created.',
            'data' => $entry,
        ], 201);
    }

    public function update(UpdateProgrammeEntryRequest $request, ProgrammeEntry $programmeEntry)
    {
        if (! $this->canManage($request, $programmeEntry)) {
            return response()->json([
                'message' => 'You are not authorized to update this entry.',
            ], 403);
        }
        $programmeEntry->update($request->validated());

        return response()->json([
            'message' => 'Programme entry updated.',
            'data' => $programmeEntry->fresh(),
        ]);
    }

    public function index(Request $request, Organisation $organisation)
    {
        $user = $request->user();

        if (! in_array($user->role, ['nep_admin', 'nep_coordinator'], true)
            && $user->organisation_id !== $organisation->id) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $entries = $organisation->programmeEntries()->get();

        return response()->json(['data' => $entries]);
    }

    public function show(Request $request, ProgrammeEntry $programmeEntry)
    {
        if (! $this->canManage($request, $programmeEntry)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }
        return response()->json(['data' => $programmeEntry]);
    }

    protected function canManage(Request $request, ProgrammeEntry $programmeEntry): bool
    {
        $user = $request->user();
        return $user->role === 'nep_admin'
            || $programmeEntry->organisation_id === $user->organisation_id;
    }
}