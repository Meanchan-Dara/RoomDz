<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\ViewingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerViewingRequestController extends Controller
{
    /**
     * List all viewing requests on rooms owned by the authenticated owner.
     * Supports filtering by status and room_id.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get all room IDs owned by this owner
        $ownerRoomIds = Room::where('user_id', $user->id)->pluck('id');

        $query = ViewingRequest::with(['room', 'room.category', 'user'])
            ->whereIn('room_id', $ownerRoomIds);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by specific room
        if ($request->filled('room_id')) {
            $query->where('room_id', $request->input('room_id'));
        }

        $sort = $request->input('sort', 'created_at');
        $order = $request->input('order', 'desc');
        $query->orderBy($sort, $order);

        $perPage = (int) $request->input('per_page', 15);
        $viewingRequests = $query->paginate($perPage);

        $formatUrl = function ($url) {
            if (empty($url) || !is_string($url)) return $url;
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                return $url;
            }
            return url(ltrim($url, '/'));
        };

        $data = collect($viewingRequests->items())->map(function ($vr) use ($formatUrl) {
            return [
                'id' => $vr->id,
                'room' => $vr->room ? [
                    'id' => $vr->room->id,
                    'name' => $vr->room->name,
                    'price' => (float) $vr->room->price,
                    'address' => $vr->room->address,
                    'image' => $formatUrl($vr->room->image),
                ] : null,
                'requester' => $vr->user ? [
                    'id' => $vr->user->id,
                    'name' => $vr->user->name,
                    'email' => $vr->user->email,
                    'phone' => $vr->user->phone,
                    'avatar' => $formatUrl($vr->user->avatar),
                ] : null,
                'name' => $vr->name,
                'phone' => $vr->phone,
                'email' => $vr->email,
                'preferred_date' => $vr->preferred_date?->format('Y-m-d'),
                'preferred_time' => $vr->preferred_time,
                'notes' => $vr->notes,
                'status' => $vr->status,
                'created_at' => $vr->created_at?->toISOString(),
                'updated_at' => $vr->updated_at?->toISOString(),
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $viewingRequests->currentPage(),
                'last_page' => $viewingRequests->lastPage(),
                'per_page' => $viewingRequests->perPage(),
                'total' => $viewingRequests->total(),
            ],
        ]);
    }

    /**
     * Show a specific viewing request on one of the owner's rooms.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $ownerRoomIds = Room::where('user_id', $user->id)->pluck('id');

        $vr = ViewingRequest::with(['room', 'room.category', 'user'])
            ->whereIn('room_id', $ownerRoomIds)
            ->findOrFail($id);

        $formatUrl = function ($url) {
            if (empty($url) || !is_string($url)) return $url;
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                return $url;
            }
            return url(ltrim($url, '/'));
        };

        return response()->json([
            'data' => [
                'id' => $vr->id,
                'room' => $vr->room ? [
                    'id' => $vr->room->id,
                    'name' => $vr->room->name,
                    'price' => (float) $vr->room->price,
                    'address' => $vr->room->address,
                    'image' => $formatUrl($vr->room->image),
                ] : null,
                'requester' => $vr->user ? [
                    'id' => $vr->user->id,
                    'name' => $vr->user->name,
                    'email' => $vr->user->email,
                    'phone' => $vr->user->phone,
                    'avatar' => $formatUrl($vr->user->avatar),
                ] : null,
                'name' => $vr->name,
                'phone' => $vr->phone,
                'email' => $vr->email,
                'preferred_date' => $vr->preferred_date?->format('Y-m-d'),
                'preferred_time' => $vr->preferred_time,
                'notes' => $vr->notes,
                'status' => $vr->status,
                'created_at' => $vr->created_at?->toISOString(),
                'updated_at' => $vr->updated_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Confirm/accept a pending viewing request.
     */
    public function confirm(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $ownerRoomIds = Room::where('user_id', $user->id)->pluck('id');

        $vr = ViewingRequest::whereIn('room_id', $ownerRoomIds)
            ->where('status', 'pending')
            ->findOrFail($id);

        $vr->update(['status' => 'confirmed']);

        return response()->json([
            'message' => 'Viewing request confirmed successfully',
            'data' => [
                'id' => $vr->id,
                'status' => $vr->status,
            ],
        ]);
    }

    /**
     * Reject a pending viewing request.
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $ownerRoomIds = Room::where('user_id', $user->id)->pluck('id');

        $vr = ViewingRequest::whereIn('room_id', $ownerRoomIds)
            ->where('status', 'pending')
            ->findOrFail($id);

        $vr->update(['status' => 'rejected']);

        return response()->json([
            'message' => 'Viewing request rejected successfully',
            'data' => [
                'id' => $vr->id,
                'status' => $vr->status,
            ],
        ]);
    }
}
