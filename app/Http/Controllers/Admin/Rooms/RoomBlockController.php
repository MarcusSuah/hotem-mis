<?php

namespace App\Http\Controllers\Admin\Rooms;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rooms\StoreRoomBlockRequest;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Services\Rooms\RoomBlockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class RoomBlockController extends Controller
{
    public function store(StoreRoomBlockRequest $request, Room $room, RoomBlockService $blocks): RedirectResponse
    {
        $blocks->create($room, $request->blockType(), $request->period(), $request->string('reason')->toString(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room :number blocked.', ['number' => $room->number])]);

        return back();
    }

    public function destroy(Request $request, RoomBlock $roomBlock, RoomBlockService $blocks): RedirectResponse
    {
        Gate::authorize(Permission::RoomBlocksManage->value);

        $blocks->release($roomBlock, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Block released; the room is available again for those dates.')]);

        return back();
    }
}
