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

        $totalRooms = $rooms->sum(fn($r) => (int) ($r->total_units ?? 1));
        $availableRooms = $rooms->sum(function ($r) {
            if ($r->available_units !== null) {
                return (int) $r->available_units;
            }
            $status = strtolower($r->status ?? '');
            $isAvail = str_contains($status, 'avail') || !str_contains($status, 'occup');
            return $isAvail ? 1 : 0;
        });

        $occupiedRooms = max(0, $totalRooms - $availableRooms);
        $occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100) : 0;

        // Viewing requests count on owner's rooms
        $roomIds = $rooms->pluck('id');
        $totalRequests = ViewingRequest::whereIn('room_id', $roomIds)->count();
        $pendingRequests = ViewingRequest::whereIn('room_id', $roomIds)->where('status', 'pending')->count();
        $confirmedRequests = ViewingRequest::whereIn('room_id', $roomIds)->where('status', 'confirmed')->count();
        $rejectedRequests = ViewingRequest::whereIn('room_id', $roomIds)->where('status', 'rejected')->count();

        // Estimated revenue calculation based on occupied rooms / units
        $occupiedRevenue = $rooms->sum(function ($r) {
            $totalUnits = (int) ($r->total_units ?? 1);
            if ($r->available_units !== null) {
                $occupied = max(0, $totalUnits - (int) $r->available_units);
                return $occupied * (float) $r->price;
            }
            $status = strtolower($r->status ?? '');
            $isOccupied = str_contains($status, 'occup') || str_contains($status, 'rent');
            return $isOccupied ? (float) $r->price : 0;
        });

        $potentialRevenue = $rooms->sum(fn($r) => (int) ($r->total_units ?? 1) * (float) $r->price);
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
                'total_units' => (int) ($r->total_units ?? 1),
                'available_units' => (int) ($r->available_units ?? 1),
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
                    'bakong_account_id' => $user->bakong_account_id,
                    'bakong_merchant_name' => $user->bakong_merchant_name,
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
                            'name' => 'Bakong Account',
                            'account' => $user->bakong_account_id ?? ($user->phone ?? 'Not configured'),
                            'type' => 'wallet',
                            'status' => $user->bakong_account_id ? 'active_payout' : 'needs_setup',
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
