<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\ViewingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerDashboardController extends Controller
{
    /**
     * Get owner dashboard summary statistics matching the Landlord Profile UI.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        $formatUrl = function ($url) {
            if (empty($url) || !is_string($url)) return $url;
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                return $url;
            }
            return url(ltrim($url, '/'));
        };

        // Owner's rooms
        $rooms = Room::with(['detail', 'category'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $totalRooms = $rooms->count();
        $availableRooms = $rooms->filter(function ($r) {
            return str_contains(strtolower($r->status), 'avail');
        })->count();

        $occupiedRooms = $totalRooms - $availableRooms;
        $occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100) : 0;

        // Viewing requests count on owner's rooms
        $roomIds = $rooms->pluck('id');
        $totalRequests = ViewingRequest::whereIn('room_id', $roomIds)->count();
        $pendingRequests = ViewingRequest::whereIn('room_id', $roomIds)->where('status', 'pending')->count();
        $confirmedRequests = ViewingRequest::whereIn('room_id', $roomIds)->where('status', 'confirmed')->count();
        $rejectedRequests = ViewingRequest::whereIn('room_id', $roomIds)->where('status', 'rejected')->count();

        // Estimated revenue calculation
        $occupiedRevenue = $rooms->filter(function ($r) {
            return !str_contains(strtolower($r->status), 'avail');
        })->sum('price');

        $potentialRevenue = $rooms->sum('price');
        $targetRevenue = $potentialRevenue > 0 ? $potentialRevenue : 1500.0;

        // Recent listings for quick access on Profile screen
        $recentListings = $rooms->take(4)->map(function ($r) use ($formatUrl) {
            return [
                'id' => $r->id,
                'name' => $r->name,
                'type' => $r->type,
                'price' => (float) $r->price,
                'price_period' => $r->price_period,
                'status' => $r->status,
                'floor' => $r->detail?->floor ?? null,
                'address' => $r->address,
                'image' => $formatUrl($r->image),
                'views_count' => $r->reviews_count * 15 + 14, // Simulated view analytics
            ];
        })->values();

        return response()->json([
            'data' => [
                'host' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $formatUrl($user->avatar),
                    'is_verified' => (bool) $user->is_verified,
                    'location_tag' => $user->location_tag ?? 'Phnom Penh, Cambodia',
                    'telegram' => $user->telegram,
                    'role' => $user->role?->name ?? 'owner',
                ],
                'stats' => [
                    'total_rooms' => $totalRooms,
                    'occupied_rooms' => $occupiedRooms,
                    'available_rooms' => $availableRooms,
                    'occupancy_rate' => $occupancyRate, // e.g. 83
                ],
                'earnings' => [
                    'current_revenue' => (float) $occupiedRevenue,
                    'target_revenue' => (float) $targetRevenue,
                    'growth_percentage' => 12,
                    'linked_payouts' => [
                        [
                            'name' => 'ABA KHQR',
                            'account' => '000 123 456',
                            'type' => 'bank',
                            'status' => 'active',
                        ],
                        [
                            'name' => 'Bakong Wallet',
                            'account' => $user->phone ?? 'Bakong QR',
                            'type' => 'wallet',
                            'status' => 'active_payout',
                        ],
                    ],
                ],
                'landlord_tools' => [
                    'manage_listings_count' => $totalRooms,
                    'viewing_appointments_count' => $pendingRequests,
                    'digital_contracts_count' => $occupiedRooms,
                    'tenant_inquiries_count' => $pendingRequests,
                ],
                'my_listings' => $recentListings,
            ],
        ]);
    }
}
