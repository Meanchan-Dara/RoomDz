<?php

namespace App\Http\Controllers;

use App\Models\ViewingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ViewingRequestController extends Controller
{
    /**
     * List all viewing requests for the authenticated user.
     * Supports filtering by status: pending, confirmed, cancelled.
     */
    public function myRequests(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ViewingRequest::with(['room', 'room.category', 'room.user'])
            ->where('user_id', $user->id);

        // Filter by status (pending, confirmed, cancelled)
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
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
                    'landlord' => $vr->room->user ? [
                        'id' => $vr->room->user->id,
                        'name' => $vr->room->user->name,
                        'phone' => $vr->room->user->phone,
                    ] : null,
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
     * Show a single viewing request detail.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $vr = ViewingRequest::with(['room', 'room.category', 'room.user'])
            ->where('user_id', $user->id)
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
                    'landlord' => $vr->room->user ? [
                        'id' => $vr->room->user->id,
                        'name' => $vr->room->user->name,
                        'phone' => $vr->room->user->phone,
                    ] : null,
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
     * Cancel a viewing request (user can only cancel their own pending requests).
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $vr = ViewingRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $vr->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Viewing request cancelled successfully',
            'data' => [
                'id' => $vr->id,
                'status' => $vr->status,
            ],
        ]);
    }
}
